let chartTemp = null;
let chartUmid = null;
let chartPressao = null;
let chartPressaoNM = null;
let chartOrvalho = null;

// Dados temporários para teste — você vai trocar para seu banco depois
function gerarDadosFake() {
    const labels = [];
    const temp = [];
    const umid = [];
    const press = [];
    const pressNM = [];
    const orvalho = [];

    for (let i = 0; i < 30; i++) {
        labels.push(`Dia ${i+1}`);
        temp.push(20 + Math.random() * 10);
        umid.push(40 + Math.random() * 40);
        press.push(1000 + Math.random() * 20);
        pressNM.push(1013 + Math.random() * 20);
        orvalho.push(10 + Math.random() * 10);
    }

    return { labels, temp, umid, press, pressNM, orvalho };
}

function criarGrafico(ctx, dados, label) {
    return new Chart(ctx, {
        type: "line",
        data: {
            labels: dados.labels,
            datasets: [{
                label,
                data: dados[label.toLowerCase()],
                borderWidth: 2,
                borderColor: "#4f7cff",
                backgroundColor: "rgba(79,124,255,0.2)",
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: "#fff" } }
            },
            scales: {
                x: { ticks: { color: "#ccc" } },
                y: { ticks: { color: "#ccc" } }
            }
        }
    });
}

function atualizarGraficos() {
    const dados = gerarDadosFake();

    if (chartTemp) chartTemp.destroy();
    if (chartUmid) chartUmid.destroy();
    if (chartPressao) chartPressao.destroy();
    if (chartPressaoNM) chartPressaoNM.destroy();
    if (chartOrvalho) chartOrvalho.destroy();

    chartTemp = criarGrafico(document.getElementById("graficoTemp"), dados, "Temp");
    chartUmid = criarGrafico(document.getElementById("graficoUmidade"), dados, "Umid");
    chartPressao = criarGrafico(document.getElementById("graficoPressao"), dados, "Press");
    chartPressaoNM = criarGrafico(document.getElementById("graficoPressaoNM"), dados, "PressNM");
    chartOrvalho = criarGrafico(document.getElementById("graficoOrvalho"), dados, "Orvalho");
}

document.getElementById("btnRefresh").addEventListener("click", atualizarGraficos);
window.onload = atualizarGraficos;
