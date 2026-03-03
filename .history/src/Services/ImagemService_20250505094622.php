<?php

namespace App\Services;

use App\MagentoApiClient;

class ImagemService
{
    protected MagentoApiClient $client;

    public function __construct()
    {
        $this->client = new MagentoApiClient();
    }

    public function enviarImagemProduto(string $sku, string $caminhoImagem, string $nomeImagem): array
    {
        if (!file_exists($caminhoImagem)) {
            return [
                'success' => false,
                'mensagem' => "Arquivo de imagem não encontrado: $caminhoImagem"
            ];
        }

        $tipoMime = mime_content_type($caminhoImagem);
        $conteudoBase64 = base64_encode(file_get_contents($caminhoImagem));

        $payload = [
            'media_type' => 'image',
            'label' => $nomeImagem,
            'position' => 1,
            'disabled' => false,
            'types' => ['image', 'small_image', 'thumbnail'],
            'content' => [
                'base64_encoded_data' => $conteudoBase64,
                'type' => $tipoMime,
                'name' => $nomeImagem
            ]
        ];

        return $this->client->uploadImageToProduct($sku, $payload);
    }
}
