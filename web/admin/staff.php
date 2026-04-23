<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/staff.php';

$superAdmin = require_super_admin();
$pdo = db();

ensure_session_started();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();

    $action = (string)($_POST['action'] ?? '');
    $staffId = (int)($_POST['staffId'] ?? 0);

    try {
        if ($action === 'create') {
            create_staff_account(
                $pdo,
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? '')
            );
            $_SESSION['staff_flash'] = [
                'type' => 'ok',
                'message' => 'Staff account created successfully.',
            ];
        } elseif ($action === 'toggle' && $staffId > 0) {
            $isActive = ((int)($_POST['isActive'] ?? 0)) === 1;
            set_staff_account_active($pdo, $staffId, $isActive);
            $_SESSION['staff_flash'] = [
                'type' => 'ok',
                'message' => $isActive ? 'Staff account activated.' : 'Staff account disabled.',
            ];
        } elseif ($action === 'reset_password' && $staffId > 0) {
            $temporaryPassword = reset_staff_account_password($pdo, $staffId);
            $_SESSION['staff_flash'] = [
                'type' => 'ok',
                'message' => 'Temporary password: ' . $temporaryPassword,
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['staff_flash'] = [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ];
    }

    header('Location: /admin/staff.php');
    exit;
}

$flash = $_SESSION['staff_flash'] ?? null;
unset($_SESSION['staff_flash']);

$staffAccounts = fetch_staff_accounts($pdo);

render_header('Manage Staff');
?>
<section class="hero hero-compact">
  <h2>Manage Staff Accounts</h2>
  <p class="muted">Only the super admin can create staff accounts, disable access, or issue temporary passwords.</p>
</section>

<?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
  <div class="alert alert-<?= h((string)$flash['type']) ?> stack-lg"><?= h((string)$flash['message']) ?></div>
<?php endif; ?>

<div class="grid stack-lg">
  <section class="card">
    <h2 class="section-title">Current Staff Accounts</h2>
    <?php if ($staffAccounts === []): ?>
      <div class="alert">No staff accounts have been created yet.</div>
    <?php else: ?>
      <div class="table-shell">
        <table class="table">
          <thead>
            <tr>
              <th>Email</th>
              <th>Status</th>
              <th>Created</th>
              <th class="align-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($staffAccounts as $staff): ?>
              <?php $isActive = (bool)$staff['isActive']; ?>
              <tr>
                <td><?= h((string)$staff['email']) ?></td>
                <td>
                  <span class="<?= $isActive ? 'status-text-ok' : 'status-text-danger' ?>">
                    <?= $isActive ? 'Active' : 'Disabled' ?>
                  </span>
                </td>
                <td><?= h(date('M j, Y', strtotime((string)$staff['createdTime']))) ?></td>
                <td class="align-right">
                  <div class="order-actions">
                    <form method="post" class="form-inline">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="toggle" />
                      <input type="hidden" name="staffId" value="<?= (int)$staff['adminId'] ?>" />
                      <input type="hidden" name="isActive" value="<?= $isActive ? 0 : 1 ?>" />
                      <button type="submit" class="btn"><?= $isActive ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post" class="form-inline">
                      <?= csrf_input() ?>
                      <input type="hidden" name="action" value="reset_password" />
                      <input type="hidden" name="staffId" value="<?= (int)$staff['adminId'] ?>" />
                      <button type="submit" class="btn btn-warning-soft">Reset Password</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <aside class="card">
    <h2 class="section-title">Create Staff Account</h2>
    <p class="muted">Staff members can access the internal portal and order queue, but cannot manage reports, menu, or other internal accounts.</p>

    <form method="post">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="create" />
      <div class="field">
        <label for="staff-email">Staff Email</label>
        <input id="staff-email" name="email" type="email" required />
      </div>
      <div class="field">
        <label for="staff-password">Initial Password</label>
        <input id="staff-password" name="password" type="text" required minlength="8" />
      </div>
      <button type="submit" class="btn btn-primary btn-full">Create Staff</button>
    </form>

    <div class="alert alert-info stack-lg">
      <strong>Super Admin Account</strong><br>
      <?= h((string)$superAdmin['email']) ?><br>
      This account is the only one allowed to manage staff access.
    </div>
  </aside>
</div>

<?php render_footer(); ?>
