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
        $dayId = (int)($_POST['dayId'] ?? 0);
        $dishId = (int)($_POST['dishId'] ?? 0);
        if ($dayId > 0 && $dishId > 0) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO DayMenuItem (dayId, dishId) VALUES (?, ?)");
            $stmt->execute([$dayId, $dishId]);
        }
    } elseif ($action === 'remove') {
        $dayMenuItemId = (int)($_POST['dayMenuItemId'] ?? 0);
        if ($dayMenuItemId > 0) {
            $stmt = $pdo->prepare("DELETE FROM DayMenuItem WHERE dayMenuItemId = ?");
            $stmt->execute([$dayMenuItemId]);
        }
    }
    
    header('Location: /admin/menu.php?day=' . (int)($_POST['dayId'] ?? 0));
    exit;
}

$days = $pdo->query("SELECT * FROM Day ORDER BY sortOrder")->fetchAll();
$allDishes = $pdo->query("SELECT * FROM Dish ORDER BY dishName")->fetchAll();

$dayId = (int)($_GET['day'] ?? ($days[0]['dayId'] ?? 0));

$menuItems = [];
if ($dayId > 0) {
    $stmt = $pdo->prepare("
        SELECT dmi.dayMenuItemId, d.dishName, d.dishId 
        FROM DayMenuItem dmi 
        JOIN Dish d ON dmi.dishId = d.dishId 
        WHERE dmi.dayId = ?
    ");
    $stmt->execute([$dayId]);
    $menuItems = $stmt->fetchAll();
}

render_header('Manage Menu');
?>
<div class="grid">
  <aside class="card">
    <h2 class="section-title">Select Day</h2>
    <div class="sidebar-list">
      <?php foreach ($days as $d): ?>
        <a href="?day=<?= $d['dayId'] ?>" class="btn btn-justify-start <?= ($d['dayId']==$dayId) ? 'btn-primary' : '' ?>">
          <?= h($d['dayName']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="card">
    <h2 class="section-title">Day's Menu Items</h2>
    <?php if (!$menuItems): ?>
      <p class="muted">No dishes scheduled.</p>
    <?php else: ?>
      <table class="table table-compact">
        <?php foreach ($menuItems as $item): ?>
          <tr>
            <td><?= h($item['dishName']) ?></td>
            <td class="align-right">
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="remove" />
                <input type="hidden" name="dayMenuItemId" value="<?= $item['dayMenuItemId'] ?>" />
                <input type="hidden" name="dayId" value="<?= $dayId ?>" />
                <button type="submit" class="btn">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
    
    <hr class="rule" />
    
    <h3 class="section-title">Add Dish to Menu</h3>
    <form method="post" class="form-row">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="add" />
      <input type="hidden" name="dayId" value="<?= $dayId ?>" />
      <select name="dishId" required class="form-grow select-muted">
        <option value="" disabled selected>Select a dish...</option>
        <?php foreach ($allDishes as $dish): ?>
          <option value="<?= $dish['dishId'] ?>"><?= h($dish['dishName']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Add</button>
    </form>
  </section>
</div>
<?php render_footer(); ?>
