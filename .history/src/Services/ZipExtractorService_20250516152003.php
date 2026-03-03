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
            $ok1 = $ok2 = false;

            if ($zip->locateName('Preco.xml') !== false) {
                $ok1 = $zip->extractTo($destino, ['Preco.xml']);
            }

            if ($zip->locateName('Produto.xml') !== false) {
                $ok2 = $zip->extractTo($destino, ['Produto.xml']);
            }

            $zip->close();

            if (!$ok1 && !$ok2) {
                throw new \Exception("Nenhum dos arquivos XML desejados foi extraído.");
            }

            return "Arquivos XML extraídos com sucesso.";
        } else {
            throw new \Exception("Erro ao abrir o arquivo ZIP.");
        }
    }
}
