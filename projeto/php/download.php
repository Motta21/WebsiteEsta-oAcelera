<?php
require_once "db_conection.php";

$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;
$cod_e       = $_GET['cod_e'] ?? 1;

$sql = "SELECT * FROM view_estacao WHERE Cod_E = ?";
$params = [$cod_e];

if (!empty($data_inicio)) {
    $sql .= " AND DataHora >= ?";
    $params[] = $data_inicio . " 00:00:00";
}

if (!empty($data_fim)) {
    $sql .= " AND DataHora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$sql .= " ORDER BY DataHora ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nomeArquivo = "dados_estacao_{$cod_e}.csv";

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename={$nomeArquivo}");

$output = fopen("php://output", "w");

// Cabeçalho
fputcsv($output, ["DataHora", "Temperatura", "Umidade", "Pressao", "Pressao_nivel_mar", "PTO_Orvalho"]);

// Dados
foreach ($dados as $row) {
    fputcsv($output, [
        $row["DataHora"],
        $row["Temperatura"],
        $row["Umidade"],
        $row["Pressao"],
        $row["Pressao_nivel_mar"],
        $row["PTO_Orvalho"]
    ]);
}

fclose($output);
exit;
