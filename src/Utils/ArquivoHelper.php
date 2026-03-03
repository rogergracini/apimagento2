<?php

namespace App\Utils;

class ArquivoHelper
{
    public static function limparArquivosXml(string $caminho)
    {
        $arquivos = ['Produto.xml', 'Preco.xml'];

        foreach ($arquivos as $arquivo) {
            $arquivoCompleto = $caminho . '/' . $arquivo;

            if (file_exists($arquivoCompleto)) {
                unlink($arquivoCompleto);
            }
        }
    }
}
