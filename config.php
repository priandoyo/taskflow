<?php
declare(strict_types=1);

$dbFile = __DIR__ . '/taskflow.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
$stmt->execute();

if ((int)$stmt->fetchColumn() === 0) {
    $stmt = $pdo->prepare(
        'INSERT INTO users(username, password, role)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([
        'admin',
        password_hash('admin123', PASSWORD_DEFAULT),
        'admin'
    ]);
}
