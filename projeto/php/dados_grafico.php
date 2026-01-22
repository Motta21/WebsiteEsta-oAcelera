<?php
header("Content-Type: application/json");
require_once "db_conection.php";

$periodo = $_GET["periodo"] ?? "diario";

// --- ADICIONE ESTE BLOCO ---
// Se o front-end mandou o ID, usa ele. Se não, assume Estação 1.
$cod_e = isset($_GET['cod_e']) ? (int)$_GET['cod_e'] : 1;
// --------------------------

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
        PTO_Orvalho,
        Chuva,
        NV_Bat
    FROM view_estacao
    WHERE Cod_E = :cod_e
      AND DataHora >= (NOW() - INTERVAL $intervalo)
    ORDER BY DataHora ASC
";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(":cod_e", $cod_e, PDO::PARAM_INT); // Agora $cod_e existe!
$stmt->execute();

$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    "sucesso" => true,
    "dados" => $dados
]);
?>