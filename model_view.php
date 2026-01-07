<!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Modell & prognos</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1"></script>

<style>
  :root{
    --bg:#0b1220;
    --panel:#0e1830;
    --grid:#1f2a44;
    --text:#e8eefc;
    --muted:#a8b3cf;
    --line:#22c55e;
    --future:#22c55e;
    --now:#ffffff;
    --accent:#60a5fa;
  }
  body{
    margin:0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    background: radial-gradient(1200px 600px at 10% 0%, #0f2348 0%, var(--bg) 55%);
    color:var(--text);
  }
  .wrap{
    max-width: 1200px;
    margin: 0 auto;
    padding: 18px 14px 28px;
  }
  .header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom: 10px;
  }
  .title{
    font-size: 34px;
    font-weight: 800;
    letter-spacing: .2px;
    margin: 0;
  }
  .subtitle{
    margin-top: 6px;
    color: var(--muted);
    font-size: 14px;
    line-height: 1.4;
  }
  .card{
    background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 18px;
    padding: 14px;
    box-shadow: 0 12px 30px rgba(0,0,0,.25);
  }
  .chartBox{
    height: 72vh;
    min-height: 420px;
  }
  canvas{ width:100% !important; height:100% !important; }
  .legendHint{
    margin-top:10px;
    color: var(--muted);
    font-size: 12px;
    display:flex;
    gap:14px;
    flex-wrap: wrap;
  }
  .dot{
    display:inline-block;
    width:10px; height:10px;
    border-radius:999px;
    margin-right:7px;
    transform: translateY(1px);
  }
  .dot.line{ background: var(--line); }
  .dot.future{ background: var(--future); opacity:.6; border: 1px dashed rgba(255,255,255,.35); }
  .dot.now{ background: var(--now); }
  @media (max-width: 720px){
    .title{ font-size: 28px; }
    .chartBox{ height: 66vh; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div>
      <h1 class="title">Prognos</h1>
      <div class="subtitle" id="subtitle">Laddar…</div>
    </div>
  </div>

  <div class="card">
    <div class="chartBox">
      <canvas id="chart"></canvas>
    </div>
    <div class="legendHint">
      <span><span class="dot line"></span>Historik</span>
      <span><span class="dot future"></span>Framtid (streckad)</span>
      <span><span class="dot now"></span>Nu</span>
    </div>
  </div>
</div>

<script>
function toMs(sqlTs) {
  const iso = sqlTs.replace(" ", "T");
  const dt = luxon.DateTime.fromISO(iso);
  return dt.isValid ? dt.toMillis() : NaN;
}

const nowMs = Date.now();

// Plugin: vertikal “NU”-linje
const nowLinePlugin = {
  id: 'nowLinePlugin',
  afterDraw(chart, args, opts) {
    const {ctx, chartArea, scales} = chart;
    if (!chartArea) return;
    const x = scales.x.getPixelForValue(nowMs);
    if (!isFinite(x)) return;

    ctx.save();
    ctx.lineWidth = 1;
    ctx.setLineDash([6,6]);
    ctx.strokeStyle = 'rgba(255,255,255,0.35)';
    ctx.beginPath();
    ctx.moveTo(x, chartArea.top);
    ctx.lineTo(x, chartArea.bottom);
    ctx.stroke();

    // Label “NU”
    ctx.setLineDash([]);
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.font = '12px system-ui, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('NU', x + 8, chartArea.top + 14);
    ctx.restore();
  }
};

// Plugin: torr-target linje (horisontell)
const dryLinePlugin = {
  id: 'dryLinePlugin',
  afterDraw(chart, args, opts) {
    if (!opts || typeof opts.value !== "number") return;
    const {ctx, chartArea, scales} = chart;
    const y = scales.y.getPixelForValue(opts.value);
    if (!isFinite(y)) return;

    ctx.save();
    ctx.lineWidth = 1;
    ctx.setLineDash([4,6]);
    ctx.strokeStyle = 'rgba(96,165,250,0.55)';
    ctx.beginPath();
    ctx.moveTo(chartArea.left, y);
    ctx.lineTo(chartArea.right, y);
    ctx.stroke();

    ctx.setLineDash([]);
    ctx.fillStyle = 'rgba(96,165,250,0.85)';
    ctx.font = '12px system-ui, sans-serif';
    ctx.textAlign = 'left';
    ctx.fillText('Torr-gräns', chartArea.left + 10, y - 8);
    ctx.restore();
  }
};

fetch("model_api.php", { cache: "no-store" })
  .then(r => r.text())
  .then(txt => {
    let d;
    try { d = JSON.parse(txt); }
    catch(e) {
      document.getElementById("subtitle").innerText =
        "API gav inte ren JSON. Öppna model_api.php och kolla.";
      console.log(txt);
      return;
    }

    if (d.error) {
      document.getElementById("subtitle").innerText = d.error;
      return;
    }

    // Bygg datapunkter och dela i historik vs framtid
    const pts = d.model
      .map(p => ({ x: toMs(p.t), y: p.y }))
      .filter(p => Number.isFinite(p.x) && Number.isFinite(p.y));

    const past = pts.filter(p => p.x <= nowMs);
    const future = pts.filter(p => p.x >= nowMs);

    // “Nu”-punkt (beräknad från närmaste framtidspunkt)
    let nowPoint = null;
    if (future.length > 0) {
      nowPoint = future[0];
    } else if (past.length > 0) {
      nowPoint = past[past.length - 1];
    }

    document.getElementById("subtitle").innerText =
      "Start: " + d.start + " | " + (d.is_dry_now ? "Torr nu" : ("Torr runt: " + (d.dry_time ?? "—")));

    // Chart
    const ctx = document.getElementById("chart");

    const chart = new Chart(ctx, {
      type: "line",
      data: {
        datasets: [
          {
            label: "Historik",
            data: past,
            borderColor: "#22c55e",
            borderWidth: 3,
            pointRadius: 0,
            tension: 0.25
          },
          {
            label: "Framtid",
            data: future,
            borderColor: "rgba(34,197,94,0.7)",
            borderWidth: 3,
            pointRadius: 0,
            borderDash: [8, 8],
            tension: 0.25
          },
          ...(nowPoint ? [{
            label: "Nu",
            data: [nowPoint],
            borderColor: "transparent",
            pointBackgroundColor: "#ffffff",
            pointBorderColor: "rgba(0,0,0,0.25)",
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 7,
            showLine: false
          }] : [])
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "nearest", intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            padding: 10,
            displayColors: false,
            callbacks: {
              title(items) {
                const ms = items[0].parsed.x;
                return luxon.DateTime.fromMillis(ms).toFormat("yyyy-LL-dd HH:mm");
              },
              label(item) {
                return "Fukt: " + item.parsed.y.toFixed(1);
              }
            }
          },
          dryLinePlugin: { value: typeof d.dry_target === "number" ? d.dry_target : null }
        },
        scales: {
          x: {
            type: "time",
            time: { unit: "day" },
            grid: { color: "rgba(31,42,68,0.65)" },
            ticks: { color: "rgba(168,179,207,0.9)", maxRotation: 0 }
          },
          y: {
            grid: { color: "rgba(31,42,68,0.65)" },
            ticks: { color: "rgba(168,179,207,0.9)" }
          }
        }
      },
      plugins: [nowLinePlugin, dryLinePlugin]
    });
  })
  .catch(err => {
    document.getElementById("subtitle").innerText = "Fetch-fel: " + err;
  });
</script>
</body>
</html>
