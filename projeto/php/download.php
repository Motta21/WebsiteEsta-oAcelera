<?php
require_once 'php/db_conection.php';

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=historico_estacao.csv");

$out = fopen("php://output", "w");

//colunas 
$colunas_validas = [
    "Temperatura"         => "temperatura",
    "Umidade"             => "umidade",
    "Pressao"             => "pressao",
    "Pressao_nivel_mar"   => "pressao_nivel_mar",
    "PTO_Orvalho"         => "ponto_orvalho",
    "NV_Bat"              => "voltagem_bateria" // ajuste nome REAL se diferente
];

//conteudo (parametros) du get
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;
$topico_raw  = $_GET['topico'] ?? "";



// transforma "A,B,C" em array
$topicos_selecionados = array_filter(array_map("trim", explode(",", $topico_raw)));

// valida apenas colunas existentes
$topicos_filtrados = [];

foreach ($topicos_selecionados as $tp) {
    if (isset($colunas_validas[$tp])) {
        $topicos_filtrados[$tp] = $colunas_validas[$tp];
    }
}

// Se não selecionar nada → usa TODAS as colunas
if (empty($topicos_filtrados)) {
    $topicos_filtrados = $colunas_validas;
}

// Sempre incluir data/hora no CSV
$colunas_csv = array_merge(
    ["data_hora"],
    array_values($topicos_filtrados)
);

   //HEADER DO CSV

fputcsv($out, $colunas_csv);


$sql_cols = "data_hora, " . implode(", ", array_values($topicos_filtrados));
$sql = "SELECT $sql_cols FROM view_estacao WHERE 1=1";
$params = [];

// FILTRO POR DATA
if (!empty($data_inicio)) {
    $sql .= " AND data_hora >= ?";
    $params[] = $data_inicio . " 00:00:00";
}
if (!empty($data_fim)) {
    $sql .= " AND data_hora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$sql .= " ORDER BY data_hora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);


while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}

fclose($out);
exit;