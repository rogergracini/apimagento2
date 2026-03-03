<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('app/Mage.php');

// Remover apenas 'Preco.xml' e 'Produto.xml'
$xmlFiles = ['Preco.xml', 'Produto.xml'];
foreach ($xmlFiles as $xmlFile) {
    if (file_exists($xmlFile)) {
        unlink($xmlFile);
        echo "Arquivo $xmlFile antigo removido.\n";
    }
}

echo "Baixando arquivo arq.zip ...";

// Usando cURL para baixar o arquivo
$url = "ftp://191.252.83.183/arq.zip";
$username = "palm20@galle";
$password = "Jequitiba1539!";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_FTP_USE_EPSV, false);
$downloaded_file = curl_exec($ch);
curl_close($ch);

$file = 'arq.zip';
file_put_contents($file, $downloaded_file);
echo "OK\n";

echo "Extraindo dados de arq.zip ...";

// Usando ZipArchive para descompactar somente 'Preco.xml' e 'Produto.xml'
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $zip->extractTo('./', ['Preco.xml', 'Produto.xml']); // Extraindo apenas os arquivos desejados
    $zip->close();
    echo "OK\n";
} else {
    echo "Erro ao descompactar o arquivo\n";
}

if ((filesize('Preco.xml') <= 0) || (filesize('Produto.xml') <= 0)) {
    echo "Erro ao baixar arquivos do FTP Galle...\n";
    die;
}

use \Magento\Framework\App\Bootstrap;

$dir = '/home/u246762803/domains/crgr.com.br/public_html';

include_once($dir . '/app/Mage.php');

$conn = new mysqli('localhost', 'u246762803_user_salomao', '2025Ewdfh1k7', 'u246762803_banco_salomao');
$conn->query("SET NAMES 'utf8'");
$conn->query('SET character_set_connection=utf8');
$conn->query('SET character_set_client=utf8');
$conn->query('SET character_set_results=utf8');

if (file_exists('crgr_processeds.arr'))
    $processeds = file_get_contents('crgr_processeds.arr');
else
    $processeds = '';

if (strlen(trim($processeds)) <= 0)
    $processeds = [];
else
    $processeds = explode(',', $processeds);


// Ignorar SKUs específicos
$ignored_skus = ['05-004', '05-004AG', '05-007', '05-007AG', '05-558', '05-558AG'];


$file = fopen('Preco.xml', 'r');
$lines = [];
$start = false;
$lines_aux = [];
$sku = '';
$key = '';

while (!feof($file)) {
    $line = trim(fgets($file));
    if ($line == '<Row>') {
        $lines_aux = [];
        $start = true;
        $sku = '';
        $key = '';
        continue;
    } elseif ($line == '</Row>') {
        if (isset($lines[$key]) === false)
            $lines[$key] = [];

        $lines[$key][$sku] = $lines_aux[$key];
        $lines_aux = [];
        $start = false;
        continue;
    }

    if ($start === true) {
        $tmp = explode('<', $line, 2);
        $tmp = explode('>', $tmp[1], 2);
        $field_name = $tmp[0];

        $tmp = explode('<', $tmp[1], 2);
        $field_value = $tmp[0];

        if ($field_name == 'ProdutoID_Int')
            $sku = $field_value;
        if ($field_name == 'TabelaID_Int')
            $key = $field_value;

        if ((strlen(trim($key)) > 0) && (isset($lines_aux[$key]) === false)) {
            $lines_aux[$key] = [];
        }
        if (isset($lines_aux[$key]) === true) {
            $lines_aux[$key][$field_name] = $field_value;
        }
    }
}
fclose($file);

$file = fopen('Produto.xml', 'r');
$start = false;
$lines_aux = [];
$sku = '';

$tmp_keys = array_keys($lines);

foreach ($tmp_keys as $key) {
    fseek($file, 0);
    while (!feof($file)) {
        $line = trim(fgets($file));

        if ($line == '<Row>') {
            $lines_aux = [];
            $start = true;
            $sku = '';
            continue;
        } elseif ($line == '</Row>') {

            if (isset($lines[$key][$sku]) === true) {
                $lines[$key][$sku] = array_merge($lines[$key][$sku], $lines_aux[$key]);
            }

            $lines_aux = [];
            $start = false;

            continue;
        }

        if ($start === true) {
            $tmp = explode('<', $line, 2);
            $tmp = explode('>', $tmp[1], 2);
            $field_name = $tmp[0];

            $tmp = explode('<', $tmp[1], 2);
            $field_value = $tmp[0];

            if ($field_name == 'ProdutoID_Int')
                $sku = $field_value;
            if ($field_name == 'TabelaID_Int')
                $key = $field_value;

            if ((strlen(trim($key)) > 0) && (isset($lines_aux[$key]) === false)) {
                $lines_aux[$key] = [];
            }
            if (isset($lines_aux[$key]) === true) {
                $lines_aux[$key][$field_name] = $field_value;
            }

        }
    }
}
fclose($file);

$tables = [];

if (isset($lines['666']) === false)
    $lines['666'] = [];// TABELA 2025/001 PRATA - O
if (isset($lines['667']) === false)
    $lines['667'] = [];// TABELA 2025/002 PRATA - R
if (isset($lines['668']) === false)
    $lines['668'] = [];// TABELA 2025/003 PRATA - RC

if (isset($lines['682']) === false)
    $lines['682'] = [];// TABELA 2025/017 FOLHEADO

if (isset($lines['683']) === false)
    $lines['683'] = [];// TABELA 2025/018 FOLHEADO(braRC)

$tables['4'] = array_merge($lines['666'], $lines['682']);
$tables['6'] = array_merge($lines['666'], $lines['683']);
$tables['8'] = array_merge($lines['667'], $lines['682']);
$tables['10'] = array_merge($lines['667'], $lines['683']);
$tables['12'] = array_merge($lines['668'], $lines['682']);
$tables['14'] = array_merge($lines['668'], $lines['683']);
$tables['16'] = array_merge($lines['668'], $lines['683']);

$magento_refs = [];
$magento_refs['4'] = 5;   // ID do magento 2025/001(x4) + 2025/017(x4) and 2025/001(x4) + 2025/017(x5)
$magento_refs['6'] = 7;   // ID do magento 2025/001(x4) + 2025/018(x4) and 2025/001(x4) + 2025/018(x5)
$magento_refs['8'] = 9;   // ID do magento 2025/002(x4) + 2025/017(x4) and 2025/002(x4) + 2025/017(x5)
$magento_refs['10'] = 11; // ID do magento 2025/002(x4) + 2025/018(x4) and 2025/002(x4) + 2025/018(x5)
$magento_refs['12'] = 13; // ID do magento 2025/003(x4) + 2025/017(x4) and 2025/003(x4) + 2025/017(x5)
$magento_refs['14'] = 15; // ID do magento 2025/003(x4) + 2025/018(x4) and 2025/003(x4) + 2025/018(x5)
$magento_refs['16'] = 17; // ID do magento 2025/003(x5) + 2025/018(x4) and 2025/003(x5) + 2025/018(x5)


$products = [];
foreach ($tables as $key_tables => $value_tables) {
    foreach ($value_tables as $key => $values) {
        if (isset($products[$key]) === false)
            $products[$key] = $values;
        if (isset($products[$key]['groups']) === false)
            $products[$key]['groups'] = [];

        array_push($products[$key]['groups'], [$key_tables, $values['Preco']]);
        array_push($products[$key]['groups'], [$magento_refs[$key_tables], $values['Preco']]);
    }
}

// file_put_contents('lixo.lxo',var_export($products,true));

foreach ($products as $sku => $value) {
    // Verificação para ignorar SKUs específicos
    if (in_array($sku, $ignored_skus)) {
        echo 'SKU: ' . $sku . ' - Produto ignorado (está na lista de SKUs ignorados).' . "\n\n";
        continue; // Pula para o próximo produto
    }

    // Verificação para SKU dentro do XML (ProdutoID_Int)
    if (in_array($value['ProdutoID_Int'], $ignored_skus)) {
        echo 'SKU: ' . $value['ProdutoID_Int'] . ' - Produto ignorado (está na lista de SKUs ignorados).' . "\n\n";
        continue; // Pula para o próximo produto
    }

    // Verificação de Ativo
    if (isset($value['Ativo']) && (strtolower($value['Ativo']) == 'false' || $value['Ativo'] === false)) {
        echo 'SKU: ' . $sku . ' - Produto inativo - Ignorando' . "\n\n";
        continue; // Ignora produtos com "Ativo" = false
    }

    // Outras verificações existentes
    if (in_array($sku, $processeds)) {
        echo 'SKU: ' . $sku . ' - Processado antes - ' . $value['Descricao'] . ' - ' . $value['TipoID_Int'] . ' - ' . $value['Largura_MM'] . 'mm x ' . $value['Altura_MM'] . 'mm - ' . $value['Peso'] . 'gr' . "\n\n";
        continue;
    }

    // Ignorando os SKUs que contem BR no final do código
    if (strpos($sku, 'BR') !== false) {
        echo 'SKU: ' . $sku . ' - ignorando o produto' . "\n\n";
        continue;
    }


    // if (strpos($sku, '01-5453-1') === false)
    //     continue; // Somente para testes


    $name = $value['Descricao'] . ' - ' . $value['TipoID_Int'] . ' - ' . $value['Largura_MM'] . 'mm x ' . $value['Altura_MM'] . 'mm - ' . $value['Peso'] . 'gr';
    $image = 'http://app.galle.com.br/images/grandes/' . $value['ImagemID'] . '.jpg';
    $image_name = strtolower($value['ImagemID']) . '.jpg';
    $relative_path = '/' . $image_name[0] . '/' . $image_name[1] . '/' . $image_name;
    $imagePath = $dir . "/media/catalog/product" . $relative_path;

    $weight = floatval($value['Peso']);
    $original_price = floatval($value['Preco']);

    $ver = explode('-', $sku)[0];
    $tipo_produto = (strpos($sku, 'AG') !== false) ? 'AG' : 'OURO';

    if ($value['PrecoGrama'] === '1')
        $weight_value = $weight;
    else
        $weight_value = 1;


    // Processamento do produto
    echo "Iniciando SKU do Produto: $sku \n\n";
    Mage::app('admin');
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    $get_product_id = Mage::getModel("catalog/product")->getIdBySku($sku);
    var_dump($get_product_id);
    $set_product = Mage::getModel("catalog/product")->load($get_product_id);

    if (!$get_product_id || !$set_product->getId()) {
        $set_product = Mage::getModel('catalog/product');
        echo "Inserindo novo produto\n";
    } else {
        echo "Atualizando produto: " . $set_product->getName() . "\n";
    }

    // Baixar e salvar imagem
    try {
        $image_content = file_get_contents($image);

        if (strlen(trim($image_content)) > 0) {
            if (!file_exists(dirname($imagePath))) {
                mkdir(dirname($imagePath), 0777, true);
            }

            if (!file_exists($imagePath)) {
                file_put_contents($imagePath, $image_content);
                echo "Imagem salva: $imagePath\n";
                $image_exist = false;
            } else {
                echo "Imagem já existe: $imagePath\n";
                $image_exist = true;
            }
        } else {
            echo "Imagem vazia: $image\n";
            $image_exist = false;
        }
    } catch (Exception $e) {
        echo "Erro ao baixar imagem: " . $e->getMessage() . "\n";
        $image_exist = false;
    }

// 🚀 INÍCIO REGRAS DE CATEGORIAS //

// Default Category ID: 2 é obrigatória para todos
$categoryIds = [2];

// Verifica se é lançamento e adiciona a categoria 41
if (isset($value['Lancamento']) && $value['Lancamento'] == '1') {
    $categoryIds[] = 4; // Categoria 4
    // Se for MaterialID 0 (Folheado a Ouro), adiciona a categoria 12
    if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 0) {
        $categoryIds[] = 12; // Categoria 12
    }
    // Se for MaterialID 2 (Prata Pura), adiciona a categoria 13
    if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 2) {
        $categoryIds[] = 13; // Categoria 13
    }
}

// 🚀 Categorias para o material Folheado a Ouro (MaterialID 0)
if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 0) {
    // Categoria 5 (Folheado a Ouro)
    $categoryIds[] = 5;

    // Se for do grupo 03, adiciona a categoria 14
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '03') {
        $categoryIds[] = 14; // Categoria 14
    }

    // Se tiver no descrição "Bracelete", adiciona a categoria 15
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Bracelete') !== false) {
        $categoryIds[] = 15; // Categoria 15
    }

    // Se for do grupo 02, adiciona a categoria 16
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '02') {
        $categoryIds[] = 16; // Categoria 16
    }

    // Se tiver no descrição "Choker", adiciona a categoria 17
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Choker') !== false) {
        $categoryIds[] = 17; // Categoria 17
    }

        // Se for do grupo 04, adiciona a categoria 40 
        if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '04') {
            // Verifica se a descrição contém "Pulseira", se sim, ignora a categoria
            if (isset($value['Descricao']) && strpos($value['Descricao'], 'Pulseira') !== false) {
                echo 'SKU: ' . $value['ProdutoID_Int'] . ' - Ignorando categoria 40 (Produto é uma Pulseira).' . "\n\n";
                continue; // Pula para o próximo produto, ignorando a adição da categoria
                }
        
        $categoryIds[] = 40; // Categoria 40
        
        // Adiciona categorias de tamanho baseado em TamanhoID
        if (isset($value['TamanhoID_Int'])) {
            switch ($value['TamanhoID_Int']) {
                case '4P': $categoryIds[] = 107; break;
                case '4F': $categoryIds[] = 108; break;
                case '4G': $categoryIds[] = 109; break;
                case '4H': $categoryIds[] = 110; break;
                case '4I': $categoryIds[] = 111; break;
                case '4T': $categoryIds[] = 112; break;
            }
        }
    }


    // Se tiver no descrição "Escapulario", adiciona a categoria 113
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Escapulario') !== false) {
        $categoryIds[] = 113; // Categoria 113
    }

    // Se tiver no descrição "Gargantilha", adiciona a categoria 114
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Gargantilha') !== false) {
        $categoryIds[] = 114; // Categoria 114
    }

    // Se tiver no descrição "Piercing", adiciona a categoria 115
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Piercing') !== false) {
        $categoryIds[] = 115; // Categoria 115
    }


    // Se for do grupo 01, adiciona a categoria 133
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '01') {
        $categoryIds[] = 133; // Categoria 133
    }



    // Se tiver no descrição "Pulseira", adiciona a categoria 116
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Pulseira') !== false) {
        $categoryIds[] = 116; // Categoria 116
        // Adiciona categorias de tamanho baseado em TamanhoID
        if (isset($value['TamanhoID_Int'])) {
            switch ($value['TamanhoID_Int']) {
                case '4A': $categoryIds[] = 117; break;
                case '4B': $categoryIds[] = 118; break;
                case '4C': $categoryIds[] = 119; break;
                case '4J': $categoryIds[] = 120; break;
                case '4Q': $categoryIds[] = 121; break;
            }
        }
    }
    

    // Se tiver no descrição "Tornozeleira", adiciona a categoria 122
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Tornozeleira') !== false) {
        $categoryIds[] = 122; // Categoria 122
    }
}

// 🚀 Categorias para o material Prata Pura (MaterialID 2)
if (isset($value['MaterialID_Int']) && $value['MaterialID_Int'] == 2) {
    // Categoria 6 (Prata Pura)
    $categoryIds[] = 6;

    // Se for do grupo 03, adiciona a categoria 18
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '03') {
        $categoryIds[] = 18; // Categoria 18
    }

    // Se tiver no descrição "Berloque", adiciona a categoria 19
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Berloque') !== false) {
        $categoryIds[] = 19; // Categoria 19
    }

    // Se tiver no descrição "Bracelete", adiciona a categoria 20
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Bracelete') !== false) {
        $categoryIds[] = 20; // Categoria 20
    }

    // Se for do grupo 02, adiciona a categoria 21
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '02') {
        $categoryIds[] = 21; // Categoria 21
    }

    // Se tiver no descrição "Choker", adiciona a categoria 125
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Choker') !== false) {
        $categoryIds[] = 125; // Categoria 125
    }

    // Se tiver no descrição "Escapulario", adiciona a categoria 126
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Escapulario') !== false) {
        $categoryIds[] = 126; // Categoria 126
    }

    // Se tiver no descrição "Gargantilha", adiciona a categoria 127
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Gargantilha') !== false) {
        $categoryIds[] = 124; // Categoria 127
    }

    // Se tiver no descrição "Piercing", adiciona a categoria 128
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Piercing') !== false) {
        $categoryIds[] = 128; // Categoria 128
    }

    // Se for do grupo 01, adiciona a categoria 130
    if (isset($value['GrupoID_Int']) && $value['GrupoID_Int'] == '01') {
        $categoryIds[] = 130; // Categoria 130
    }

    // Se tiver no descrição "Pulseira", adiciona a categoria 129
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Pulseira') !== false) {
        $categoryIds[] = 129; // Categoria 129
    }

    // Se tiver no descrição "Terco", adiciona a categoria 131
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Terco') !== false) {
        $categoryIds[] = 131; // Categoria 131
    }

    // Se tiver no descrição "Tornozeleira", adiciona a categoria 132
    if (isset($value['Descricao']) && strpos($value['Descricao'], 'Tornozeleira') !== false) {
        $categoryIds[] = 132; // Categoria 132
    }
}

// Associa as categorias ao produto para o Magento 1.9
if ($set_product && $set_product instanceof Mage_Catalog_Model_Product) {
    $set_product->setCategoryIds($categoryIds);
} else {
    echo "Erro: Produto não é um objeto válido.\n";
    continue;
}

// 🚀 FIM REGRAS DE CATEGORIAS //


    try {
        $urlkey = slug($name . ' ' . $sku);
        $today_date = date("d/m/Y");
        $new_date = date('d/m/Y', strtotime("+15 day"));

        echo "Table: " . $key_tables . "/" . $magento_refs[$key_tables] . " - Sku: " . $sku . " - Name: " . $name . " - Price: " . $price_x4 . " - Peso: " . $weight . " - Orginal Price: " . $original_price . " - Price_X5: " . $price_x5 . "\n";


        $set_product->setWebsiteIds([1])
            ->setStoreId(0)
            ->setAttributeSetId(4)
            ->setTypeId('simple')
            ->setCreatedAt(strtotime('now'))
            ->setNewsFromDate($today_date)
            ->setNewsToDate($new_date)
            ->setVisibility(4)
            ->setManufacturer(28)
            ->setSku($sku)
            ->setTaxClassId(0)
            ->setCountryOfManufacture('BR')
            ->setStatus(1)
            ->setName($name)
            ->setDescription($name)
            ->setShort_description($name)
            ->setWeight((string) $weight)
            ->setUrlKey($urlkey)
            ->setStockData([
                'use_config_manage_stock' => 0,
                'use_config_min_qty' => 1,
                'use_config_min_sale_qty' => 1,
                'use_config_max_sale_qty' => 1,
                'use_config_backorders' => 1,
                'use_config_notify_stock_qty' => 1,
                'manage_stock' => 1,
                'min_sale_qty' => 0,
                'min_qty' => 0,
                'max_sale_qty' => 100000,
                'is_qty_decimal' => 0,
                'backorders' => 0,
                'notify_stock_qty' => 5000,
                'is_in_stock' => '1',
                'qty' => 888,
            ]
        );

        // Preços por grupo
        $code_x4 = (String) $key_tables;
        $code_x5 = (String) $magento_refs[$key_tables];

        $groupPrice = [];
        $hivalue = 0;
        foreach ($value['groups'] as $keygr => $valuegr) {


            $price_x4 = floatval($valuegr[1]) * 4 * $weight_value;
            $price_x5 = floatval($valuegr[1]) * 5 * $weight_value;

            if ($hivalue < $price_x4)
                $hivalue = $price_x4;
            if ($hivalue < $price_x5)
                $hivalue = $price_x5;

            if ((in_array($valuegr[0], $magento_refs) === true) && ($tipo_produto != 'AG')) {
                $pricegr = $price_x5;
            } else {
                $pricegr = $price_x4;
            }

            $groupPrice[] = [
                'website_id' => Mage::getModel('core/store')->load($price_data['store_id'])->getWebsiteId(),
                'cust_group' => $valuegr[0],
                'price' => floatVal($pricegr),
                "all_groups" => false
            ];
        }
        $set_product->setData('group_price', $groupPrice);

        $set_product->setPrice($hivalue);
        $set_product->setCost(null);
        $set_product->setSpecialPrice($hivalue);

        // Imagem
        if (!$image_exist && file_exists($imagePath)) {
            $set_product->addImageToMediaGallery($imagePath, ['image', 'small_image', 'thumbnail'], false, false);
        }

        $set_product->save();

        file_put_contents('crgr_processeds.arr', ',' . $sku, FILE_APPEND);
        echo "Produto salvo com sucesso.\n\n";

    } catch (Exception $e) {
        echo "Erro ao salvar produto SKU $sku: " . $e->getMessage() . "\n\n";
    }
}
if (file_exists('crgr_processeds.arr'))
    unlink('crgr_processeds.arr');

echo "\nProcesso finalizado.\n";
echo "👉 Recomendações pós-execução:\n";
echo "- Limpar cache: rm -rf var/cache/*\n";
echo "- Reindexar: php shell/indexer.php reindexall\n\n";


function slug($string)
{
    $string = strtr(mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string)), 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    $string = strip_tags($string);
    $string = preg_replace('/[^A-Za-z0-9-]+/', ' ', $string);
    $string = trim($string);
    $string = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    $slug = strtolower($string);
    return $slug;
}