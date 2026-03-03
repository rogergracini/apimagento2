<?php

namespace App\Services;

use App\MagentoApiClient;
use App\Services\XmlParserService;
use App\Services\ProcessadoService;
use App\Services\PrecoService;
use App\Services\CategoriaService;
use App\Utils\SlugHelper;
use App\Utils\DownloadHelper;

class ProdutoService
{
    protected $magentoClient;
    protected $xmlService;

    public function __construct()
    {
        $this->magentoClient = new MagentoApiClient();
        $this->xmlService = new XmlParserService();
    }

    // ✅ Método para importar todos os produtos do XML
    public function importarProdutos(): array
    {
        $produtoXml = __DIR__ . '/../../Produto.xml';
        $precoXml = __DIR__ . '/../../Preco.xml';
        $produtos = $this->xmlService->carregarProdutos($produtoXml, $precoXml);

        $resultados = [];

        foreach ($produtos as $produto) {
            $resultado = $this->importarProduto($produto);
            $resultados[] = array_merge(['sku' => $produto['ProdutoID_Int'] ?? ''], $resultado);
        }

        return $resultados;
    }

    public function importarProduto(array $produto): array
{
    $sku = $produto['ProdutoID_Int'] ?? null;

    if (!$sku) {
        return ['status' => 'erro', 'mensagem' => 'SKU ausente'];
    }

    if (stripos($sku, 'BR') !== false) {
        return ['status' => 'ignorado', 'mensagem' => 'SKU contém "BR", ignorado'];
    }

    $processadoService = new ProcessadoService();
    if ($processadoService->jaProcessado($sku)) {
        return ['status' => 'ignorado', 'mensagem' => 'SKU já processado anteriormente'];
    }

    if (
        isset($produto['Ativo']) &&
        ($produto['Ativo'] === false || strtolower($produto['Ativo']) === 'false')
    ) {
        return ['status' => 'ignorado', 'mensagem' => 'Produto inativo'];
    }

    // Nome formatado e URL amigável
    $descricao = $produto['Descricao'] ?? 'Produto';
    $tipo = $produto['TipoID_Int'] ?? '';
    $largura = $produto['Largura_MM'] ?? '';
    $altura = $produto['Altura_MM'] ?? '';
    $peso = $produto['Peso'] ?? '';
    $nomeFormatado = trim("{$descricao} - {$tipo} - {$largura}mm x {$altura}mm - {$peso}gr");
    $UrlKey = SlugHelper::gerarSlug("{$descricao} {$sku}");

    // Categorias
    $categorias = CategoriaService::obterCategorias($produto);

    // Tipo de produto dinâmico com base no SKU
    $tipoProduto = (stripos($sku, 'AG') !== false) ? 'AG' : 'OURO';
    $porGrama = isset($produto['PrecoGrama']) && $produto['PrecoGrama'] === '1';
    $pesoFinal = $porGrama ? (float)($produto['Peso'] ?? 1) : 1;

    // Preços por grupo
    $precoService = new PrecoService();
    $grupos = $precoService->getPrecosPorGrupoMagento($produto);
    $calculo = $precoService->calcularPrecos($grupos, $tipoProduto, $pesoFinal, $porGrama);

    // Payload principal do produto
    $payload = [
        'sku' => $sku,
        'name' => $nomeFormatado,
        'url_key' => $UrlKey,
        'description' => $nomeFormatado,
        'short_description' => $nomeFormatado,
        'category_ids' => $categorias,
        'price' => $calculo['preco_base'],
        'status' => 1,
        'tax_class_id' => 0,
        'type_id' => 'simple',
        'attribute_set_id' => 4,
        'weight' => (float)($produto['Peso'] ?? 0),
        'visibility' => 4,
        'extension_attributes' => [
            'stock_item' => [
                'qty' => 3150,
                'is_in_stock' => true,
                'use_config_manage_stock' => false,
                'manage_stock' => true,
                'min_qty' => 0,
                'min_sale_qty' => 0,
                'max_sale_qty' => 100000,
                'backorders' => 0,
                'use_config_min_qty' => true,
                'use_config_min_sale_qty' => true,
                'use_config_max_sale_qty' => true,
                'use_config_backorders' => true,
                'use_config_notify_stock_qty' => true,
            ],
        ],
    ];

    // Envia o produto via API
    $resposta = $this->magentoClient->createOrUpdateProduct($payload);

    if (!isset($resposta['id'])) {
        return [
            'status' => 'erro',
            'mensagem' => $resposta['message'] ?? 'Erro desconhecido',
        ];
    }

    // Marca como processado
    $processadoService->marcarComoProcessado($sku);

    // Aplica preços por grupo
    foreach ($calculo['tier_prices'] as $tier) {
        $this->magentoClient->setPrecoPorGrupo($sku, $tier['cust_group'], $tier['price']);
    }

    // Imagem
    $imagemId = $produto['ImagemID'] ?? null;
    if ($imagemId && strpos($imagemId, '/') !== false) {
        $partes = explode('/', $imagemId);
        $imageName = end($partes);

        if (!preg_match('/\.(jpg|jpeg|png|gif)$/i', $imageName)) {
            $imageName .= '.jpg';
        }

        $urlImagem = 'http://app.galle.com.br/images/grandes/' . $imagemId . '.jpg';
        $caminhoLocal = "/home3/pratas31/testeagencia.dev.br/images/$imageName";

        if (DownloadHelper::baixarImagem($urlImagem, $caminhoLocal)) {
            $imagemBase64 = base64_encode(file_get_contents($caminhoLocal));

            $payloadImagem = [
                'media_type' => 'image',
                'label' => $sku,
                'position' => 1,
                'disabled' => false,
                'types' => ['image', 'small_image', 'thumbnail'],
                'content' => [
                    'base64_encoded_data' => $imagemBase64,
                    'type' => 'image/jpeg',
                    'name' => $imageName
                ]
            ];

            $this->magentoClient->uploadImageToProduct($sku, $payloadImagem);
        }
    }

    // Retorno final
    return [
        'status' => 'importado',
        'mensagem' => 'Produto criado/atualizado',
        'tipo_produto' => $tipoProduto,
        'por_grama' => $porGrama,
        'peso_aplicado' => $pesoFinal,
        'categorias' => $categorias,
        'grupos_aplicados' => $calculo['tier_prices'],
        'preco_base' => $calculo['preco_base']
    ];
}
public function buscarProdutoMagento(string $sku): ?array
    {
        return $this->magentoClient->getProductBySku($sku);
    }
}
