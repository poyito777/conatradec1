<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

if (($me['role'] ?? '') !== 'admin') {
    exit('Acceso denegado');
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('ID inválido');
}

$stmt = $pdo->prepare("
    UPDATE students
    SET is_historical = 0
    WHERE id = ?
");
$stmt->execute([$id]);

header("Location: students.php");
exit;