// CONFIGURAÇÕES
const channelID = 3022907;
const BASE_URL = `https://api.thingspeak.com/channels/${channelID}/feeds.json`;
const DEFAULT_RESULTS = 20;

// Limites para alertas
const LIMITS = {
  temp: { warn: 30, danger: 35, low: 12 }
};

// ESTADO GLOBAL
let charts = {};
let allFeeds = [];
let currentResults = DEFAULT_RESULTS;
let currentSort = { col: 0, asc: false };
let toastTimeout = null;

// ELTs
const el = (id) => document.getElementById(id);
const historicoBody = el('historico-body');
const filtroHistorico = el('filtro-historico');
const buscaTabela = el('buscaTabela');
const loader = el('loader');
const toast = el('toast');

// UTIL
const showLoader = (v = true) => loader.classList.toggle('hidden', !v);

const showToast = (msg, type = 'warn') => {
  if (!toast) return;
  toast.textContent = msg;

  if (type === 'danger') {
    toast.style.background = 'linear-gradient(135deg, rgba(239,68,68,.95), rgba(244,63,94,.95))';
  } else {
    toast.style.background = 'linear-gradient(135deg, rgba(245,158,11,.95), rgba(234,179,8,.95))';
  }

  toast.classList.add('show');

  if (toastTimeout) clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => toast.classList.remove('show'), 3500);
};

const fmtTime = (s) => {
  try { return new Date(s).toLocaleString(); }
  catch (e) { return s; }
};
const parseNum = (v) => {
  if (v == null || v === '') return null;
  const n = parseFloat(v);
  return Number.isFinite(n) ? n : null;
};

// THEME
(function initTheme() {
  const saved = sessionStorage.getItem('theme');
  const html = document.documentElement;

  if (saved) html.setAttribute('data-theme', saved);

  const tbtn = el('toggleTheme');
  if (tbtn) {
    tbtn.addEventListener('click', () => {
      const cur = html.getAttribute('data-theme') || 'light';
      const nxt = cur === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', nxt);
      sessionStorage.setItem('theme', nxt);
    });
  }
})();

// SIDEBAR
(function initSidebar(){
  const btn = el('toggleSidebar');
  const sidebar = el('sidebar');
  if (!btn || !sidebar) return;

  const savedState = sessionStorage.getItem('sidebarState');
  if (savedState === 'expanded') sidebar.classList.remove('collapsed');
  else sidebar.classList.add('collapsed');

  btn.addEventListener('click', ()=> {
    sidebar.classList.toggle('collapsed');
    sessionStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
  });

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', (e) => {
    if (sidebar.classList.contains('collapsed')) {
      e.preventDefault(); 
    }
  });
});
})();

// RELOGIO
function atualizarHora(){
  const h = el('hora');
  if (h) h.textContent = new Date().toLocaleString();
}

// NAV ACTIVE ON SCROLL
(function navActiveOnScroll(){
  const links = [...document.querySelectorAll('.nav-link')];
  const sections = links.map(a => {
    const sel = a.getAttribute('href');
    try { return document.querySelector(sel); } catch(e) { return null; }
  }).filter(Boolean);
  if (!sections.length) return;
  const onScroll = () => {
    const y = window.scrollY + 120;
    let active = sections[0];
    for (const sec of sections) if (sec.offsetTop <= y) active = sec;
    links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#'+active.id));
  };
  document.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
})();

// FETCH & PROCESS
async function fetchData(results = DEFAULT_RESULTS) {
  const url = `${BASE_URL}?results=${results}`;
  showLoader(true);
  try {
    const res = await fetch(url, { mode: 'cors' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    allFeeds = json.feeds || [];
    if (!allFeeds.length) showToast('Nenhuma leitura encontrada (verifique Channel ID / API Key se necessário).', 'warn');
    return allFeeds;
  } catch (e) {
    console.error(e);
    showToast('❌ Erro ao carregar dados do servidor. Verifique CORS / Channel ID / API Key', 'danger');
    return [];
  } finally {
    showLoader(false);
  }
}

// CONSTRUIR TABELA
function renderTable(feeds) {
  if (!historicoBody) return;
  const q = (buscaTabela?.value || '').toLowerCase().trim();
  const filtered = (feeds || []).filter(f => {
    if (!q) return true;
    return Object.values(f).some(v => (v ?? '').toString().toLowerCase().includes(q));
  });

  const rows = filtered.map(f => ([
    f.created_at,
    parseNum(f.field2),
    parseNum(f.field3),
    parseNum(f.field4),
    parseNum(f.field5),
    parseNum(f.field6)
  ]));

  rows.sort((a,b)=>{
    const {col, asc} = currentSort;
    const va = a[col], vb = b[col];
    if (va==null && vb==null) return 0;
    if (va==null) return 1; if (vb==null) return -1;
    if (col === 0) return asc ? new Date(va)-new Date(vb) : new Date(vb)-new Date(va);
    return asc ? (va-vb) : (vb-va);
  });

  historicoBody.innerHTML = rows.map(r => `
    <tr>
      <td>${fmtTime(r[0])}</td>
      <td>${r[1] ?? ''}</td>
      <td>${r[2] ?? ''}</td>
      <td>${r[3] ?? ''}</td>
      <td>${r[4] ?? ''}</td>
      <td>${r[5] ?? ''}</td>
    </tr>
  `).join('');
}

// GRÁFICOS
function makeGradient(ctx, color) {
  const h = ctx && ctx.canvas && ctx.canvas.height ? ctx.canvas.height : 300;
  const g = ctx.createLinearGradient(0,0,0,h);
  g.addColorStop(0, color);
  g.addColorStop(1, 'rgba(0,0,0,0)');
  return g;
}

function destroyCharts() {
  Object.values(charts).forEach(ch => {
    try { ch?.destroy?.(); } catch(e){ }
  });
  charts = {};
}

function renderCharts(feeds) {
  const safeFeeds = feeds || [];
  const labels = safeFeeds.map(f => {
    try { return new Date(f.created_at).toLocaleTimeString(); }
    catch { return f.created_at; }
  });

  const temp = safeFeeds.map(f => parseNum(f.field2));
  const umid = safeFeeds.map(f => parseNum(f.field3));
  const press = safeFeeds.map(f => parseNum(f.field4));
  const pressnm = safeFeeds.map(f => parseNum(f.field5));
  const orvalho = safeFeeds.map(f => parseNum(f.field6));

  destroyCharts();

  const canvasTemp = document.getElementById('graficoTemp');
  const canvasUmid = document.getElementById('graficoUmidade');
  const canvasPress = document.getElementById('graficoPressao');
  const canvasPressNM = document.getElementById('graficoPressaoNM');
  const canvasOrvalho = document.getElementById('graficoOrvalho');

  if (!canvasTemp && !canvasUmid && !canvasPress && !canvasPressNM && !canvasOrvalho) {
    return;
  }

  // Temperatura
  if (canvasTemp) {
    const ctxT = canvasTemp.getContext('2d');
    charts.temp = new Chart(ctxT, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Temperatura (°C)',
          data: temp,
          borderColor: '#ef4444',
          backgroundColor: makeGradient(ctxT, 'rgba(239,68,68,0.35)'),
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: baseLineOptions('°C')
    });
  }

  // Umidade
  if (canvasUmid) {
    const ctxU = canvasUmid.getContext('2d');
    charts.umid = new Chart(ctxU, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Umidade (%)',
          data: umid,
          borderColor: '#38bdf8',
          backgroundColor: makeGradient(ctxU, 'rgba(56,189,248,0.35)'),
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: baseLineOptions('%')
    });
  }

  // Pressão
  if (canvasPress) {
    const ctxP = canvasPress.getContext('2d');
    charts.press = new Chart(ctxP, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Pressão (hPa)',
          data: press,
          borderColor: '#22c55e',
          backgroundColor: makeGradient(ctxP, 'rgba(34,197,94,0.35)'),
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: baseLineOptions('hPa')
    });
  }

  // Pressão Nível do Mar
  if (canvasPressNM) {
    const ctxPNM = canvasPressNM.getContext('2d');
    charts.pressnm = new Chart(ctxPNM, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Pressão Nível do Mar (hPa)',
          data: pressnm,
          borderColor: '#facc15',
          backgroundColor: makeGradient(ctxPNM, 'rgba(250,204,21,0.35)'),
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: baseLineOptions('hPa')
    });
  }

  // Ponto de Orvalho
  if (canvasOrvalho) {
    const ctxO = canvasOrvalho.getContext('2d');
    charts.orvalho = new Chart(ctxO, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Ponto de Orvalho (°C)',
          data: orvalho,
          borderColor: '#a78bfa',
          backgroundColor: makeGradient(ctxO, 'rgba(167,139,250,0.35)'),
          fill: true,
          tension: 0.35,
          pointRadius: 2
        }]
      },
      options: baseLineOptions('°C')
    });
  }
}

function baseLineOptions(unit) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 800 },
    plugins: {
      legend: { display: true },
      tooltip: { callbacks: { label: (ctx) => `${ctx.formattedValue} ${unit}` } }
    },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.06)' } },
      y: { grid: { color: 'rgba(255,255,255,0.06)' } }
    }
  };
}
function baseBarOptions(unit) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 700 },
    plugins: {
      legend: { display: true },
      tooltip: { callbacks: { label: (ctx) => `${ctx.formattedValue} ${unit}` } }
    },
    scales: {
      x: { grid: { display: false } },
      y: { grid: { color: 'rgba(255,255,255,0.06)' } }
    }
  };
}

// CARDS DINÂMICOS + ALERTAS
function renderCards(last) {
  if (!last) return;
  const t = parseNum(last.field2);
  const u = parseNum(last.field3);
  const p = parseNum(last.field4);
  const pnm = parseNum(last.field5);
  const o = parseNum(last.field6);

  if (el('temp-atual')) el('temp-atual').textContent = (t!=null ? `${t.toFixed(1)} °C` : '-- °C');
  if (el('umid-atual')) el('umid-atual').textContent = (u!=null ? `${u.toFixed(0)} %` : '-- %');
  if (el('pressao-atual')) el('pressao-atual').textContent = (p!=null ? `${p.toFixed(0)} hPa` : '-- hPa');
  if (el('pressao-nm-atual')) el('pressao-nm-atual').textContent = (pnm!=null ? `${pnm.toFixed(0)} hPa` : '-- hPa');
  if (el('orvalho-atual')) el('orvalho-atual').textContent = (o!=null ? `${o.toFixed(1)} °C` : '-- °C');

  const setStatus = (id, status, text) => {
    const elx = document.getElementById(id);
    if (!elx) return;
    elx.className = '';
    if (status) elx.classList.add(status);
    elx.textContent = text;
  };

  if (t!=null) {
    if (t >= LIMITS.temp.danger) setStatus('temp-status','status-bad','Perigo: calor extremo');
    else if (t >= LIMITS.temp.warn) setStatus('temp-status','status-warn','Atenção: calor');
    else if (t <= LIMITS.temp.low) setStatus('temp-status','status-warn','Atenção: frio');
    else setStatus('temp-status','status-ok','Dentro do esperado');
  } else setStatus('temp-status', null, '—');

  // Outros status 
  setStatus('umid-status', null, '—');
  setStatus('pressao-status', null, '—');
  setStatus('pressao-nm-status', null, '—');
  setStatus('orvalho-status', null, '—');

  ['card-temp','card-umid','card-pressao','card-pressao-nm','card-orvalho'].forEach(id=>{
  const c = document.getElementById(id);
  if (!c) return;
  c.style.transition = 'transform .25s ease, box-shadow .25s ease';
  c.style.transform = 'translateY(-3px)';
  c.style.boxShadow = '0 18px 40px rgba(0,0,0,.25)';
  setTimeout(()=> { c.style.transform = ''; c.style.boxShadow = ''; }, 250);
});
}

// RENDER GERAL
function renderEverything(feeds) {
  if (!feeds || !feeds.length) return;
  const recent = feeds.slice(-currentResults);
  renderCards(recent[recent.length-1]);
  renderCharts(recent);
  renderTable(recent);
}

// HANDLERS
async function atualizar() {
  currentResults = parseInt(filtroHistorico ? filtroHistorico.value : DEFAULT_RESULTS, 10) || DEFAULT_RESULTS;
  const feeds = await fetchData(Math.max(10, currentResults));
  renderEverything(feeds);
}

function initSort() {
  const ths = document.querySelectorAll('#tabelaHistorico thead th');
  ths.forEach((th, idx)=>{
    th.style.cursor = 'pointer';
    th.addEventListener('click', ()=>{
      const type = th.dataset.sort;
      if (currentSort.col === idx) currentSort.asc = !currentSort.asc;
      else { currentSort.col = idx; currentSort.asc = type==='data' ? true : false; }
      renderTable(allFeeds.slice(-currentResults));
    });
  });
}

// INIT
function init() {
  atualizarHora();
  setInterval(atualizarHora, 1000);

  if (filtroHistorico) filtroHistorico.addEventListener('change', atualizar);
  const btn = el('btnRefresh');
  if (btn) btn.addEventListener('click', atualizar);
  if (buscaTabela) buscaTabela.addEventListener('input', () => renderTable(allFeeds.slice(-currentResults)));

  initSort();
  atualizar();
  setInterval(atualizar, 60_000);
}

document.addEventListener('DOMContentLoaded', init);

document.addEventListener('DOMContentLoaded', () => {
  const path = window.location.pathname;
  document.querySelectorAll('.nav-link').forEach(link => {
    if (path.includes(link.getAttribute('href'))) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
});

// --- LÓGICA DO CARROSSEL ---

document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.getElementById('carouselImages');
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');

    if (!carousel) return; 

    const images = carousel.querySelectorAll('.carousel-image');
    let currentIndex = 0;
    const totalImages = images.length;

    function updateCarousel() {
        const offset = -currentIndex * 100;
        carousel.style.transform = `translateX(${offset}%)`;
    }

    // Navegação para a próxima imagem
    nextButton.addEventListener('click', () => {
        currentIndex = (currentIndex + 1) % totalImages; 
        updateCarousel();
    });

    // Navegação para a imagem anterior
    prevButton.addEventListener('click', () => {
        currentIndex = (currentIndex - 1 + totalImages) % totalImages; 
        updateCarousel();
    });

    // Auto-play (Troca automática a cada 5 segundos)
    const intervalTime = 5000;
    setInterval(() => {
        currentIndex = (currentIndex + 1) % totalImages;
        updateCarousel();
    }, intervalTime);
});