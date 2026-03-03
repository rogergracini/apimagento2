<?php

namespace App\Utils;

class SlugHelper
{
    public static function slug(string $texto): string
    {
        // Remove acentos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);

        // Substitui caracteres especiais por espaço
        $texto = preg_replace('/[^A-Za-z0-9-]+/', ' ', $texto);

        // Converte múltiplos espaços em um só hífen
        $texto = preg_replace('/\s+/', '-', trim($texto));

        // Remove hífens duplicados e converte para minúsculas
        $texto = strtolower(preg_replace('/-+/', '-', $texto));

        return $texto;
    }
}
