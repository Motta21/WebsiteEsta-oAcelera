<?php
// 1. INCLUSÃO DA CONEXÃO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'php/db_conection.php';

//FILTROS DO USUÁRIO para A Data
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;
$topico      = $_GET['topico'] ?? '';

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

// DATA FINAL
if (!empty($data_fim)) {
    $sql .= " AND DataHora <= ?";
    $params[] = $data_fim . " 23:59:59";
}

// TÓPICO
$colunas_validas = [
    'Temperatura', 'Umidade', 'Pressao', 'Pressao_nivel_mar', 'PTO_Orvalho','NV_Bat'
];
if (!empty($topico) && in_array($topico, $colunas_validas)) {
    $sql .= " AND $topico IS NOT NULL";
}

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
  <title>WeatherMonitor — Histórico</title>
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
      <a class="nav-link active" href="historico.html"><i class="fa-solid fa-clock-rotate-left"></i><span>Histórico</span></a>
      <a class="nav-link" href="contato.html"><i class="fa-solid fa-address-book"></i><span>Contato</span></a>
      <a class="nav-link" href="patrocinadores.html"><i class="fa-solid fa-handshake"></i><span>Patrocinadores</span></a>
    </nav>
    <div class="sidebar-footer">
      <a class="nav-link small" href="https://thingspeak.com/" target="_blank" rel="noopener">
        <i class="fa-brands fa-think-peaks"></i><span>ThingSpeak</span>
      </a>
    </div>
  </aside>

<main class="content">

<header class="topbar">
    <div class="topbar-left">
        <h1>Estação Meteorológica</h1>
        <p class="subtitle">Histórico de leituras meteorológicas</p>
    </div>

    <div class="topbar-right">
      <button class="btn-icon" id="userLogin" onclick="window.location.href='login.html'">
      <i class="fa-solid fa-user"></i>
    </button>
        <button class="btn-icon" id="toggleTheme"><i class="fa-solid fa-moon"></i></button>
        <div class="clock" id="hora">--:--:--</div>
    </div>
</header>

<section id="historico" class="section">

    <div class="section-head">
        <h2><i class="fa-solid fa-clock"></i> Histórico de Leituras</h2>
    </div>

    <form method="GET" class="filtro-box">

        <div>
            <label>Data inicial:</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
        </div>

        <div>
            <label>Data final:</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
        </div>

        <div>
            <label>Tópico:</label>
            <select name="topico">
                <option value="">Todos</option>
                <?php foreach ($colunas_validas as $col): ?>
                    <option value="<?= $col ?>" <?= $topico == $col ? 'selected' : '' ?>>
                        <?= $col ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn">Filtrar</button>

        <a href="php/download.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
            <i class="fa-solid fa-download"></i> Download CSV
        </a>

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