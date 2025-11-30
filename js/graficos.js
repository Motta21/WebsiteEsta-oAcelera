let chartTemp = null;
let chartUmid = null;

async function carregarDados(periodo) {
    const resp = await fetch(`dados_grafico.php?periodo=${periodo}`);
    const dados = await resp.json();
    return dados;
}

function criarGrafico(canvasId, chartRef, label, dados) {

    if (chartRef !== null) {
        chartRef.destroy();
    }

    const ctx = document.getElementById(canvasId).getContext("2d");

    const novoGrafico = new Chart(ctx, {
        type: "line",
        data: {
            labels: dados.labels,
            datasets: [{
                label: label,
                data: dados.valores,
                borderWidth: 2,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    return novoGrafico;
}

async function atualizarGraficos() {
    const periodo = document.getElementById("periodo").value;

    const dadosTemp = await carregarDados(periodo);
    const dadosUmid = await carregarDados(periodo);

    chartTemp = criarGrafico("graficoTemp", chartTemp, "Temperatura (°C)", dadosTemp);
    chartUmid = criarGrafico("graficoUmid", chartUmid, "Umidade (%)", dadosUmid);
}

// Atualiza ao carregar a página
window.onload = atualizarGraficos;
