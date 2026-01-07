<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

function parseRangeToSqlInterval($range) {
  if (!$range) return null;
  if (!preg_match('/^(\d+)([dh])$/', $range, $m)) return null;
  $n = (int)$m[1];
  $unit = $m[2] === 'd' ? 'DAY' : 'HOUR';
  return [$n, $unit];
}

function parseBucketSeconds($bucket) {
  if (!$bucket) return null;
  if (!preg_match('/^(\d+)([mh])$/', $bucket, $m)) return null;
  $n = (int)$m[1];
  $u = $m[2];
  if ($u === 'm') return $n * 60;
  if ($u === 'h') return $n * 3600;
  return null;
}

function readJsonBody() {
  $raw = file_get_contents('php://input');
  if (!$raw) return null;
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}

switch ($method) {

  // =========================
  // GET: raw eller bucket
  // =========================
  case 'GET':
    try {
      $limit  = isset($_GET['limit']) ? max(1, min(5000, (int)$_GET['limit'])) : null;
      $range  = isset($_GET['range']) ? $_GET['range'] : null;    // ex "14d" or "48h"
      $bucket = isset($_GET['bucket']) ? $_GET['bucket'] : null;  // ex "5m" or "1h"

      $rangeParsed = parseRangeToSqlInterval($range);
      $bucketSec = parseBucketSeconds($bucket);

      // ---------- Aggregated bucket mode ----------
      if ($rangeParsed && $bucketSec) {
        [$n, $unit] = $rangeParsed;

        $sql = "
          SELECT
            FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(created_at)/:bucket)*:bucket) AS created_at,
            AVG(fukt) AS fukt,
            MIN(fukt) AS fukt_min,
            MAX(fukt) AS fukt_max,
            COUNT(*)  AS n
          FROM $table
          WHERE created_at >= (NOW() - INTERVAL $n $unit)
          GROUP BY created_at
          ORDER BY created_at ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':bucket' => $bucketSec]);
        $rows = $stmt->fetchAll();

        echo json_encode([
          'success' => true,
          'mode' => 'bucket',
          'range' => $range,
          'bucket' => $bucket,
          'data' => $rows
        ]);
        break;
      }

      // ---------- Raw mode ----------
      if ($limit !== null) {
        $stmt = $pdo->prepare("SELECT id, fukt, created_at FROM $table ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
      } else {
        $stmt = $pdo->query("SELECT id, fukt, created_at FROM $table ORDER BY created_at DESC");
        $rows = $stmt->fetchAll();
      }

      echo json_encode([
        'success' => true,
        'mode' => 'raw',
        'data' => $rows
      ]);
    } catch (PDOException $e) {
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;

  // =========================
  // POST: spara mätvärde
  // =========================
  case 'POST':
    try {
      // Support: JSON body eller form-data
      $j = readJsonBody();

      $fukt = null;

      if (is_array($j)) {
        // tillåt flera möjliga nycklar
        if (isset($j['fukt'])) $fukt = $j['fukt'];
        else if (isset($j['moisture'])) $fukt = $j['moisture'];
        else if (isset($j['value'])) $fukt = $j['value'];
      }

      // fallback: form-data / query
      if ($fukt === null) {
        if (isset($_POST['fukt'])) $fukt = $_POST['fukt'];
        else if (isset($_POST['moisture'])) $fukt = $_POST['moisture'];
        else if (isset($_POST['value'])) $fukt = $_POST['value'];
      }

      if ($fukt === null || !is_numeric($fukt)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing/invalid fukt']);
        break;
      }

      $fukt = (int)round($fukt);

      $stmt = $pdo->prepare("INSERT INTO $table (fukt, created_at) VALUES (:fukt, NOW())");
      $stmt->execute([':fukt' => $fukt]);

      echo json_encode([
        'success' => true,
        'inserted_id' => $pdo->lastInsertId(),
        'fukt' => $fukt
      ]);
    } catch (PDOException $e) {
      http_response_code(500);
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    break;

  // =========================
  // DELETE: valfritt
  // =========================
  case 'DELETE':
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'DELETE not enabled']);
    break;

  default:
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}
