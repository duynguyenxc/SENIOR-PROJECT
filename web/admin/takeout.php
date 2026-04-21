<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/db.php';
require_super_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['setName'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $avail = isset($_POST['isAvailable']) ? 1 : 0;
        
        if ($name && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO TakeoutSet (setName, description, price, isAvailable) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $price, $avail]);
        }
    } elseif ($action === 'toggle') {
        $setId = (int)($_POST['setId'] ?? 0);
        $avail = (int)($_POST['isAvailable'] ?? 0);
        if ($setId > 0) {
            $stmt = $pdo->prepare("UPDATE TakeoutSet SET isAvailable = ? WHERE setId = ?");
            $stmt->execute([$avail, $setId]);
        }
    }
    header('Location: /admin/takeout.php');
    exit;
}

$sets = $pdo->query("SELECT * FROM TakeoutSet ORDER BY setId DESC")->fetchAll();

render_header('Manage Takeout Sets');
?>
<div class="grid">
  <section class="card">
    <h2 class="section-title">Takeout Sets</h2>
    <table class="table table-compact">
      <tr class="table-header-row">
        <th>Name</th>
        <th>Price</th>
        <th>Status</th>
        <th class="align-right">Action</th>
      </tr>
      <?php foreach ($sets as $set): ?>
        <tr>
          <td>
            <strong><?= h($set['setName']) ?></strong><br>
            <small class="muted"><?= h($set['description']) ?></small>
          </td>
          <td>$<?= h($set['price']) ?></td>
          <td>
             <?php if ($set['isAvailable']): ?>
               <span class="status-text-ok">Available</span>
             <?php else: ?>
               <span class="status-text-danger">Hidden</span>
             <?php endif; ?>
          </td>
          <td class="align-right">
             <form method="post" class="form-inline">
               <?= csrf_input() ?>
               <input type="hidden" name="action" value="toggle" />
               <input type="hidden" name="setId" value="<?= $set['setId'] ?>" />
               <input type="hidden" name="isAvailable" value="<?= $set['isAvailable'] ? 0 : 1 ?>" />
               <button class="btn" type="submit"><?= $set['isAvailable'] ? 'Disable' : 'Enable' ?></button>
             </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>
  
  <aside class="card">
    <h2 class="section-title">Add New Set</h2>
    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="add" />
      <div class="field">
        <label>Name</label>
        <input type="text" name="setName" required />
      </div>
      <div class="field">
        <label>Description (ingredients)</label>
        <input type="text" name="description" required />
      </div>
      <div class="field">
        <label>Price ($)</label>
        <input type="number" step="0.01" name="price" required />
      </div>
      <div class="checkbox-row">
        <label><input type="checkbox" name="isAvailable" checked /> Available Immediately</label>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Add Set</button>
    </form>
  </aside>
</div>
<?php render_footer(); ?>
