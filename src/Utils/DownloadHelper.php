<?php

namespace App\Utils;

class DownloadHelper
{
    public static function baixarImagem(string $url, string $caminhoDestino): bool
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $conteudo = curl_exec($ch);
        $erro = curl_error($ch);
        curl_close($ch);

        if (!$conteudo || strlen(trim($conteudo)) === 0) {
            error_log("Erro ao baixar imagem de $url: $erro");
            return false;
        }

        $pasta = dirname($caminhoDestino);
        if (!is_dir($pasta)) {
            mkdir($pasta, 0777, true);
        }

        file_put_contents($caminhoDestino, $conteudo);
        return true;
    }
}
