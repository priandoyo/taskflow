
<?php
require_once __DIR__ . '/functions.php';
$u = require_login();
$allowedGroups = visible_groups();
$error = '';

$editId = (int)($_GET['edit'] ?? 0);
$editing = null;
$selected = [];

if ($editId) {
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch();

    if (!$editing || !can_view_task($editId)) {
        http_response_code(403);
        exit('Task not found or access denied.');
    }

    $selected = array_map(
        'intval',
        array_column(task_groups($editId), 'id')
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');
    $chosen = array_unique(array_map(
        'intval',
        (array)($_POST['groups'] ?? [])
    ));

    if ($title === '') {
        $error = 'Task title is required.';
    } elseif (!$id && !$chosen) {
        $error = 'Select at least one group.';
    } else {
        foreach ($chosen as $gid) {
            if (!can_access_group($gid)) {
                $error = 'You cannot assign this group.';
                break;
            }
        }

        if ($id && !can_view_task($id)) {
            http_response_code(403);
            exit('Access denied.');
        }

        if ($id && !is_admin()) {
            // Non-admin users can edit task details, but not assignments.
            $chosen = [];
        }

        if (!$error) {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE tasks SET title=?, description=?, due_date=?
                     WHERE id=?'
                );
                $stmt->execute([
                    $title, $description, $dueDate ?: null, $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO tasks(title, description, due_date, created_by)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([
                    $title, $description, $dueDate ?: null, $u['id']
                ]);
                $id = (int)$pdo->lastInsertId();
            }

            if ($chosen) {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM task_assignments WHERE task_id = ?'
                    );
                    $stmt->execute([$id]);

                    $stmt = $pdo->prepare(
                        'INSERT INTO task_assignments(task_id, group_id)
                         VALUES (?, ?)'
                    );
                    foreach ($chosen as $gid) {
                        $stmt->execute([$id, $gid]);
                    }
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }

            header('Location: task.php?id=' . $id);
            exit;
        }
    }
}

$showForm = isset($_GET['new']) || $editId || $_SERVER['REQUEST_METHOD'] === 'POST';
$search = trim($_GET['q'] ?? '');
$visibleIds = array_column($allowedGroups, 'id');
$rows = [];

if ($visibleIds) {
    $marks = implode(',', array_fill(0, count($visibleIds), '?'));
    $sql = "SELECT DISTINCT t.* FROM tasks t
            JOIN task_assignments a ON a.task_id = t.id
            WHERE a.group_id IN ($marks)";
    $params = $visibleIds;

    if ($search !== '') {
        $sql .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY t.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

page_start('Tasks');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><p class="eyebrow">WORK MANAGEMENT</p><h1>Tasks</h1></div>
  <a class="btn btn-primary" href="tasks.php?new=1">+ New task</a>
</div>

<form class="search-bar mb-3" method="get">
  <input class="form-control" name="q" placeholder="Search tasks..."
         value="<?= h($search) ?>">
  <button class="btn btn-outline-primary">Search</button>
</form>

<?php if ($showForm): ?>
  <section class="panel mb-4">
    <h2 class="h5"><?= $editing ? 'Edit task' : 'Create task' ?></h2>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

      <label class="form-label">Task title</label>
      <input class="form-control mb-3" name="title" required
             value="<?= h($editing['title'] ?? $_POST['title'] ?? '') ?>">

      <label class="form-label">Description</label>
      <textarea class="form-control mb-3" rows="3"
                name="description"><?= h($editing['description'] ?? $_POST['description'] ?? '') ?></textarea>

      <label class="form-label">Due date</label>
      <input class="form-control mb-3" type="date" name="due_date"
             value="<?= h($editing['due_date'] ?? '') ?>">

      <?php if (!$editing || is_admin()): ?>
        <label class="form-label">Assign groups</label>
        <div class="row mb-3">
          <?php foreach ($allowedGroups as $g): ?>
            <div class="col-md-6">
              <label class="form-check">
                <input class="form-check-input" type="checkbox"
                       name="groups[]" value="<?= (int)$g['id'] ?>"
                       <?= in_array((int)$g['id'], $selected, true) ||
                           in_array((int)$g['id'], array_map('intval',
                           (array)($_POST['groups'] ?? [])), true)
                           ? 'checked' : '' ?>>
                <?= h($g['name']) ?>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Group assignments can only be changed by an admin.</p>
      <?php endif; ?>

      <button class="btn btn-primary">Save task</button>
      <a class="btn btn-light" href="tasks.php">Cancel</a>
    </form>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Task</th><th>Groups</th><th>Due date</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): $tg = task_groups((int)$t['id']); ?>
        <tr>
          <td>
            <a class="fw-semibold" href="task.php?id=<?= (int)$t['id'] ?>">
              <?= h($t['title']) ?>
            </a>
            <div class="small muted"><?= h($t['description']) ?></div>
          </td>
          <td>
            <?php foreach ($tg as $g): ?>
              <span class="badge text-bg-light"><?= h($g['name']) ?></span>
            <?php endforeach; ?>
          </td>
          <td><?= h($t['due_date'] ?: '—') ?></td>
          <td><a href="tasks.php?edit=<?= (int)$t['id'] ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-center muted py-4">No tasks found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php page_end(); ?>
