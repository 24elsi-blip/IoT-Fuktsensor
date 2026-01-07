<!DOCTYPE html>
<html lang="sv">
<head>
  <meta charset="UTF-8">
  <title>Admin – Fuktdata (%)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
      background: linear-gradient(135deg, #0f172a, #020617);
      color: #e5e7eb;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .shell {
      width: min(96vw, 1100px);
      background: rgba(15, 23, 42, 0.96);
      border-radius: 18px;
      box-shadow: 0 20px 45px rgba(0,0,0,0.6);
      padding: 22px 24px 24px;
      border: 1px solid rgba(148,163,184,0.35);
      backdrop-filter: blur(10px);
    }
    .header { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom: 14px; }
    h1 { margin: 0; font-size: 1.4rem; }
    p { margin: 6px 0 0; color:#9ca3af; }

    .row { display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-top: 10px; }
    .btn {
      border: none; border-radius: 999px; padding: 7px 14px;
      cursor: pointer; font-size: .9rem; text-decoration:none;
      display:inline-flex; align-items:center; gap:8px;
    }
    .btn-primary { background:#2563eb; color:#e5e7eb; }
    .btn-primary:hover { background:#1d4ed8; }
    .btn-outline { background:transparent; border:1px solid rgba(148,163,184,0.6); color:#e5e7eb; }
    .btn-outline:hover { background:rgba(148,163,184,0.1); }
    .btn-danger { background: rgba(239,68,68,0.12); border:1px solid rgba(248,113,113,0.7); color:#fecaca; padding:5px 10px; font-size:.8rem; }
    .btn-danger:hover { background: rgba(239,68,68,0.2); }

    input[type="number"]{
      background:#020617;
      border:1px solid rgba(55,65,81,0.9);
      color:#e5e7eb;
      border-radius:999px;
      padding: 7px 12px;
      font-size: .9rem;
      width: 140px;
      outline:none;
    }
    input[type="number"]:focus { border-color:#2563eb; box-shadow:0 0 0 1px rgba(37,99,235,0.5); }

    .status { color:#9ca3af; font-size:.9rem; margin-top: 8px; }

    table { width:100%; border-collapse: collapse; margin-top: 12px; font-size: .9rem; }
    thead { background:#020617; }
    th, td { padding: 8px 10px; border-bottom:1px solid rgba(31,41,55,0.85); text-align:left; }
    th { color:#9ca3af; font-weight: 500; font-size:.8rem; }
    tbody tr:nth-child(even){ background: rgba(15,23,42,0.85); }
    tbody tr:hover{ background: rgba(30,64,175,0.15); }

    .right { text-align:right; }
    .pill {
      padding: 2px 10px; border-radius:999px; border:1px solid rgba(148,163,184,0.6);
      font-size:.82rem; color:#9ca3af;
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <div>
        <h1>Fuktdata – Admin (%)</h1>
        <p>Lägg till och visa data som % (0–100). Databasen sparar råvärden.</p>
      </div>
      <div class="row">
        <a class="btn btn-outline" href="graf.php">📈 Till grafen</a>
        <span class="pill" id="meta">0 rader</span>
      </div>
    </div>

    <div class="row">
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="number" id="pctInput" placeholder="Fukt % (0–100)" min="0" max="100" step="0.1">
        <button class="btn btn-primary" type="button" onclick="addPercent()">+ Lägg till %</button>
        <button class="btn btn-outline" type="button" onclick="reloadData()">↻ Uppdatera</button>
      </div>
      <div class="status" id="status">Väntar...</div>
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Fukt (%)</th>
          <th>Fukt (raw)</th>
          <th>Tid</th>
          <th class="right">Åtgärd</th>
        </tr>
      </thead>
      <tbody id="data-body">
        <tr><td colspan="5" class="status">Inga data ännu...</td></tr>
      </tbody>
    </table>
  </div>

  <script>
    // ======= KALIBRERING (samma som graf.php) =======
    const DRY_RAW = 763.19;  // 0%
    const WET_RAW = 815;     // 100%
    // ===============================================

    function clamp(x, lo, hi) { return Math.max(lo, Math.min(hi, x)); }

    function rawToPercent(raw) {
      const pct = ((raw - DRY_RAW) / (WET_RAW - DRY_RAW)) * 100.0;
      return clamp(pct, 0, 100);
    }

    function percentToRaw(pct) {
      const p = clamp(pct, 0, 100);
      return Math.round(DRY_RAW + (p / 100.0) * (WET_RAW - DRY_RAW));
    }

    function setStatus(msg, ok=true) {
      const el = document.getElementById('status');
      el.textContent = msg;
      el.style.color = ok ? '#9ca3af' : '#f97316';
    }

    function render(rows) {
      const tbody = document.getElementById('data-body');
      const meta = document.getElementById('meta');
      meta.textContent = rows.length + ' rader';

      tbody.innerHTML = '';
      if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="status">Inga data att visa.</td></tr>';
        return;
      }

      rows.forEach(r => {
        const raw = Number(r.fukt);
        const pct = rawToPercent(raw);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.id}</td>
          <td>${pct.toFixed(1)}%</td>
          <td>${raw}</td>
          <td>${r.created_at}</td>
          <td class="right">
            <button class="btn btn-danger" type="button" onclick="deleteRow(${r.id})">Ta bort</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function reloadData() {
      setStatus('Hämtar data...');
      try {
        const res = await fetch('api.php');
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        render(json.data || []);
        setStatus('OK');
      } catch (e) {
        setStatus('Fel: ' + e, false);
      }
    }

    async function addPercent() {
      const input = document.getElementById('pctInput');
      const v = parseFloat((input.value || '').replace(',', '.'));
      if (isNaN(v)) { setStatus('Skriv in ett % mellan 0 och 100.', false); input.focus(); return; }

      const pct = clamp(v, 0, 100);
      const raw = percentToRaw(pct);

      setStatus(`Skickar ${pct.toFixed(1)}% (raw ${raw})...`);
      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ fukt: raw })   // API sparar raw
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        input.value = '';
        setStatus('Tillagt!');
        reloadData();
      } catch (e) {
        setStatus('Fel vid tillägg: ' + e, false);
      }
    }

    async function deleteRow(id) {
      if (!confirm('Ta bort rad ' + id + '?')) return;
      setStatus('Tar bort rad ' + id + '...');
      try {
        const res = await fetch('api.php?id=' + encodeURIComponent(id), { method: 'DELETE' });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) throw new Error(json.error || ('HTTP ' + res.status));
        setStatus('Borttagen.');
        reloadData();
      } catch (e) {
        setStatus('Fel vid borttagning: ' + e, false);
      }
    }

    reloadData();
  </script>
</body>
</html>
