<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . "/db.php";
header("Content-Type: application/json");

// ================== KONFIG ==================
$FUTURE_DAYS = 15;
$BUCKET_SECONDS = 1800;        // 30 min modellpunkter

// Smoothing (tar bort brutalt brus)
$SMOOTH_WINDOW =  nineOrDefault(9);

// Vattningsdetektion (robust, brusvänlig)
$BASELINE_MINUTES = 60;        // baseline-window före kandidat (min)
$HOLD_MINUTES     = 30;        // måste ligga kvar efter kandidat (min)
$JUMP_ABS_MIN     = 55;        // måste vara minst +55 raw över baseline
$JUMP_KEEP_FRAC   = 0.60;      // post-median måste vara minst 60% av JUMP_ABS_MIN över baseline
$COOLDOWN_HOURS   = 6;         // minst 6h mellan två vattningar

// Torr-definition (för exponentmodellen)
$FIT_DAYS   = 10;
$DRY_Q      = 0.15;            // 15e percentilen i fit-data => "platå"
$DRY_OFFSET = 3;               // torr-gräns = platå + 3 raw
$K_MIN      = 1e-4;

// ================== HELPERS ==================
function nineOrDefault($x){ return is_int($x) ? $x : 9; }

function median(array $a) {
  $n = count($a);
  if ($n === 0) return null;
  sort($a);
  $m = (int)floor($n / 2);
  return ($n % 2) ? $a[$m] : (($a[$m - 1] + $a[$m]) / 2);
}

function rolling_median(array $vals, int $w) {
  $out = [];
  $n = count($vals);
  $half = (int)floor($w / 2);
  for ($i=0; $i<$n; $i++) {
    $lo = max(0, $i-$half);
    $hi = min($n-1, $i+$half);
    $win = [];
    for ($j=$lo; $j<=$hi; $j++) $win[] = $vals[$j];
    $out[] = median($win);
  }
  return $out;
}

function percentile(array $a, float $q) {
  $n = count($a);
  if ($n === 0) return null;
  sort($a);
  $pos = ($n - 1) * $q;
  $lo = (int)floor($pos);
  $hi = (int)ceil($pos);
  if ($lo === $hi) return $a[$lo];
  $frac = $pos - $lo;
  return $a[$lo]*(1-$frac) + $a[$hi]*$frac;
}

function linreg(array $x, array $y) {
  $n = count($x);
  if ($n < 2) return [0.0, $y[0] ?? 0.0];
  $sx = array_sum($x);
  $sy = array_sum($y);
  $sxy = 0.0; $sx2 = 0.0;
  for ($i=0; $i<$n; $i++) { $sxy += $x[$i]*$y[$i]; $sx2 += $x[$i]*$x[$i]; }
  $den = ($n*$sx2 - $sx*$sx);
  if (abs($den) < 1e-12) return [0.0, $y[$n-1] ?? 0.0];
  $a = ($n*$sxy - $sx*$sy)/$den;
  $b = ($sy - $a*$sx)/$n;
  return [$a, $b];
}

// ================== HÄMTA DATA ==================
try {
  // använd tabellnamnet från db.php ($table)
  $stmt = $pdo->query("
    SELECT created_at AS ts, fukt AS value
    FROM $table
    ORDER BY created_at ASC
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  echo json_encode(["error" => "DB-fel"]);
  exit;
}

if (count($rows) < 40) {
  echo json_encode(["error" => "För lite data"]);
  exit;
}

// ================== PREPP ==================
$ts = [];
$val = [];
foreach ($rows as $r) {
  $t = strtotime($r['ts']);
  if ($t === false) continue;
  $ts[]  = (int)$t;
  $val[] = (float)$r['value'];
}
$n = count($val);
if ($n < 40) {
  echo json_encode(["error" => "För lite giltig data"]);
  exit;
}

$now = time();

// ================== ESTIMERA MÄTINTERVALL ==================
$deltas = [];
for ($i=1; $i<$n; $i++) {
  $dt = $ts[$i] - $ts[$i-1];
  if ($dt > 0 && $dt < 3600) $deltas[] = $dt;
}
$dtSec = median($deltas);
if ($dtSec === null) $dtSec = 60;
$dtSec = max(10, min(600, (int)round($dtSec))); // clamp 10s..10min

$baselinePts = max(5, (int)round(($BASELINE_MINUTES*60) / $dtSec));
$holdPts     = max(3, (int)round(($HOLD_MINUTES*60) / $dtSec));
$cooldownSec = $COOLDOWN_HOURS * 3600;

// ================== SMOOTH ==================
$smAll = rolling_median($val, 9);

// ================== VATTNINGSDETEKTION (NIVÅSKIFTE) ==================
// Kandidat vid index i om:
// 1) sm[i] - baselineMedian >= JUMP_ABS_MIN
// 2) median(sm[i..i+holdPts]) - baselineMedian >= JUMP_ABS_MIN*JUMP_KEEP_FRAC
// 3) cooldown
$lastSpikeIdx = 0;
$lastSpikeTs  = 0;
$lastSpikeTimeStr = null;

for ($i = $baselinePts; $i < $n - $holdPts; $i++) {

  // baseline: median före i
  $baseWin = array_slice($smAll, $i - $baselinePts, $baselinePts);
  $base = median($baseWin);
  if ($base === null) continue;

  $jump = $smAll[$i] - $base;
  if ($jump < $JUMP_ABS_MIN) continue;

  // hold: median efter i
  $postWin = array_slice($smAll, $i, $holdPts);
  $post = median($postWin);
  if ($post === null) continue;

  if (($post - $base) < ($JUMP_ABS_MIN * $JUMP_KEEP_FRAC)) continue;

  $tSpike = $ts[$i];
  if ($lastSpikeTs && ($tSpike - $lastSpikeTs) < $cooldownSec) continue;

  $lastSpikeIdx = $i;
  $lastSpikeTs  = $tSpike;
  $lastSpikeTimeStr = date("Y-m-d H:i:s", $tSpike);
}

// segment från senaste vattning
$segTs = array_slice($ts, $lastSpikeIdx);
$segSm = array_slice($smAll, $lastSpikeIdx);

if (count($segSm) < 20) {
  // fallback
  $segTs = $ts;
  $segSm = $smAll;
  $lastSpikeIdx = 0;
  $lastSpikeTs = 0;
  $lastSpikeTimeStr = null;
}

$t0 = $segTs[0];

// ================== FIT-DATA (SENASTE FIT_DAYS) ==================
$fitStart = $now - $FIT_DAYS*86400;

$fitT = [];
$fitY = [];
for ($i=0; $i<count($segSm); $i++) {
  if ($segTs[$i] < $fitStart) continue;
  $fitT[] = ($segTs[$i] - $t0) / 3600.0; // timmar
  $fitY[] = $segSm[$i];
}
if (count($fitY) < 12) {
  $fitT = [];
  $fitY = [];
  for ($i=0; $i<count($segSm); $i++) {
    $fitT[] = ($segTs[$i] - $t0) / 3600.0;
    $fitY[] = $segSm[$i];
  }
}

// ================== TORR-PLATÅ + TARGET ==================
$dryBase = percentile($fitY, $DRY_Q);
if ($dryBase === null) $dryBase = min($fitY);
$dryTarget = $dryBase + $DRY_OFFSET;

// C är platån
$C = $dryBase;

// ================== EXP FIT: y = C + A*exp(-k t) ==================
$lx = [];
$ly = [];
for ($i=0; $i<count($fitY); $i++) {
  $z = $fitY[$i] - $C;
  if ($z <= 1e-6) continue;
  $lx[] = $fitT[$i];
  $ly[] = log($z);
}

if (count($ly) < 8) {
  $A = max(1.0, max($fitY) - $C);
  $k = $K_MIN;
} else {
  list($m, $c0) = linreg($lx, $ly); // ln(z) = c0 + m t, m = -k
  $k = max($K_MIN, -$m);
  $A = exp($c0);
}

// ================== TORR-TID ==================
$hNow = ($now - $t0)/3600.0;
$yNow = $C + $A * exp(-$k * $hNow);

$isDryNow = ($yNow <= $dryTarget);

$dryTime = null;
$hoursToDry = 0.0;

if (!$isDryNow) {
  $ratio = ($dryTarget - $C) / max(1e-9, $A);
  if ($ratio <= 0) {
    $isDryNow = true;
    $hoursToDry = 0.0;
  } else {
    $tDryHours = -log($ratio) / $k;
    $tsDry = (int)round($t0 + $tDryHours*3600);
    if ($tsDry <= $now) {
      $isDryNow = true;
      $hoursToDry = 0.0;
    } else {
      $dryTime = $tsDry;
      $hoursToDry = ($tsDry - $now)/3600.0;
    }
  }
}

// ================== MODELLKURVA (t0 -> nu+15 dagar) ==================
$end = $now + $FUTURE_DAYS*86400;

$model = [];
for ($t = $t0; $t <= $end; $t += $BUCKET_SECONDS) {
  $h = ($t - $t0)/3600.0;
  $y = $C + $A * exp(-$k * $h);
  $model[] = ["t" => date("Y-m-d H:i:s", $t), "y" => $y];
}

// ================== OUTPUT ==================
echo json_encode([
  "start" => date("Y-m-d H:i:s", $t0),

  "spike_index" => $lastSpikeIdx,
  "spike_time"  => $lastSpikeTimeStr,

  // debug för att du ska kunna se att det matchar din data
  "dt_sec" => $dtSec,
  "baseline_minutes" => $BASELINE_MINUTES,
  "hold_minutes" => $HOLD_MINUTES,
  "baseline_pts" => $baselinePts,
  "hold_pts" => $holdPts,
  "jump_abs_min" => $JUMP_ABS_MIN,
  "jump_keep_frac" => $JUMP_KEEP_FRAC,
  "cooldown_hours" => $COOLDOWN_HOURS,

  "C" => $C,
  "A" => $A,
  "k" => $k,

  "dry_target" => $dryTarget,
  "is_dry_now" => $isDryNow,
  "hours_to_dry" => $isDryNow ? 0.0 : $hoursToDry,
  "dry_time" => $isDryNow ? null : date("Y-m-d H:i:s", (int)$dryTime),

  "model" => $model
]);

