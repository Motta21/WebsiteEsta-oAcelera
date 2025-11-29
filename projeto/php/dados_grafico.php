<?php
require_once "php/db_conection.php";

$periodo = $_GET["periodo"] ?? "diario";
$topico  = $_GET["topico"] ?? "Temperatura";

$colunasValidas = [
    "Temperatura",
    "Umidade",
    "Pressao",
    "Pressao_nivel_mar",
    "PTO_Orvalho"
];

if (!in_array($topico, $colunasValidas)) {
    echo json_encode(["erro" => "Tópico inválido"]);
    exit;
}

switch ($periodo) {
    case "diario":
        $sql = "SELECT DATE_FORMAT(DataHora,'%H:00') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 1 DAY
                GROUP BY HOUR(DataHora)";
        break;

    case "semanal":
        $sql = "SELECT DATE_FORMAT(DataHora,'%d/%m') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 7 DAY
                GROUP BY DATE(DataHora)";
        break;

    case "quinzenal":
        $sql = "SELECT DATE_FORMAT(DataHora,'%d/%m') AS label, AVG($topico) AS valor
                FROM view_estacao
                WHERE DataHora >= NOW() - INTERVAL 15 DAY
                GROUP BY DATE(DataHora)";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute();

$labels = [];
$dados = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $labels[] = $row["label"];
    $dados[]  = round($row["valor"], 2);
}

echo json_encode([
    "labels" => $labels,
    "dados"  => $dados
]);
