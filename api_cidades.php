<?php
header('Content-Type: application/json; charset=utf-8');
$locais = require __DIR__ . '/data/brasil_locais.php';
$estados = [
  'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal',
  'ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais',
  'PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte',
  'RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins',
];

$q = trim($_GET['q'] ?? '');
$uf = strtoupper(trim($_GET['uf'] ?? ''));
$out = [];

if ($uf && isset($locais[$uf])) {
    $qq = mb_strtolower($q);
    foreach ($locais[$uf] as $cidade) {
        if ($q === '' || str_contains(mb_strtolower($cidade), $qq)) {
            $out[] = ['label' => $cidade . ', ' . $uf, 'cidade' => $cidade, 'uf' => $uf];
        }
        if (count($out) >= 12) break;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($q !== '') {
    $qq = mb_strtolower($q);
    // estados
    foreach ($estados as $sigla => $nome) {
        if (str_starts_with(mb_strtolower($nome), $qq) || str_starts_with(mb_strtolower($sigla), $qq)) {
            $out[] = ['type' => 'estado', 'label' => $nome . ' (' . $sigla . ')', 'uf' => $sigla, 'nome' => $nome];
        }
    }
    // cidades (qualquer estado)
    foreach ($locais as $sigla => $cidades) {
        foreach ($cidades as $cidade) {
            if (str_starts_with(mb_strtolower($cidade), $qq)) {
                $out[] = ['type' => 'cidade', 'label' => $cidade . ', ' . $sigla, 'cidade' => $cidade, 'uf' => $sigla];
            }
            if (count($out) >= 20) break 2;
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
