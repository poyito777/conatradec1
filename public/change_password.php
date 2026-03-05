<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
$me = $_SESSION['user'];
$force = ((int)($me['must_change_password'] ?? 0) === 1);

$error = '';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $current = (string)($_POST['current_password'] ?? '');
  $new1 = (string)($_POST['new_password'] ?? '');
  $new2 = (string)($_POST['new_password2'] ?? '');

  if ($new1 === '' || $new2 === '') $error = "Completá la nueva contraseña.";
  elseif ($new1 !== $new2) $error = "Las nuevas contraseñas no coinciden.";
  elseif (strlen($new1) < 8) $error = "Mínimo 8 caracteres.";
  else {
    $st = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
    $st->execute([(int)$me['id']]);
    $u = $st->fetch();
    if (!$u) { http_response_code(404); exit("Usuario no existe"); }

    if (!password_verify($current, $u['password_hash'])) {
      $error = "La contraseña actual no es correcta.";
    } else {
      $newHash = password_hash($new1, PASSWORD_DEFAULT);
      $up = $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=0 WHERE id=?");
      $up->execute([$newHash, (int)$me['id']]);
      $_SESSION['user']['must_change_password'] = 0;
      header("Location: dashboard.php"); exit;
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cambiar contraseña</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:600px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .note{color:var(--muted);margin:0 0 12px}
  </style>
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div class="logo">
        <img src="/docentes/assets/img/logo-conatradec.png" alt="CONATRADEC">
        <span>CONATRADEC • Docentes</span>
      </div>
      <div class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="students.php">Estudiantes</a>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <h2 style="margin:0 0 6px;">Cambiar contraseña</h2>
        <?php if ($force): ?>
          <p class="note">Por seguridad, debés cambiar tu contraseña para continuar.</p>
        <?php else: ?>
          <p class="note">Recomendación: usá una contraseña fuerte (8+ caracteres).</p>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="field">
            <label>Contraseña actual</label>
            <input type="password" name="current_password" required>
          </div>

          <div class="field">
            <label>Nueva contraseña</label>
            <input type="password" name="new_password" required>
          </div>

          <div class="field">
            <label>Confirmar nueva contraseña</label>
            <input type="password" name="new_password2" required>
          </div>

          <button class="btn" type="submit">Guardar</button>
        </form>

        <?php if (!$force): ?>
          <div style="margin-top:12px;"><a href="dashboard.php">← Volver</a></div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>