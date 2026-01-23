let chartsGraphs = {};

const cores = {
    temperatura: "rgba(255, 99, 132, 1)",     
    umidade: "rgba(54, 162, 235, 1)",         
    pressao: "rgba(255, 206, 86, 1)",         
    pressaoNM: "rgba(153, 102, 255, 1)",      
    orvalho: "rgba(75, 192, 192, 1)",
    bat: "rgb(238, 217, 25)"          
};

async function carregarDados(periodo) {
    try {
        // Pega o valor da estação selecionada no HTML
        const estacaoElement = document.getElementById("estacao");
        const estacao = estacaoElement ? estacaoElement.value : 1; 

        // Faz o pedido para o PHP enviando PERIODO e COD_E
        const resposta = await fetch(`php/dados_grafico.php?periodo=${periodo}&cod_e=${estacao}`);  
        const json = await resposta.json();

        if (!json.sucesso) {
            mostrarToast("Erro ao carregar dados!");
            return;
        }

        atualizarGraficos(json.dados);

    } catch (erro) {
        console.error(erro);
        mostrarToast("Falha ao buscar dados!");
    } finally {
        // Esconde o loader se ele existir
        const loader = document.getElementById("loader");
        if(loader) loader.classList.add("hidden");
    }
}

function atualizarGraficos(dados) {
    if (dados.length === 0) {
        mostrarToast("Sem dados para este período/estação.");
        return;
    }

    const labels = dados.map(d => d.DataHora);
    const temperatura = dados.map(d => parseFloat(d.Temperatura));
    const umidade = dados.map(d => parseFloat(d.Umidade));
    const pressao = dados.map(d => parseFloat(d.Pressao));
    const pressaoNM = dados.map(d => parseFloat(d.Pressao_nivel_mar));
    const orvalho = dados.map(d => parseFloat(d.PTO_Orvalho));
    const bat = dados.map(d => parseFloat(d.NV_Bat));

    // --- Gráficos de Linha Padrão ---
    criarOuAtualizar("graficoTemp", labels, temperatura, "Temperatura (°C)", cores.temperatura);
    criarOuAtualizar("graficoUmidade", labels, umidade, "Umidade (%)", cores.umidade);
    criarOuAtualizar("graficoPressao", labels, pressao, "Pressão (hPa)", cores.pressao);
    criarOuAtualizar("graficoPressaoNM", labels, pressaoNM, "Pressão Nível Mar (hPa)", cores.pressaoNM);
    criarOuAtualizar("graficoOrvalho", labels, orvalho, "Ponto de Orvalho (°C)", cores.orvalho);
    criarOuAtualizar("graficoBat", labels, bat, "Bateria (V)", cores.bat);
    
    // --- Gráfico Triplo (Clima) ---
    criarOuAtualizar("graficoTemUmiPto", labels, [
        { label: "Temp", data: temperatura, borderColor: cores.temperatura, yAxisID: 'y', tension: 0.4 }, 
        { label: "Umi", data: umidade, borderColor: cores.umidade, yAxisID: 'y1', tension: 0.4 },      
        { label: "Orvalho", data: orvalho, borderColor: cores.orvalho, yAxisID: 'y', tension: 0.4 }    
    ], "Clima");

    // --- Lógica do Pluviômetro 
    
    // Para 6 horas (considerando registros de 10 em 10 min, coloquei -36 mesma coisa no oto)
    const dados6h = dados.slice(-36); 
    const chuva6h = processarDadosChuva(dados6h);
    const labels6h = dados6h.map(d => d.DataHora.split(' ')[1].substring(0, 5));
    criarOuAtualizarChuva("graficoChuva6h", labels6h, chuva6h.mmPorPeriodo, chuva6h.acumulado, "Últimas 6h");

    // Para 24 horas
    const dados24h = dados.slice(-144);
    const chuva24h = processarDadosChuva(dados24h);
    const labels24h = dados24h.map(d => d.DataHora.split(' ')[1].substring(0, 5));
    criarOuAtualizarChuva("graficoChuva24h", labels24h, chuva24h.mmPorPeriodo, chuva24h.acumulado, "Últimas 24h");
}

function criarOuAtualizar(idCanvas, labels, valores, titulo, cor) {
    const canvas = document.getElementById(idCanvas);
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    const ehMultiplo = Array.isArray(valores) && typeof valores[0] === 'object';

    const datasetsConfig = ehMultiplo ? valores : [{
        label: titulo,
        data: valores,
        borderColor: cor,
        backgroundColor: cor ? cor.replace("1)", "0.2)") : "transparent",
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.35
    }];

    // Configuração básica das escalas
    let escalasConfig = {
        x: { ticks: { maxTicksLimit: 8 } },
        y: { beginAtZero: false } // Eixo padrão (Esquerdo)
    };

    // SE for o gráfico triplo, adicionamos o eixo secundário na direita
if (idCanvas === "graficoTemUmiPto") {
    escalasConfig = {
        x: { ticks: { maxTicksLimit: 8 } },
        y: { // Temperatura e Orvalho (Esquerda)
            type: 'linear',
            display: true,
            position: 'left',
            title: { display: true, text: 'Temp / Orvalho (°C)' },
            min: 0, 
            max: 40,
            beginAtZero: false
        },
        y1: { 
            type: 'linear',
            display: true,
            position: 'right',
            title: { display: true, text: '% Umidade' },
            min: 0, 
            max: 100, 
            grid: { drawOnChartArea: false }
        }
    };
}

    if (chartsGraphs[idCanvas]) {
        chartsGraphs[idCanvas].data.labels = labels;
        chartsGraphs[idCanvas].data.datasets = datasetsConfig;
        // Importante: Se mudar as escalas, precisamos atualizar as options também
        chartsGraphs[idCanvas].options.scales = escalasConfig; 
        chartsGraphs[idCanvas].update();
    } else {
        chartsGraphs[idCanvas] = new Chart(ctx, {
            type: "line",
            data: {
                labels: labels,
                datasets: datasetsConfig
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                scales: escalasConfig // Usa a configuração definida acima
            }
        });
    }
}


//PLUVIOMETRO (DADOS DA CHUVAAAAAAA :))
function processarDadosChuva(dados) {
    let soma = 0;
    const mmPorPeriodo = dados.map(d => parseFloat(d.Chuva || 0));
    const acumulado = mmPorPeriodo.map(valor => {
        soma += valor;
        return soma.toFixed(2);
    });
    return { mmPorPeriodo, acumulado };
}

function criarOuAtualizarChuva(idCanvas, labels, mmPeriodo, acumulado, titulo) {
    const canvas = document.getElementById(idCanvas);
    if (!canvas) return;

    const ctx = canvas.getContext("2d");

    const datasetsConfig = [
        {
            type: 'bar',
            label: 'Precipitação (mm)',
            data: mmPeriodo,
            backgroundColor: '#4682B4', // Azul aço igual ao CEMADEN
            borderRadius: 3,
            order: 2
        },
        {
            type: 'line',
            label: 'Acumulada (mm)',
            data: acumulado,
            borderColor: '#8B0000', // Vermelho escuro para acumulado
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.1,
            fill: false,
            order: 1
        }
    ];

    if (chartsGraphs[idCanvas]) {
        chartsGraphs[idCanvas].data.labels = labels;
        chartsGraphs[idCanvas].data.datasets = datasetsConfig;
        chartsGraphs[idCanvas].update();
    } else {
        chartsGraphs[idCanvas] = new Chart(ctx, {
            data: { labels: labels, datasets: datasetsConfig },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        title: { display: true, text: 'Milímetros (mm)' }
                    },
                    x: { ticks: { maxTicksLimit: 12 } }
                }
            }
        });
    }
}



// === EVENT LISTENERS (Aqui embaixo é o lugar certo) ===

// Botão de atualizar manual
const btnRefresh = document.getElementById("btnRefresh");
if(btnRefresh) {
    btnRefresh.addEventListener("click", () => {
        carregarDados(document.getElementById("periodo").value);
    });
}

// Quando muda o período (Diário, Semanal...)
const selectPeriodo = document.getElementById("periodo");
if(selectPeriodo) {
    selectPeriodo.addEventListener("change", () => {
        carregarDados(selectPeriodo.value);
    });
}

// === NOVO: Quando muda a Estação ===
const selectEstacao = document.getElementById("estacao");
if(selectEstacao) {
    selectEstacao.addEventListener("change", () => {
        // Recarrega os dados mantendo o período que já estava escolhido
        carregarDados(document.getElementById("periodo").value);
    });
}

function mostrarToast(msg) {
    const toast = document.getElementById("toast");
    if(!toast) return;
    
    toast.textContent = msg;
    toast.classList.add("show");
    setTimeout(() => toast.classList.remove("show"), 3000);
}

// Iniciar
window.onload = () => carregarDados("diario");