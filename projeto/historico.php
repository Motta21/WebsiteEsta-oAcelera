<?php
// 1. INCLUSÃO DA CONEXÃO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'php/db_conection.php';

//FILTROS DO USUÁRIO para A Data
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;

//Consulta
$view_dados = "view_estacao";

$sql = "SELECT * FROM $view_dados WHERE 1=1";
$params = [];
// fim consulta

// DATA INICIAL
if (!empty($data_inicio)) {
    $sql .= " AND DataHora >= ?";
    $params[] = $data_inicio . " 00:00:00";
}

// DATA Fim
if (!empty($data_fim)) {
    $sql .= " AND DataHora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

if (isset($_GET['cod_e'])) {
    $cod_e = $_GET['cod_e']; 
} else {
    $cod_e = 1; // Padrão Estação 1
}

$sql .= " AND Cod_E = ?";
$params[] = $cod_e;

$sql .= " ORDER BY DataHora DESC LIMIT 50";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $erro_consulta = "Erro ao consultar a View: " . $e->getMessage();
    $registros = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Amaná — Histórico</title>
  <script>
    (function() {
      const saved = sessionStorage.getItem('theme');
      const html = document.documentElement;
      html.setAttribute('data-theme', saved || 'light');
    })();
  </script>
  
  <link rel="stylesheet" href="estilo.css">
  <link rel="stylesheet" href="historico.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="shortcut icon" href="/img/favicon_io/favicon.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <aside class="sidebar collapsed" id="sidebar">
    <div class="brand">
      <button class="btn-icon toggle-sidebar" id="toggleSidebar"><i class="fa-solid fa-bars"></i></button>
    </div>
    
    <nav class="nav">
      <a class="nav-link" href="../index.html"><i class="fa-solid fa-cloud-sun-rain"></i><span>Início</span></a>
      <a class="nav-link" href="dashboard.html"><i class="fa-solid fa-gauge"></i><span>Dashboard</span></a>
      <a class="nav-link" href="graficos.html"><i class="fa-solid fa-chart-line"></i><span>Gráficos</span></a>
      <a class="nav-link active" href="historico.php"><i class="fa-solid fa-clock-rotate-left"></i><span>Histórico</span></a>
      <a class="nav-link" href="contato.html"><i class="fa-solid fa-address-book"></i><span>Contato</span></a>
      <a class="nav-link" href="patrocinadores.html"><i class="fa-solid fa-handshake"></i><span>Parceiros</span></a>
      <a class="nav-link" href="extensao.html"><i class="fa-solid fa-video"></i><span>Extensão</span></a>
      <a class="nav-link" href="login.html"><i class="fa-solid fa-user"></i><span>Perfil</span></a>

        </nav>
    
  </aside>

<main class="content">

<header class="topbar">
    <div class="topbar-left">
        
    </div>

    <div class="topbar-right">
     
    </button>
        <button class="btn-icon" id="toggleTheme"><i class="fa-solid fa-moon"></i></button>
        <div class="clock" id="hora">--:--:--</div>
    </div>
</header>
<h1>Central de Coleta de Dados Ambientais</h1>
        <p class="subtitle">Histórico de leituras meteorológicas</p>
<section id="historico" class="section">

    <div class="section-head">
        <h2><i class="fa-solid fa-clock"></i> Histórico de Leituras</h2>
    </div>

    <form method="GET" class="filter-bar">

    <div class="filter-group">

        <div class="filter-item">
            <input type="date" name="data_inicio" class="input-date" value="<?= htmlspecialchars($data_inicio) ?>">
        </div>

        <div class="filter-item">
            <input type="date" name="data_fim" class="input-date" value="<?= htmlspecialchars($data_fim) ?>">
        </div>

        <select name="cod_e" id="estacao" class="input-select">
        <option value="1" <?= ($cod_e == 1 ? 'selected' : '') ?>>Estação 1</option>
        <option value="2" <?= ($cod_e == 2 ? 'selected' : '') ?>>Estação 2</option>
    </select>


        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>

        <a href="php/download.php?data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>&cod_e=<?= urlencode($cod_e) ?>"
           class="btn-download">
            <i class="fa-solid fa-download"></i> CSV
        </a>

    </div>

</form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Temperatura (°C)</th>
                    <th>Umidade (%)</th>
                    <th>Pressão (hPa)</th>
                    <th>Pressão Nível do Mar (hPa)</th>
                    <th>Ponto Orvalho (°C)</th>
                   <!-- <th>Voltagem Bateria (V)</th> comentei pq ta dando erro, vou rrumar dps -->
                </tr>
            </thead>

            <tbody>
            <?php if (isset($erro_consulta)): ?>
                <tr><td colspan="7" style="color:red;text-align:center;"><?= $erro_consulta ?></td></tr>

            <?php elseif (count($registros) == 0): ?>
                <tr><td colspan="7" style="text-align:center;">Nenhum registro encontrado.</td></tr>

            <?php else: ?>
                <?php foreach ($registros as $linha): ?>
                    <tr>
                        <td><?= htmlspecialchars($linha['DataHora']) ?></td>
                        <td><?= htmlspecialchars($linha['Temperatura']) ?></td>
                        <td><?= htmlspecialchars($linha['Umidade']) ?></td>
                        <td><?= htmlspecialchars($linha['Pressao']) ?></td>
                        <td><?= htmlspecialchars($linha['Pressao_nivel_mar']) ?></td>
                        <td><?= htmlspecialchars($linha['PTO_Orvalho']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<footer class="footer">
    <span>&copy; 2025 Amana — Central de Coleta de Dados Ambientais</span>
</footer>

</main>

<script src="script.js"></script>
</body>
</html>