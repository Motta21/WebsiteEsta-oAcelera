<?php
require_once 'php/db_conection.php';

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=historico_estacao.csv");

$out = fopen("php://output", "w");

// Todas as colunas válidas
$colunas = [
    "DataHora",
    "Temperatura",
    "Umidade",
    "Pressao",
    "Pressao_nivel_mar",
    "PTO_Orvalho",
    "NV_Bat"
];

// Cabeçalho do CSV
fputcsv($out, $colunas);

// Parâmetros
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;

$sql = "SELECT " . implode(", ", $colunas) . " FROM Dados WHERE 1=1";
$params = [];

// FILTRO POR DATA
if (!empty($data_inicio)) {
    $sql .= " AND DataHora >= ?";
    $params[] = $data_inicio . " 00:00:00";
}

if (!empty($data_fim)) {
    $sql .= " AND DataHora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$sql .= " ORDER BY DataHora ASC";

// Executar
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Escrever no CSV
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputc
