<?php
require_once "db_conection.php";

$cod_e = $_GET['Cod_E'] ?? 1;

$sql = "SELECT * 
        FROM view_estacao 
        WHERE Cod_E = ?
        ORDER BY DataHora DESC 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$Cod_E]);

$dados = $stmt->fetch(PDO::FETCH_ASSOC);

header("Content-Type: application/json; charset=utf-8");
echo json_encode($dados ?: []);
