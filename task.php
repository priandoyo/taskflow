<?php
require_once __DIR__ . '/functions.php';
$u = require_login();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t || !can_view_task($id)) {
    http_response_code(404);
    exit('Task not found or access denied.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if ($u['role'] === 'viewer') {
        http_response_code(403);
        exit('Viewers cannot update tasks.');
    }

    $aid = (int)($_POST['assignment_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $progress = trim($_POST['progress'] ?? '');

    if (!in_array($status, statuses(), true)) {
        $error = 'Invalid status.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM task_assignments
             WHERE id = ? AND task_id = ?'
        );
        $stmt->execute([$aid, $id]);
        $a = $stmt->fetch();

        if (!$a || !can_access_group((int)$a['group_id'])) {
            http_response_code(403);
            exit('You cannot update this group.');
        }

        if (!$error) {
            $pdo->beginTransaction();
            try {
                if ($a['status'] !== $status ||
                    $a['progress'] !== $progress) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO task_status_history
                         (assignment_id, old_status, new_status,
                          description, updated_by)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $aid, $a['status'], $status, $progress, $u['id']
                    ]);

                    $stmt = $pdo->prepare(
                        'UPDATE task_assignments
                         SET status=?, progress=?, updated_by=?,
                             updated_at=CURRENT_TIMESTAMP
                         WHERE id=?'
                    );
                    $stmt->execute([
                        $status, $progress, $u['id'], $aid
                    ]);
                }
                $pdo->commit();
                header('Location: task.php?id=' . $id);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}

$assignments = task_groups($id);

page_start('Task details');
?>
<a href="tasks.php" class="text-decoration-none">&larr; Back to tasks</a>

<div class="d-flex justify-content-between align-items-start mt-3 mb-4">
  <div>
    <p class="eyebrow">TASK #<?= $id ?></p>
    <h1><?= h($t['title']) ?></h1>
    <p class="muted"><?= nl2br(h($t['description'])) ?></p>
    <p class="small muted">Due: <?= h($t['due_date'] ?: 'Not set') ?></p>
  </div>
  <a class="btn btn-outline-primary" href="tasks.php?edit=<?= $id ?>">Edit task</a>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<h2 class="h4 mb-3">Group progress</h2>
<?php foreach ($assignments as $g): ?>
  <?php
  $stmt = $pdo->prepare(
      'SELECT * FROM task_assignments
       WHERE task_id = ? AND group_id = ?'
  );
  $stmt->execute([$id, $g['id']]);
  $a = $stmt->fetch();
  ?>
  <section class="panel mb-3">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
      <h3 class="h5 mb-0"><?= h($g['name']) ?></h3>
      <span class="status-pill"><?= h($g['status']) ?></span>
    </div>

    <?php if ($u['role'] !== 'viewer'): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="assignment_id" value="<?= (int)$a['id'] ?>">
        <label class="form-label">Status</label>
        <select class="form-select mb-3" name="status">
          <?php foreach (statuses() as $s): ?>
            <option value="<?= h($s) ?>"
              <?= $s === $g['status'] ? 'selected' : '' ?>>
              <?= h($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label class="form-label">Progress description</label>
        <textarea class="form-control mb-3" name="progress" rows="2"
          placeholder="Describe progress, blockers, or next steps..."><?= h($g['progress']) ?></textarea>
        <button class="btn btn-primary btn-sm">Update progress</button>
      </form>
    <?php else: ?>
      <p><?= nl2br(h($g['progress'] ?: 'No progress description yet.')) ?></p>
    <?php endif; ?>

    <details class="mt-3">
      <summary>View status history</summary>
      <?php
      $stmt = $pdo->prepare(
          'SELECT h.*, u.username FROM task_status_history h
           LEFT JOIN users u ON u.id = h.updated_by
           WHERE h.assignment_id = ? ORDER BY h.id DESC'
      );
      $stmt->execute([$a['id']]);
      $history = $stmt->fetchAll();
      ?>
      <?php if (!$history): ?>
        <p class="small muted mt-2">No updates recorded yet.</p>
      <?php endif; ?>
      <?php foreach ($history as $item): ?>
        <div class="history-item mt-2">
          <strong><?= h($item['old_status'] ?: 'Created') ?>
            &rarr; <?= h($item['new_status']) ?></strong>
          <div><?= nl2br(h($item['description'])) ?></div>
          <small class="muted">
            <?= h($item['username'] ?? 'Unknown') ?> ·
            <?= h($item['updated_at']) ?>
          </small>
        </div>
      <?php endforeach; ?>
    </details>
  </section>
<?php endforeach; ?>

<?php page_end(); ?>
