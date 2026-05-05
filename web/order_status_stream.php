<?php
declare(strict_types=1);

// Server-Sent Events (SSE) endpoint for the customer "My Orders" page.
// Polls the database every 2 seconds and pushes an event when order data changes,
// which triggers a page reload on the client.

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/orders.php';

$customer = require_customer('/login.php?redirect=%2Fmy_orders.php');
session_write_close();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$pdo = db();
$customerId = (int)$customer['customerId'];

$versionForCustomer = static function () use ($pdo, $customerId): string {
  $stmt = $pdo->prepare(
    'SELECT
       COUNT(*) AS orderCount,
       COALESCE(MAX(updatedTime), "0") AS lastUpdated
     FROM `Order`
     WHERE customerId = ?'
  );
  $stmt->execute([$customerId]);
  $row = $stmt->fetch() ?: ['orderCount' => 0, 'lastUpdated' => '0'];

  return (string)$row['lastUpdated'] . '|' . (string)$row['orderCount'];
};

$lastVersion = null;
for ($attempt = 0; $attempt < 15; $attempt++) {
  $version = $versionForCustomer();
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
