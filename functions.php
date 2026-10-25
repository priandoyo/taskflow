<?php
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function user(): ?array {
    global $pdo;
    if (empty($_SESSION['user_id'])) return null;

    $stmt = $pdo->prepare(
        'SELECT id, username, role FROM users
         WHERE id = ? AND active = 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): array {
    $u = user();
    if (!$u) {
        header('Location: index.php');
        exit;
    }
    return $u;
}

function is_admin(): bool {
    return (user()['role'] ?? '') === 'admin';
}

function can_access_group(int $groupId): bool {
    global $pdo;

    if (is_admin()) return true;
    $u = user();
    if (!$u) return false;

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM user_groups
         WHERE user_id = ? AND group_id = ?'
    );
    $stmt->execute([$u['id'], $groupId]);
    return (int)$stmt->fetchColumn() > 0;
}

function groups(): array {
    global $pdo;
    return $pdo->query('SELECT * FROM groups ORDER BY name')->fetchAll();
}

function visible_groups(): array {
    if (is_admin()) return groups();

    global $pdo;
    $u = user();
    if (!$u) return [];

    $stmt = $pdo->prepare(
        'SELECT g.* FROM groups g
         JOIN user_groups ug ON ug.group_id = g.id
         WHERE ug.user_id = ? ORDER BY g.name'
    );
    $stmt->execute([$u['id']]);
    return $stmt->fetchAll();
}

function task_groups(int $taskId): array {
    global $pdo;
    $result = [];

    $stmt = $pdo->prepare(
        'SELECT g.id, g.name, a.status, a.progress
         FROM task_assignments a
         JOIN groups g ON g.id = a.group_id
         WHERE a.task_id = ? ORDER BY g.name'
    );
    $stmt->execute([$taskId]);

    foreach ($stmt->fetchAll() as $row) {
        if (can_access_group((int)$row['id'])) {
            $result[] = $row;
        }
    }
    return $result;
}

function can_view_task(int $taskId): bool {
    return count(task_groups($taskId)) > 0;
}

function statuses(): array {
    return ['On plan', 'On development', 'On testing', 'Completed'];
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void {
    if (
        !isset($_POST['csrf']) ||
        !hash_equals(csrf_token(), (string)$_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Invalid request token. Go back and try again.');
    }
}

function page_start(string $title): void {
    $u = user();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= h($title) ?> · TaskFlow</title>
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet">
      <link href="style.css" rel="stylesheet">
    </head>
    <body>
    <?php if ($u): ?>
      <nav class="navbar navbar-expand-lg navbar-dark app-nav">
        <div class="container">
          <a class="navbar-brand fw-bold" href="dashboard.php">TaskFlow</a>
          <div class="d-flex flex-wrap gap-3 align-items-center">
            <a href="dashboard.php">Dashboard</a>
            <a href="tasks.php">Tasks</a>
            <?php if (is_admin()): ?>
              <a href="admin.php">Admin</a>
            <?php endif; ?>
            <span class="text-white-50"><?= h($u['username']) ?></span>
            <a href="index.php?logout=1">Logout</a>
          </div>
        </div>
      </nav>
    <?php endif; ?>
    <main class="container py-4">
    <?php
}

function page_end(): void {
    echo '</main></body></html>';
}
