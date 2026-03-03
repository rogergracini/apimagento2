<?php

namespace App\Services;

class XmlParserService
{
    public function carregarProdutos(string $produtoXmlPath, string $precoXmlPath): array
    {
        if (!file_exists($produtoXmlPath) || !file_exists($precoXmlPath)) {
            throw new \Exception("Arquivos XML não encontrados.");
        }

        $dadosProduto = $this->parseXmlParaArray($produtoXmlPath);
        $dadosPreco = $this->parseXmlParaArray($precoXmlPath);

        $produtos = [];

        // Agrupa dados por ProdutoID_Int e TabelaID_Int
        foreach ($dadosPreco as $preco) {
            $sku = $preco['ProdutoID_Int'] ?? null;
            $tabela = $preco['TabelaID_Int'] ?? null;

            if (!$sku || !$tabela) {
                continue;
            }

            if (!isset($produtos[$sku])) {
                $produtos[$sku] = [];
            }

            if (!isset($produtos[$sku]['groups'])) {
                $produtos[$sku]['groups'] = [];
            }

            $produtos[$sku]['groups'][] = [$tabela, $preco['Preco'] ?? 0];
            $produtos[$sku] = array_merge($produtos[$sku], $preco); // inclui campos de preço
        }

        foreach ($dadosProduto as $produto) {
            $sku = $produto['ProdutoID_Int'] ?? null;
            if ($sku && isset($produtos[$sku])) {
                $produtos[$sku] = array_merge($produtos[$sku], $produto);
            }
        }

        return $produtos;
    }

    private function parseXmlParaArray(string $xmlFilePath): array
    {
        $xml = simplexml_load_file($xmlFilePath);
        $json = json_encode($xml);
        $array = json_decode($json, true);

        if (!isset($array['Row'])) {
            return [];
        }

        // Se for uma linha única, transforma em array
        if (isset($array['Row']['ProdutoID_Int'])) {
            return [$array['Row']];
        }

        return $array['Row'];
    }
}
