<?php

namespace App\Services;

class CategoriaService
{
    public function obterCategorias(array $dadosProduto): array
    {
        $categorias = [2]; // Categoria default obrigatória

        // Categoria para lançamentos
        if (isset($dadosProduto['Lancamento']) && $dadosProduto['Lancamento'] == '1') {
            $categorias[] = 41;
        }

        // Verifica material e aplica regras
        if (isset($dadosProduto['MaterialID_Int'])) {
            $material = (int) $dadosProduto['MaterialID_Int'];

            if ($material === 0) { // Folheado a Ouro
                $categorias[] = 44;

                if (($dadosProduto['GrupoID_Int'] ?? '') === '03') $categorias[] = 45;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Bracelete') !== false) $categorias[] = 46;
                if (($dadosProduto['GrupoID_Int'] ?? '') === '02') $categorias[] = 47;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Choker') !== false) $categorias[] = 48;
                if (($dadosProduto['GrupoID_Int'] ?? '') === '04') {
                    $categorias[] = 49;
                    $this->categoriasPorTamanho($categorias, $dadosProduto['TamanhoID_Int'] ?? '');
                }
                if (strpos($dadosProduto['Descricao'] ?? '', 'Escapulario') !== false) $categorias[] = 56;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Gargantilha') !== false) $categorias[] = 57;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Piercing') !== false) $categorias[] = 58;
                if (($dadosProduto['GrupoID_Int'] ?? '') === '01') {
                    $categorias[] = 59;
                    if (strpos($dadosProduto['Descricao'] ?? '', 'Pulseira') !== false) {
                        $categorias[] = 60;
                        $this->categoriasPorTamanhoPulseira($categorias, $dadosProduto['TamanhoID_Int'] ?? '');
                    }
                }
                if (strpos($dadosProduto['Descricao'] ?? '', 'Tornozeleira') !== false) $categorias[] = 66;
            }

            if ($material === 2) { // Prata pura
                $categorias[] = 67;

                if (($dadosProduto['GrupoID_Int'] ?? '') === '03') $categorias[] = 68;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Berloque') !== false) $categorias[] = 69;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Bracelete') !== false) $categorias[] = 70;
                if (($dadosProduto['GrupoID_Int'] ?? '') === '02') $categorias[] = 71;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Choker') !== false) $categorias[] = 72;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Escapulario') !== false) $categorias[] = 73;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Gargantilha') !== false) $categorias[] = 74;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Piercing') !== false) $categorias[] = 75;
                if (($dadosProduto['GrupoID_Int'] ?? '') === '01') $categorias[] = 76;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Pulseira') !== false) $categorias[] = 77;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Terco') !== false) $categorias[] = 78;
                if (strpos($dadosProduto['Descricao'] ?? '', 'Tornozeleira') !== false) $categorias[] = 79;
            }
        }

        return array_unique($categorias);
    }

    private function categoriasPorTamanho(array &$categorias, string $tamanho)
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

    private function categoriasPorTamanhoPulseira(array &$categorias, string $tamanho)
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
