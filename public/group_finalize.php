<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: groups.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header("Location: groups.php");
    exit;
}

// Traer grupo
$stmt = $pdo->prepare("SELECT * FROM groups_table WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    header("Location: groups.php");
    exit;
}

// Permisos:
// admin puede finalizar cualquiera
// docente solo sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

// Si ya está finalizado, no hacer nada
if (($group['status'] ?? '') !== 'finalizado') {
    $upd = $pdo->prepare("UPDATE groups_table SET status = 'finalizado' WHERE id = ?");
    $upd->execute([$id]);
}

header("Location: groups.php?finalized=1");
exit;