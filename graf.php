<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8" />
  <title>Fukt i plantkruka – %</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root{
      --bg-card: rgba(15, 23, 42, 0.96);
      --border-subtle: rgba(148, 163, 184, 0.35);
      --text-main: #e5e7eb;
      --text-muted: #9ca3af;
      --accent: #2563eb;
      --soil: #8B5A2B;
      --green: #16a34a;
      --orange: #f97316;
    }
    *{ box-sizing: border-box; }
    html,body{ height:100%; margin:0; }
    body{
      font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
      background: radial-gradient(circle at top left, #0b1120 0, #020617 45%, #000 100%);
      color: var(--text-main);
      display:flex;
      justify-content:center;
      align-items:stretch;
    }
    .page{
      width:100%;
      padding:24px;
      display:flex;
      justify-content:center;
      align-items:center;
    }
    .card{
      width:min(96vw, 1400px);
      background: var(--bg-card);
      border-radius:20px;
      border:1px solid var(--border-subtle);
      box-shadow:0 24px 60px rgba(0,0,0,.65);
      padding:22px 26px 24px;
      backdrop-filter: blur(12px);
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .header{
      display:flex;
      justify-content:space-between;
      gap:14px;
      flex-wrap:wrap;
      align-items:flex-start;
    }
    .title-block h1{
      margin:0;
      font-size:1.6rem;
      display:flex;
      gap:10px;
      align-items:center;
    }
    .title-pill{
      font-size:.75rem;
      padding:2px 8px;
      border-radius:999px;
      background: rgba(34,197,94,.16);
      border:1px solid rgba(34,197,94,.30);
      color:#bbf7d0;
    }
    .title-block p{
      margin:6px 0 0;
      color: var(--text-muted);
      font-size:.92rem;
      max-width: 900px;
    }

    .controls{
      display:flex;
      flex-direction:column;
      gap:8px;
      align-items:flex-end;
      min-width: 320px;
    }
    .controls-row{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      justify-content:flex-end;
      align-items:center;
    }

    .btn{
      border:none;
      border-radius:999px;
      padding:7px 14px;
      cursor:pointer;
      font-size:.9rem;
      text-decoration:none;
      display:inline-flex;
      align-items:center;
      gap:8px;
      white-space:nowrap;
      transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
      user-select:none;
    }
    .btn-outline{
      background: transparent;
      color: var(--text-main);
      border:1px solid rgba(148,163,184,.70);
    }
    .btn-outline:hover{ background: rgba(148,163,184,.10); }

    .btn-primary{
      background: var(--accent);
      color:#e5e7eb;
      box-shadow:0 10px 22px rgba(37,99,235,.45);
    }
    .btn-primary:hover{
      background:#1d4ed8;
      transform: translateY(-1px);
      box-shadow:0 14px 30px rgba(37,99,235,.55);
    }

    .btn-admin{
      background: linear-gradient(135deg, #22c55e, #16a34a);
      color:#022c22;
      box-shadow:0 10px 26px rgba(22,163,74,.6);
      font-weight:600;
    }
    .btn-admin:hover{
      transform: translateY(-1px);
      box-shadow:0 16px 34px rgba(22,163,74,.7);
    }

    select{
      background: #020617;
      border:1px solid rgba(148,163,184,.55);
      color: var(--text-main);
      border-radius: 999px;
      padding: 7px 12px;
      font-size:.9rem;
      outline:none;
    }
    select:focus{
      border-color:#2563eb;
      box-shadow:0 0 0 1px rgba(37,99,235,.45);
    }

    .status{
      font-size:.85rem;
      color: var(--text-muted);
      display:flex;
      align-items:center;
      gap:8px;
      justify-content:flex-end;
    }
    .dot{
      width:9px; height:9px; border-radius:999px;
      background: var(--green);
      box-shadow:0 0 0 4px rgba(22,163,74,.20);
    }

    .chart-container{
      position:relative;
      width:100%;
      height: clamp(260px, 55vh, 540px);
      margin-top: 6px;
    }

    .meta-row{
      display:flex;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      color: var(--text-muted);
    }
    .pill{
      padding:2px 10px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.60);
      font-size:.85rem;
    }

    @media (max-width: 768px){
      .card{ padding:18px 14px 18px; }
      .header{ flex-direction:column; }
      .controls{ align-items:flex-start; min-width: 0; }
      .controls-row{ justify-content:flex-start; }
      .status{ justify-content:flex-start; }
    }
  </style>
</head>

<body>
  <div class="page">
    <div class="card">
      <div class="header">
        <div class="title-block">
          <h1>Fukt i plantkruka <span class="title-pill">Procent</span></h1>
          <p>
            För snabb och tydlig graf används “bucket averages” (medel per tidsintervall) istället för att rita varje minutpunkt.
            Du kan fortfarande se rådata via “Senaste 50 (rådata)”.
          </p>
        </div>

        <div class="controls">
          <div class="controls-row">
            <select id="viewSelect" onchange="reloadData()">
              <option value="30d">Alla (30 dagar, 30 min medel)</option>
              <option value="7d">7 dagar (5 min medel)</option>
              <option value="24h">24 timmar (1 min medel)</option>
              <option value="raw50">Senaste 50 (rådata)</option>
            </select>
            <a class="btn btn-outline" href="index.html">Home</a>
            <a class="btn btn-outline" href="model_view.php">📐 Visa modell</a>

            <button class="btn btn-primary" type="button" onclick="reloadData()">↻ Uppdatera</button>
            <a class="btn btn-admin" href="data.php">✏️ Ändra / hantera värden</a>
          </div>

          <div class="status" id="status">
            <span class="dot" id="dot"></span>
            <span id="statusText">Väntar på data…</span>
          </div>
        </div>
      </div>

      <div class="chart-container">
        <canvas id="moistureChart"></canvas>
      </div>

      <div class="meta-row">
        <div class="pill" id="metaLeft">—</div>
        <div class="pill" id="metaRight">—</div>
      </div>
    </div>
  </div>

<script>
  // ======= KALIBRERING FÖR PROCENT =======
  // 0% = din “torra baslinje”
  // 100% = “nyvattnad nivå”
  const DRY_RAW = 763.19;
  const WET_RAW = 815;
  // ======================================

  let chart = null;

  function clamp(x, lo, hi){ return Math.max(lo, Math.min(hi, x)); }

  function rawToPercent(raw){
    const pct = ((raw - DRY_RAW) / (WET_RAW - DRY_RAW)) * 100;
    return clamp(pct, 0, 100);
  }

  // EMA-smoothing (ser “definitivt” ut men reagerar ändå)
  function ema(values, alpha = 0.12){
    if(!values || values.length === 0) return [];
    let s = values[0];
    const out = [s];
    for(let i=1;i<values.length;i++){
      s = alpha*values[i] + (1-alpha)*s;
      out.push(s);
    }
    return out;
  }

  function setStatus(text, ok=true){
    const dot = document.getElementById('dot');
    const statusText = document.getElementById('statusText');
    statusText.textContent = text;
    if(ok){
      dot.style.background = getComputedStyle(document.documentElement).getPropertyValue('--green').trim();
      dot.style.boxShadow = '0 0 0 4px rgba(22,163,74,.20)';
    }else{
      dot.style.background = getComputedStyle(document.documentElement).getPropertyValue('--orange').trim();
      dot.style.boxShadow = '0 0 0 4px rgba(249,115,22,.28)';
    }
  }

  function buildUrl(){
    const v = document.getElementById('viewSelect').value;

    // Bucket-lägen (snabbt, snyggt)
    if(v === '30d') return { url: 'api.php?range=30d&bucket=30m', label: '30d / 30m bucket' };
    if(v === '7d')  return { url: 'api.php?range=7d&bucket=5m',   label: '7d / 5m bucket' };
    if(v === '24h') return { url: 'api.php?range=24h&bucket=1m',  label: '24h / 1m bucket' };

    // Råläge (detaljer)
    if(v === 'raw50') return { url: 'api.php?limit=50', label: 'raw senaste 50' };

    return { url: 'api.php?range=30d&bucket=30m', label: '30d / 30m bucket' };
  }

  async function fetchData(){
    const { url, label } = buildUrl();
    setStatus('Hämtar… (' + label + ')', true);

    const res = await fetch(url);
    if(!res.ok) throw new Error('HTTP ' + res.status);

    const json = await res.json();
    if(!json.success) throw new Error(json.error || 'okänt fel');

    // bucket-mode levereras ASC i vår api.php, raw levereras oftast DESC -> vänd
    let rows = json.data || [];
    if(json.mode === 'raw') rows = rows.slice().reverse();

    setStatus('Data uppdaterad', true);
    return { rows, mode: json.mode || 'unknown' };
  }

  function updateMeta(rows, mode){
    const left = document.getElementById('metaLeft');
    const right = document.getElementById('metaRight');

    left.textContent = `${rows.length} punkter (${mode})`;

    if(rows.length === 0){
      right.textContent = 'Senaste: –';
      return;
    }

    const last = rows[rows.length - 1];
    const raw = Number(last.fukt);
    const pct = rawToPercent(raw);

    right.textContent = `Senaste: ${pct.toFixed(1)}% (raw ${raw}) – ${last.created_at}`;
  }

  function buildChart(rows, mode){
    const ctx = document.getElementById('moistureChart').getContext('2d');

    const labels = rows.map(r => r.created_at);
    const rawValues = rows.map(r => Number(r.fukt));
    const pctValues = rawValues.map(rawToPercent);

    // I bucket-mode är datan redan “averaged”, så EMA kan vara svagare
    const alpha = (mode === 'bucket') ? 0.18 : 0.12;
    const smooth = ema(pctValues, alpha);

    if(chart) chart.destroy();

    chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Fukt (trend, %)',
            data: smooth,
            fill: true,
            backgroundColor: 'rgba(139, 90, 43, 0.18)',
            borderColor: 'rgba(139, 90, 43, 0.95)',
            borderWidth: 2,
            tension: 0.35,
            pointRadius: 0,
            pointHitRadius: 10,
          },
          {
            label: 'Fukt (%-punkter)',
            data: pctValues,
            fill: false,
            borderWidth: 0,
            pointRadius: (mode === 'raw') ? 2 : 0,          // visa prickar bara i raw-läge
            pointBackgroundColor: 'rgba(148, 163, 184, 0.9)',
            pointHitRadius: 6,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,

        // Viktigt när mycket data: mindre animationer
        animation: { duration: 250 },

        scales: {
          x: {
            grid: { color: 'rgba(148,163,184,0.18)' },
            ticks: {
              color: '#9ca3af',
              autoSkip: true,
              maxTicksLimit: 10,
              maxRotation: 45,
              minRotation: 45
            }
          },
          y: {
            min: 0,
            max: 100,
            grid: { color: 'rgba(148,163,184,0.12)' },
            ticks: {
              color: '#9ca3af',
              callback: (v) => v + '%'
            }
          }
        },
        plugins: {
          legend: { display: true },
          tooltip: {
            backgroundColor: 'rgba(15,23,42,0.95)',
            borderColor: 'rgba(148,163,184,0.6)',
            borderWidth: 1,
            titleColor: '#e5e7eb',
            bodyColor: '#e5e7eb',
            displayColors: false,
            callbacks: {
              title: (items) => 'Tid: ' + items[0].label,
              label: (ctx) => {
                const i = ctx.dataIndex;
                const raw = rawValues[i];
                const pct = pctValues[i];
                return `${ctx.dataset.label}: ${pct.toFixed(1)}% (raw ${raw})`;
              }
            }
          }
        }
      }
    });
  }

  async function reloadData(){
    try{
      const { rows, mode } = await fetchData();
      updateMeta(rows, mode);
      buildChart(rows, mode);
    }catch(e){
      setStatus('Fel: ' + e.message, false);
      updateMeta([], 'error');
      if(chart){ chart.destroy(); chart = null; }
    }
  }

  reloadData();
</script>
</body>
</html>
