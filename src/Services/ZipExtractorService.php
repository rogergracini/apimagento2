<?php

namespace App\Services;

use App\Utils\ArquivoHelper;

class ZipExtractorService
{
    public static function extrairXmlDoZip(string $caminhoZip, string $destino)
    {
        // 1. Limpa arquivos antigos
        ArquivoHelper::limparArquivosXml($destino);

        // 2. Verifica se o ZIP existe
        if (!file_exists($caminhoZip) || filesize($caminhoZip) === 0) {
            throw new \Exception("O arquivo arq.zip não existe ou está vazio.");
        }

        $zip = new \ZipArchive;
        if ($zip->open($caminhoZip) === TRUE) {
            $okPreco = false;
            $okProduto = false;
            $okImagem = false;

            // Extrai Preco.xml se existir
            if ($zip->locateName('Preco.xml') !== false) {
                $okPreco = $zip->extractTo($destino, ['Preco.xml']);
            }

            // Extrai Produto.xml se existir
            if ($zip->locateName('Produto.xml') !== false) {
                $okProduto = $zip->extractTo($destino, ['Produto.xml']);
            }

            // Extrai Imagem.xml se existir
            if ($zip->locateName('Imagem.xml') !== false) {
                $okImagem = $zip->extractTo($destino, ['Imagem.xml']);
            }
            
            $zip->close();

            // Lança um erro apenas se os arquivos essenciais (Produto e Preco) não forem extraídos
            if (!$okPreco || !$okProduto) {
                throw new \Exception("Falha ao extrair os arquivos essenciais Produto.xml e/ou Preco.xml do ZIP.");
            }

            // Apenas emite um aviso se o Imagem.xml não for encontrado, pois o script pode funcionar sem ele (sem a otimização)
            if (!$okImagem) {
                echo "[AVISO] Arquivo Imagem.xml não encontrado dentro do arq.zip. A importação continuará sem a otimização de imagens.\n";
            }

            return "Arquivos XML extraídos com sucesso.";
        } else {
            throw new \Exception("Erro ao abrir o arquivo ZIP.");
        }
    }
}
