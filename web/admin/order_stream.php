<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/orders.php';

require_admin();
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$pdo = db();
$statuses = order_active_queue_statuses();

$versionForQueue = static function () use ($pdo, $statuses): string {
  $stmt = $pdo->prepare(
    'SELECT
       COUNT(*) AS orderCount,
       COALESCE(MAX(updatedTime), "0") AS lastUpdated
     FROM `Order`
     WHERE status IN (' . sql_placeholders($statuses) . ')'
  );
  $stmt->execute($statuses);
  $row = $stmt->fetch() ?: ['orderCount' => 0, 'lastUpdated' => '0'];

  return (string)$row['lastUpdated'] . '|' . (string)$row['orderCount'];
};

$lastVersion = null;
for ($attempt = 0; $attempt < 15; $attempt++) {
  $version = $versionForQueue();
  if ($version !== $lastVersion) {
    echo "event: orders-update\n";
    echo 'data: ' . json_encode(['version' => $version], JSON_UNESCAPED_SLASHES) . "\n\n";
    @ob_flush();
    flush();
    $lastVersion = $version;
  } else {
    echo ": heartbeat\n\n";
    @ob_flush();
    flush();
  }

  sleep(2);
}
