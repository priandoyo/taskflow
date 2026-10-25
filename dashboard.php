<?php
require_once __DIR__ . '/functions.php';
$u = require_login();

$visible = visible_groups();
$ids = array_column($visible, 'id');
$counts = array_fill_keys(statuses(), 0);
$total = 0;
$overdue = 0;

if ($ids) {
    $marks = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare(
        "SELECT a.status, COUNT(*) AS n
         FROM task_assignments a
         JOIN tasks t ON t.id = a.task_id
         WHERE a.group_id IN ($marks)
         GROUP BY a.status"
    );
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $r) {
        $counts[$r['status']] = (int)$r['n'];
        $total += (int)$r['n'];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM task_assignments a
         JOIN tasks t ON t.id = a.task_id
         WHERE a.group_id IN ($marks)
         AND t.due_date IS NOT NULL AND t.due_date < date('now')
         AND a.status != 'Completed'"
    );
    $stmt->execute($ids);
    $overdue = (int)$stmt->fetchColumn();
}

page_start('Dashboard');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <p class="eyebrow">OVERVIEW</p>
    <h1>Dashboard</h1>
    <p class="muted">Track progress across your groups.</p>
  </div>
  <a class="btn btn-primary" href="tasks.php?new=1">+ New task</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <span>Total assignments</span>
      <strong><?= $total ?></strong>
    </div>
  </div>
  <?php foreach ([
      'On plan' => 'Planned',
      'On development' => 'In development',
      'On testing' => 'Testing',
      'Completed' => 'Completed'
  ] as $status => $label): ?>
    <div class="col-6 col-lg-3">
      <div class="stat-card">
        <span><?= h($label) ?></span>
        <strong><?= $counts[$status] ?></strong>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <section class="panel">
      <h2 class="h5 mb-3">Progress by status</h2>
      <?php foreach ($counts as $status => $count): ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between mb-1">
            <span><?= h($status) ?></span>
            <strong><?= $count ?></strong>
          </div>
          <div class="progress">
            <div class="progress-bar"
                 style="width:<?= $total ? round($count / $total * 100) : 0 ?>%">
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <p class="small muted mb-0">
        Counts represent group assignments, not unique tasks.
      </p>
    </section>
  </div>
  <div class="col-lg-4">
    <section class="panel">
      <h2 class="h5">Your groups</h2>
      <?php if (!$visible): ?>
        <p class="muted">No groups assigned.</p>
      <?php endif; ?>
      <?php foreach ($visible as $g): ?>
        <div class="group-row">
          <span class="group-dot"></span><?= h($g['name']) ?>
        </div>
      <?php endforeach; ?>
      <hr>
      <div class="d-flex justify-content-between">
        <span>Overdue assignments</span>
        <strong class="text-danger"><?= $overdue ?></strong>
      </div>
      <a href="tasks.php" class="btn btn-outline-primary w-100 mt-3">
        View tasks
      </a>
    </section>
  </div>
</div>
<?php page_end(); ?>
