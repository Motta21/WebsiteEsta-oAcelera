<?php
header("Content-Type: application/json");

$HOST = "localhost";
$USER = "root";
$PASS = "";
$DB = "estacao";

$conn = new mysqli($HOST, $USER, $PASS, $DB);

if ($conn->connect_error) {
    echo json_encode(["error" => "Falha ao conectar"]);
    exit;
}

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
        data_hora,
        temperatura,
        umidade,
        pressao,
        pressao_nm,
        orvalho
    FROM leituras
    WHERE data_hora >= NOW() - INTERVAL $intervalo
    ORDER BY data_hora ASC
";

$res = $conn->query($sql);

$labels = [];
$temperatura = [];
$umidade = [];
$pressao = [];
$pressao_nm = [];
$orvalho = [];

while ($row = $res->fetch_assoc()) {
    $labels[] = $row["data_hora"];
    $temperatura[] = (float)$row["temperatura"];
    $umidade[] = (float)$row["umidade"];
    $pressao[] = (float)$row["pressao"];
    $pressao_nm[] = (float)$row["pressao_nm"];
    $orvalho[] = (float)$row["orvalho"];
}

echo json_encode([
    "labels" => $labels,
    "temperatura" => $temperatura,
    "umidade" => $umidade,
    "pressao" => $pressao,
    "pressao_nm" => $pressao_nm,
    "orvalho" => $orvalho
]);
