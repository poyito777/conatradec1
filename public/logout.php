<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/helpers/log.php';

session_start();

// Guardar info antes de destruir sesión
$userId = $_SESSION['user']['id'] ?? null;

if ($userId) {
    log_activity(
        $pdo,
        (int)$userId,
        'logout',
        'Cierre de sesión del usuario'
    );
}

session_destroy();

header("Location: index.php");
exit;