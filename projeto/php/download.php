<?php
require_once 'php/db_conection.php';

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=historico_estacao.csv");

$out = fopen("php://output", "w");

// Cabeçalho do CSV
fputcsv($out, [
    "data_hora",
    "temperatura",
    "umidade",
    "pressao",
    "pressao_nivel_mar",
    "ponto_orvalho"
]);

$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;
$topico      = $_GET['topico'] ?? '';

$sql = "SELECT * FROM view_estacao WHERE 1=1";
$params = [];

if (!empty($data_inicio)) {
    $sql .= " AND data_hora >= ?";
    $params[] = $data_inicio . " 00:00:00";
}
if (!empty($data_fim)) {
    $sql .= " AND data_hora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$colunas_validas = [
    'temperatura', 'umidade', 'pressao', 'pressao_nivel_mar', 'ponto_orvalho'
];
if (!empty($topico) && in_array($topico, $colunas_validas)) {
    $sql .= " AND $topico IS NOT NULL";
}

$sql .= " ORDER BY data_hora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}

fclose($out);
exit;
