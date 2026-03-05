<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Bad request"); }

$st = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id=? LIMIT 1");
$st->execute([$id]);
$u = $st->fetch();

if (!$u) { http_response_code(404); exit("No existe"); }
if ($u['role'] !== 'teacher') { http_response_code(403); exit("Solo docentes."); }

function gen_password(int $len=10): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#';
  $p=''; for($i=0;$i<$len;$i++) $p.=$alphabet[random_int(0, strlen($alphabet)-1)];
  return $p;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$newPlain = gen_password(10);
$newHash  = password_hash($newPlain, PASSWORD_DEFAULT);

// must_change_password = 1
$up = $pdo->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=?");
$up->execute([$newHash, $id]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restablecer contraseña</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:760px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    code{font-size:18px}
    .box{margin-top:12px;padding:14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10)}
  </style>
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div class="logo">
        <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
        <span>CONATRADEC • Docentes</span>
      </div>
      <div class="nav">
        <a href="teachers.php">Docentes</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <h2 style="margin:0 0 6px;">Contraseña restablecida ✅</h2>
        <p style="margin:0;color:var(--muted);">El docente estará obligado a cambiarla en su próximo inicio de sesión.</p>

        <div class="box">
          <p style="margin:0 0 8px;"><b>Docente:</b> <?= h($u['name']) ?> (<?= h($u['email']) ?>)</p>
          <p style="margin:0;"><b>Nueva contraseña temporal:</b> <code><?= h($newPlain) ?></code></p>
        </div>

        <div style="margin-top:14px;">
          <a href="teachers.php">← Volver</a>
        </div>
      </section>
    </main>
  </div>
</body>
</html>