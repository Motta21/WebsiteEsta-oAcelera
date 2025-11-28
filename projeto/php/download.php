<?php
// download.php
// Versão robusta para debugar e gerar CSV
// NÃO colocar nada (nem espaços) antes do <?php

// Mostra erros (apenas para desenvolvimento). Remova em produção.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// arquivo de log temporário (inspecione se ainda der 500)
$logfile = '/tmp/download_error.log';

function log_msg($msg) {
    global $logfile;
    $line = "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    file_put_contents($logfile, $line, FILE_APPEND);
}

try {
    // include de conexão (verifique se esse arquivo NÃO emite saída)
    require_once 'db_conection.php';

    // Se houver buffer aberto, limpa (evita headers already sent)
    while (ob_get_level() > 0) { ob_end_clean(); }

    // Pega filtros
    $data_inicio = $_GET['data_inicio'] ?? null;
    $data_fim    = $_GET['data_fim'] ?? null;
    $topico      = $_GET['topico'] ?? '';

    // Monta SQL (ajuste o nome da view se necessário)
    $view = 'view_estacao';
    $sql = "SELECT * FROM $view WHERE 1=1";
    $params = [];

    if (!empty($data_inicio)) {
        $sql .= " AND DataHora >= ?";
        $params[] = $data_inicio . " 00:00:00";
    }
    if (!empty($data_fim)) {
        $sql .= " AND DataHora <= ?";
        $params[] = $data_fim . " 23:59:59";
    }

    // Colunas que nos interessam (vários formatos possíveis)
    $wanted_cols_variants = [
        'data_hora' => ['DataHora','data_hora','data_hora','datahora'],
        'temperatura' => ['Temperatura','temperatura','temp','Temp'],
        'umidade' => ['Umidade','umidade','humidade'],
        'pressao' => ['Pressao','pressao','pressão','pressao_atm'],
        'pressao_nivel_mar' => ['Pressao_nivel_mar','pressao_nivel_mar','pressaoNivelMar','pressao_nivel_do_mar'],
        'ponto_orvalho' => ['PTO_Orvalho','PTO_Orvalho','PontoOrvalho','ponto_orvalho','pto_orvalho'],
        'nv_bat' => ['NV_Bat','NV_Bat','NV_BAT','nv_bat','NVBat']
    ];

    // Se um tópico foi pedido, filtra por não-nulo naquela coluna (tenta mapear)
    if (!empty($topico)) {
        // Tópico recebido pode ser "Temperatura" etc - aceitamos
        // adicionamos um WHERE de existência genérico; caso não exista a coluna o DB pode falhar -> try/catch
        $sql .= " AND $topico IS NOT NULL";
    }

    $sql .= " ORDER BY DataHora ASC";

    // Prepara e executa
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Abre stream de saída
    // Define headers só agora (evita problema de output prévio)
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=historico_estacao.csv");
    // Opcional: força download sem cache
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");

    $out = fopen('php://output', 'w');
    if ($out === false) {
        throw new Exception("Não foi possível abrir php://output");
    }

    // Pega a primeira linha para descobrir as colunas retornadas
    $firstRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($firstRow === false) {
        // nenhuma linha: escreve cabeçalho padrão e sai
        $headers = ['DataHora','Temperatura','Umidade','Pressao','Pressao_Nivel_Mar','Ponto_Orvalho','NV_Bat'];
        fputcsv($out, $headers);
        fclose($out);
        exit;
    }

    // Descobre colunas disponíveis (case-insensitive)
    $availableCols = [];
    foreach ($firstRow as $k => $v) {
        $availableCols[strtolower($k)] = $k; // map lowercase -> real name
    }

    // Mapeia as colunas que queremos para os nomes reais presentes
    $mapped = []; // chave desejada => nome real na query
    foreach ($wanted_cols_variants as $key => $variants) {
        foreach ($variants as $candidate) {
            if (isset($availableCols[strtolower($candidate)])) {
                $mapped[$key] = $availableCols[strtolower($candidate)];
                break;
            }
        }
    }

    // Se não mapeou DataHora, tenta pegar qualquer coluna parecida
    if (!isset($mapped['data_hora'])) {
        foreach ($availableCols as $lc => $real) {
            if (strpos($lc, 'data') !== false) {
                $mapped['data_hora'] = $real;
                break;
            }
        }
    }

    // Cabeçalho que será escrito no CSV (use os nomes amigáveis)
    $csv_headers = [];
    $order_keys = ['data_hora','temperatura','umidade','pressao','pressao_nivel_mar','ponto_orvalho','nv_bat'];
    foreach ($order_keys as $k) {
        if (isset($mapped[$k])) {
            // Cabeçalho legível
            switch ($k) {
                case 'data_hora': $csv_headers[] = 'DataHora'; break;
                case 'temperatura': $csv_headers[] = 'Temperatura'; break;
                case 'umidade': $csv_headers[] = 'Umidade'; break;
                case 'pressao': $csv_headers[] = 'Pressao'; break;
                case 'pressao_nivel_mar': $csv_headers[] = 'Pressao_Nivel_Mar'; break;
                case 'ponto_orvalho': $csv_headers[] = 'Ponto_Orvalho'; break;
                case 'nv_bat': $csv_headers[] = 'NV_Bat'; break;
            }
        }
    }

    // Se por alguma razão não foi identificado nenhuma coluna, escreve todas as colunas retornadas
    if (empty($csv_headers)) {
        // escreve as chaves originais
        fputcsv($out, array_keys($firstRow));
        // escreve a primeira linha e segue
        fputcsv($out, array_values($firstRow));
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    // Escreve cabeçalho coerente
    fputcsv($out, $csv_headers);

    // Escreve a primeira linha usando o mapeamento
    $buildRow = [];
    foreach ($order_keys as $k) {
        if (isset($mapped[$k])) {
            $real = $mapped[$k];
            $buildRow[] = $firstRow[$real] ?? '';
        }
    }
    fputcsv($out, $buildRow);

    // Escreve o resto das linhas
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $buildRow = [];
        foreach ($order_keys as $k) {
            if (isset($mapped[$k])) {
                $real = $mapped[$k];
                $buildRow[] = $row[$real] ?? '';
            }
        }
        fputcsv($out, $buildRow);
    }

    fclose($out);
    exit;

} catch (Throwable $e) {
    // Log detalhado para debugging
    $msg = "EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
    log_msg($msg);
    // Se headers ainda não foram enviados, exibe texto para diagnóstico em dev
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
        echo "Ocorreu um erro ao gerar o CSV. Verifique o log em /tmp/download_error.log";
        echo PHP_EOL . $msg;
    }
    exit(1);
}
