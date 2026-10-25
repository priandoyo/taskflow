<?php
require_once __DIR__ . '/functions.php';
$u = require_login();

if (!is_admin()) {
    http_response_code(403);
    exit('Admin access required.');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_user') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $chosen = array_unique(array_map(
                'intval', (array)($_POST['groups'] ?? [])
            ));

            if ($username === '' || strlen($password) < 8) {
                throw new RuntimeException(
                    'Enter a username and password of at least 8 characters.'
                );
            }
            if (!in_array($role, ['admin', 'user', 'viewer'], true)) {
                throw new RuntimeException('Invalid role.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO users(username, password, role)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $role
            ]);
            $newId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO user_groups(user_id, group_id) VALUES (?, ?)'
            );
            foreach ($chosen as $gid) {
                $stmt->execute([$newId, $gid]);
            }
            $message = 'User created.';
        } elseif ($action === 'create_group') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                throw new RuntimeException('Group name is required.');
            }
            $stmt = $pdo->prepare('INSERT INTO groups(name) VALUES (?)');
            $stmt->execute([$name]);
            $message = 'Group created.';
        } elseif ($action === 'assign_groups') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $chosen = array_unique(array_map(
                'intval', (array)($_POST['groups'] ?? [])
            ));

            $stmt = $pdo->prepare('SELECT id FROM users WHERE id=?');
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('User not found.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM user_groups WHERE user_id=?');
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare(
                'INSERT INTO user_groups(user_id, group_id) VALUES (?, ?)'
            );
            foreach ($chosen as $gid) {
                $stmt->execute([$userId, $gid]);
            }
            $pdo->commit();
            $message = 'Group memberships updated.';
        } elseif ($action === 'toggle_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId === (int)$u['id']) {
                throw new RuntimeException('You cannot deactivate yourself.');
            }
            $stmt = $pdo->prepare(
                'UPDATE users SET active = 1 - active WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $message = 'User status changed.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof PDOException
            ? 'Could not save. Check for a duplicate username or group name.'
            : $e->getMessage();
    }
}

$allGroups = groups();
$users = $pdo->query(
    'SELECT id, username, role, active FROM users ORDER BY username'
)->fetchAll();

page_start('Administration');
?>
<p class="eyebrow">SYSTEM SETTINGS</p>
<h1 class="mb-4">Administration</h1>

<?php if ($message): ?>
  <div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <section class="panel mb-4">
      <h2 class="h5 mb-3">Create user</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_user">

        <label class="form-label">Username</label>
        <input class="form-control mb-3" name="username" required>

        <label class="form-label">Initial password (8+ characters)</label>
        <input class="form-control mb-3" name="password"
               type="password" minlength="8" required>

        <label class="form-label">Role</label>
        <select class="form-select mb-3" name="role">
          <option value="user">User</option>
          <option value="viewer">Viewer</option>
          <option value="admin">Admin</option>
        </select>

        <label class="form-label">Group membership</label>
        <?php foreach ($allGroups as $g): ?>
          <label class="form-check">
            <input class="form-check-input" type="checkbox"
                   name="groups[]" value="<?= (int)$g['id'] ?>">
            <?= h($g['name']) ?>
          </label>
        <?php endforeach; ?>
        <button class="btn btn-primary mt-3">Create user</button>
      </form>
    </section>

    <section class="panel">
      <h2 class="h5 mb-3">Create group</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_group">
        <input class="form-control mb-3" name="name"
               placeholder="e.g. IT Operations" required>
        <button class="btn btn-outline-primary">Add group</button>
      </form>
    </section>
  </div>

  <div class="col-lg-7">
    <section class="panel">
      <h2 class="h5 mb-3">Users and access</h2>
      <?php foreach ($users as $person): ?>
        <?php
        $stmt = $pdo->prepare(
            'SELECT group_id FROM user_groups WHERE user_id=?'
        );
        $stmt->execute([$person['id']]);
        $memberIds = array_map('intval',
            array_column($stmt->fetchAll(), 'group_id'));
        ?>
        <div class="user-card mb-3">
          <div class="d-flex justify-content-between">
            <strong><?= h($person['username']) ?></strong>
            <span class="badge text-bg-light">
              <?= h($person['role']) ?>
              <?= $person['active'] ? '' : ' · Inactive' ?>
            </span>
          </div>

          <form method="post" class="mt-2">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="assign_groups">
            <input type="hidden" name="user_id"
                   value="<?= (int)$person['id'] ?>">
            <div class="row">
              <?php foreach ($allGroups as $g): ?>
                <div class="col-md-6">
                  <label class="form-check small">
                    <input class="form-check-input" type="checkbox"
                           name="groups[]" value="<?= (int)$g['id'] ?>"
                           <?= in_array((int)$g['id'], $memberIds, true)
                               ? 'checked' : '' ?>>
                    <?= h($g['name']) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-outline-primary mt-2">
              Save memberships
            </button>
          </form>

          <?php if ((int)$person['id'] !== (int)$u['id']): ?>
            <form method="post" class="mt-2">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_user">
              <input type="hidden" name="user_id"
                     value="<?= (int)$person['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary">
                <?= $person['active'] ? 'Deactivate' : 'Reactivate' ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  </div>
</div>
<?php page_end(); ?>
