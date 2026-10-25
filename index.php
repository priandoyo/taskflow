<?php
require_once __DIR__ . '/functions.php';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $stmt = $pdo->prepare(
        'SELECT * FROM users WHERE username = ? AND active = 1'
    );
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $u = $stmt->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}

if (user()) {
    header('Location: dashboard.php');
    exit;
}

page_start('Login');
?>
<div class="login-wrap">
  <div class="login-card">
    <div class="brand-mark">T</div>
    <h1>Welcome to TaskFlow</h1>
    <p class="muted">Group Task &amp; Action Tracker</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <label class="form-label">Username</label>
      <input class="form-control mb-3" name="username" required autofocus>
      <label class="form-label">Password</label>
      <input class="form-control mb-4" type="password"
             name="password" required>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>
    <p class="small muted mt-3 mb-0">
      Workshop demo: admin / admin123
    </p>
  </div>
</div>
<?php page_end(); ?>
