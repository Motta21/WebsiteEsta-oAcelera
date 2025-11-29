<?php
header("Content-Type: application/json");
require_once "db_conection.php"; 

$periodo = $_GET["periodo"] ?? "diario";

switch ($periodo) {
    case "diario":
        $intervalo = "1 DAY";
        break;
    case "semanal":
        $intervalo = "7 DAY";
        break;
    case "quinzenal":
        $intervalo = "15 DAY";
        break;
    default:
        $intervalo = "1 DAY";
}

$sql = "
    SELECT 
        DataHora,
        Temperatura,
        Umidade,
        Pressao,
        Pressao_nivel_mar,
        PTO_Orvalho
    FROM view_estacao
    WHERE DataHora >= (NOW() - INTERVAL $intervalo)
    ORDER BY DataHora ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$labels = [];
$temperatura = [];
$umidade = [];
$pressao = [];
$pressao_nm = [];
$orvalho = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $labels[] = $row["DataHora"];
    $temperatura[] = floatval($row["Temperatura"]);
    $umidade[] = floatval($row["Umidade"]);
    $pressao[] = floatval($row["Pressao"]);
    $pressao_nm[] = floatval($row["Pressao_nivel_mar"]);
    $orvalho[] = floatval($row["PTO_Orvalho"]);
}

echo json_encode([
    "labels" => $labels,
    "temperatura" => $temperatura,
    "umidade" => $umidade,
    "pressao" => $pressao,
    "pressao_nm" => $pressao_nm,
    "orvalho" => $orvalho
]);
