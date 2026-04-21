<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_header(string $title): void {
  $cust = current_customer();
  $admin = current_admin();
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="/assets/style.css" />
  </head>
  <body>
    <header class="topbar">
      <div class="container topbar-inner">
        <div class="brand">
          <a href="/" class="brand-link">Veg Buffet</a>
        </div>
        <nav class="nav">
          <?php if ($admin): ?>
            <a href="/admin/index.php" class="nav-link">Orders</a>
            <?php if (is_super_admin($admin)): ?>
              <a href="/admin/reports.php" class="nav-link">Reports</a>
              <a href="/admin/staff.php" class="nav-link">Manage Staff</a>
              <a href="/admin/menu.php" class="nav-link">Menu Builder</a>
              <a href="/admin/takeout.php" class="nav-link">Takeout Sets</a>
              <a href="/admin/history.php" class="nav-link">Order History</a>
            <?php endif; ?>
            <span class="nav-text nav-role"><?= h($admin['role']) ?></span>
            <a class="nav-link nav-link-spaced" href="/logout.php">Logout</a>
          <?php else: ?>
            <a href="/" class="nav-link">Home</a>
            <a href="/menu.php" class="nav-link">Menu</a>
            <a href="/takeout.php" class="nav-link">Takeout</a>
            <a href="/cart.php" class="nav-link">Cart (<?= isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0 ?>)</a>
            <a href="/admin/login.php" class="nav-link">Staff Portal</a>
            <?php if ($cust): ?>
              <a href="/my_orders.php" class="nav-link">My Orders</a>
              <span class="nav-text nav-greeting">Hi, <?= h($cust['fullName']) ?></span>
              <a class="nav-link" href="/logout.php">Logout</a>
            <?php else: ?>
              <a class="nav-link" href="/login.php">Login</a>
              <a class="nav-link nav-link-primary" href="/register.php">Create account</a>
            <?php endif; ?>
          <?php endif; ?>
        </nav>
      </div>
    </header>
    <main class="container">
  <?php
}

function render_footer(): void {
  ?>
    </main>
  </body>
  </html>
  <?php
}

