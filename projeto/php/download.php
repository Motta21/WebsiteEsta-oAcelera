<?php
require_once 'php/db_conection.php';

// Mostra exceções do PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Colunas "desejadas" (nomes amigáveis / ou como você quer no CSV)
$wanted_cols = [
    "DataHora",
    "Temperatura",
    "Umidade",
    "Pressao",
    "Pressao_nivel_mar",
    "PTO_Orvalho",
    "voltagem_bateria"
];

try {
    // 1) Verifica se a view existe e pega uma linha para descobrir os nomes reais das colunas
    $sampleStmt = $pdo->query("SELECT * FROM view_estacao LIMIT 1");
    $sampleRow = $sampleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sampleRow) {
        // Se não há nenhuma linha, ainda assim podemos obter colunas via DESCRIBE (se suportado)
        // tenta DESCRIBE / INFORMATION_SCHEMA (fallback genérico)
        $colsInfo = $pdo->query("SELECT * FROM view_estacao LIMIT 0")->columnCount();
        // se chegar aqui sem colunas, lançamos erro
        throw new Exception("A view 'view_estacao' existe mas não retornou colunas nem linhas.");
    }

    // Colunas reais da view (array de nomes, case preserved)
    $available_cols = array_keys($sampleRow);

} catch (Exception $e) {
    // Se falhar ao acessar a view, mostra erro legível (não gera CSV)
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Erro ao acessar view_estacao: " . $e->getMessage();
    exit;
}

// Faz um mapa case-insensitive: lowercase => original
$avail_map = [];
foreach ($available_cols as $c) {
    $avail_map[strtolower($c)] = $c;
}

// Faz o match das colunas desejadas com as disponíveis (case-insensitive)
$selected_real_cols = []; // nomes reais a usar no SELECT (na ordem desejada)
$header_names = [];       // nomes que vamos escrever no CSV (legíveis)
$missing = [];

foreach ($wanted_cols as $w) {
    $lk = strtolower($w);
    if (isset($avail_map[$lk])) {
        $selected_real_cols[] = $avail_map[$lk];
        $header_names[] = $w; // mantém o rótulo "amigável"
    } else {
        $missing[] = $w;
    }
}

// Se não sobrar nenhuma coluna válida, aborta com mensagem
if (empty($selected_real_cols)) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Nenhuma das colunas solicitadas existe na view 'view_estacao'. Colunas disponíveis: " . implode(', ', $available_cols);
    exit;
}

// Usa o nome real da coluna de data (procura por algo parecido com data/hora)
$data_col = null;
foreach ($avail_map as $lk => $real) {
    if (strpos($lk, 'data') !== false && strpos($lk, 'hora') !== false) {
        $data_col = $real;
        break;
    }
}
// Se não encontrar, tenta nomes comuns
if (!$data_col) {
    if (isset($avail_map['datahora'])) $data_col = $avail_map['datahora'];
    elseif (isset($avail_map['data_hora'])) $data_col = $avail_map['data_hora'];
    elseif (isset($avail_map['datetime'])) $data_col = $avail_map['datetime'];
}

if (!$data_col) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Não foi possível identificar a coluna de data/hora na view. Colunas disponíveis: " . implode(', ', $available_cols);
    exit;
}

// Agora tudo validado: podemos enviar headers CSV
header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=historico_estacao.csv");

$out = fopen("php://output", "w");

// Monta o cabeçalho do CSV: prefira rótulos "amigáveis" (header_names)
// Garantimos que data/hora esteja primeiro no cabeçalho
$final_headers = $header_names;
// Se o data_col não está entre os wanted na primeira posição, coloca "DataHora" na frente
// (Se header_names já tem DataHora, nada muda.)
if (strtolower($final_headers[0]) !== 'datahora' && strtolower($final_headers[0]) !== strtolower($wanted_cols[0])) {
    // se DataHora está entre os header_names, move para o início
    $index = null;
    foreach ($final_headers as $i => $h) {
        if (strtolower($h) === 'datahora' || stripos($h, 'data') !== false && stripos($h, 'hora') !== false) {
            $index = $i;
            break;
        }
    }
    if ($index !== null) {
        $dh = $final_headers[$index];
        array_splice($final_headers, $index, 1);
        array_unshift($final_headers, $dh);
        // também reorder selected_real_cols para colocar data_col na frente
        $idxReal = array_search($data_col, $selected_real_cols);
        if ($idxReal !== false) {
            array_splice($selected_real_cols, $idxReal, 1);
            array_unshift($selected_real_cols, $data_col);
        }
    } else {
        // se DataHora nem estava entre os wanted, adiciona DataHora real na frente e no header
        array_unshift($final_headers, $data_col);
        array_unshift($selected_real_cols, $data_col);
    }
}

// escreve comentário no topo do CSV se houve colunas faltantes (linha com "# AVISO: ...")
if (!empty($missing)) {
    fputcsv($out, ["# AVISO: as seguintes colunas não existem nesta view e foram ignoradas: " . implode(', ', $missing)]);
}

// Escreve o cabeçalho (rótulos)
fputcsv($out, $final_headers);

// Monta SQL com backticks para segurança (identificadores)
$select_list = array_map(function($c){ return "`" . str_replace("`","``",$c) . "`"; }, $selected_real_cols);
$sql = "SELECT " . implode(", ", $select_list) . " FROM `view_estacao` WHERE 1=1";

$params = [];
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim    = $_GET['data_fim'] ?? null;

// FILTRO POR DATA usando a coluna real identificada
if (!empty($data_inicio)) {
    $sql .= " AND `$data_col` >= ?";
    $params[] = $data_inicio . " 00:00:00";
}
if (!empty($data_fim)) {
    $sql .= " AND `$data_col` <= ?";
    $params[] = $data_fim . " 23:59:59";
}

$sql .= " ORDER BY `$data_col` ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Escreve as linhas no CSV mantendo a ordem de colunas selecionadas
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out_row = [];
        foreach ($selected_real_cols as $c) {
            // se por algum motivo faltar a coluna no row, escreve vazio
            $out_row[] = isset($row[$c]) ? $row[$c] : '';
        }
        fputcsv($out, $out_row);
    }

} catch (Exception $e) {
    // Se ocorrer erro na execução, fecha o output e tenta informar o erro
    // Não podemos enviar mais headers; então escrevemos uma linha de erro no CSV
    fputcsv($out, ["# ERRO: " . $e->getMessage()]);
}

fclose($out);
exit;
?>
