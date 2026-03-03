<?php

namespace App\Services;

class CategoriaService
{
    public static function obterCategorias(array $value): array
    {
        $categoryIds = [2]; // Categoria padrão obrigatória

        // 🚀 Lançamento + MaterialID
        if (isset($value['Lancamento']) && $value['Lancamento'] == '1') {
            $categoryIds[] = 41;
            if (($value['MaterialID_Int'] ?? null) == 0) $categoryIds[] = 42;
            if (($value['MaterialID_Int'] ?? null) == 2) $categoryIds[] = 43;
        }

        // 🚀 Folheado a Ouro
        if (($value['MaterialID_Int'] ?? null) == 0) {
            $categoryIds[] = 44;

            if (($value['GrupoID_Int'] ?? '') === '03') $categoryIds[] = 45;
            if (strpos($value['Descricao'] ?? '', 'Bracelete') !== false) $categoryIds[] = 46;
            if (($value['GrupoID_Int'] ?? '') === '02') $categoryIds[] = 47;
            if (strpos($value['Descricao'] ?? '', 'Choker') !== false) $categoryIds[] = 48;

            if (($value['GrupoID_Int'] ?? '') === '04') {
                $categoryIds[] = 49;
                self::categoriasPorTamanho($categoryIds, $value['TamanhoID_Int'] ?? '');
            }

            if (strpos($value['Descricao'] ?? '', 'Escapulario') !== false) $categoryIds[] = 56;
            if (strpos($value['Descricao'] ?? '', 'Gargantilha') !== false) $categoryIds[] = 57;
            if (strpos($value['Descricao'] ?? '', 'Piercing') !== false) $categoryIds[] = 58;

            if (($value['GrupoID_Int'] ?? '') === '01') {
                $categoryIds[] = 59;

                if (strpos($value['Descricao'] ?? '', 'Pulseira') !== false) {
                    $categoryIds[] = 60;
                    self::categoriasPorTamanhoPulseira($categoryIds, $value['TamanhoID_Int'] ?? '');
                }
            }

            if (strpos($value['Descricao'] ?? '', 'Tornozeleira') !== false) $categoryIds[] = 66;
        }

        // 🚀 Prata Pura
        if (($value['MaterialID_Int'] ?? null) == 2) {
            $categoryIds[] = 67;
            if (($value['GrupoID_Int'] ?? '') === '03') $categoryIds[] = 68;
            if (strpos($value['Descricao'] ?? '', 'Berloque') !== false) $categoryIds[] = 69;
            if (strpos($value['Descricao'] ?? '', 'Bracelete') !== false) $categoryIds[] = 70;
            if (($value['GrupoID_Int'] ?? '') === '02') $categoryIds[] = 71;
            if (strpos($value['Descricao'] ?? '', 'Choker') !== false) $categoryIds[] = 72;
            if (strpos($value['Descricao'] ?? '', 'Escapulario') !== false) $categoryIds[] = 73;
            if (strpos($value['Descricao'] ?? '', 'Gargantilha') !== false) $categoryIds[] = 74;
            if (strpos($value['Descricao'] ?? '', 'Piercing') !== false) $categoryIds[] = 75;
            if (($value['GrupoID_Int'] ?? '') === '01') $categoryIds[] = 76;
            if (strpos($value['Descricao'] ?? '', 'Pulseira') !== false) $categoryIds[] = 77;
            if (strpos($value['Descricao'] ?? '', 'Terco') !== false) $categoryIds[] = 78;
            if (strpos($value['Descricao'] ?? '', 'Tornozeleira') !== false) $categoryIds[] = 79;
        }

        return array_unique($categoryIds);
    }

    private static function categoriasPorTamanho(array &$categorias, string $tamanho): void
    {
        switch ($tamanho) {
            case '4P': $categorias[] = 50; break;
            case '4F': $categorias[] = 51; break;
            case '4G': $categorias[] = 52; break;
            case '4H': $categorias[] = 53; break;
            case '4I': $categorias[] = 54; break;
            case '4T': $categorias[] = 55; break;
        }
    }

    private static function categoriasPorTamanhoPulseira(array &$categorias, string $tamanho): void
    {
        switch ($tamanho) {
            case '4A': $categorias[] = 61; break;
            case '4B': $categorias[] = 62; break;
            case '4C': $categorias[] = 63; break;
            case '4J': $categorias[] = 64; break;
            case '4Q': $categorias[] = 65; break;
        }
    }
}
