<?php
header("Content-Type: application/json");
require_once "db_conection.php";

$periodo = $_GET["periodo"] ?? "diario";

switch ($periodo) {
    case "diario":      $intervalo = "1 DAY"; break;
    case "semanal":     $intervalo = "7 DAY"; break;
    case "quinzenal":   $intervalo = "15 DAY"; break;
    default:            $intervalo = "1 DAY";
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

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "sucesso" => true,
    "dados" => $dados
]);
