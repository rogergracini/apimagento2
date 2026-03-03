<?php

namespace App\Services;

class XmlParserService
{
    public function carregarProdutos(string $produtoXmlPath, string $precoXmlPath, ?string $sku = null): array
    {
        if (!file_exists($produtoXmlPath) || !file_exists($precoXmlPath)) {
            throw new \Exception("Arquivos XML não encontrados.");
        }

        $dadosProduto = $this->parseXmlParaArray($produtoXmlPath);
        $dadosPreco = $this->parseXmlParaArray($precoXmlPath);

        $produtos = [];

        foreach ($dadosPreco as $preco) {
            $produtoSku = $preco['ProdutoID_Int'] ?? null;
            $tabela = $preco['TabelaID_Int'] ?? null;

            if (!$produtoSku || !$tabela) {
                continue;
            }

            if ($sku && $produtoSku !== $sku) {
                continue;
            }

            if (!isset($produtos[$produtoSku])) {
                $produtos[$produtoSku] = [];
            }

            if (!isset($produtos[$produtoSku]['groups'])) {
                $produtos[$produtoSku]['groups'] = [];
            }

            $produtos[$produtoSku]['groups'][] = [$tabela, $preco['Preco'] ?? 0];
            $produtos[$produtoSku] = array_merge($produtos[$produtoSku], $preco);
        }

        foreach ($dadosProduto as $produto) {
            $produtoSku = $produto['ProdutoID_Int'] ?? null;
            if ($produtoSku && isset($produtos[$produtoSku])) {
                $produtos[$produtoSku] = array_merge($produtos[$produtoSku], $produto);
            }
        }

        return array_values($produtos); // Retorna apenas os valores (sem chaves por SKU)
    }

    public function carregarProdutosXml(): array
    {
        $produtoXmlPath = __DIR__ . '/../../../Produto.xml';
        $precoXmlPath   = __DIR__ . '/../../../Preco.xml';

        return $this->carregarProdutos($produtoXmlPath, $precoXmlPath);
    }

    private function parseXmlParaArray(string $xmlFilePath): array
    {
        $xml = simplexml_load_file($xmlFilePath);
        $array = json_decode(json_encode($xml), true);

        if (!isset($array['Row'])) {
            return [];
        }

        if (isset($array['Row']['ProdutoID_Int'])) {
            return [$array['Row']];
        }

        return $array['Row'];
    }
}
