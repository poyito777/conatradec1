<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$error = '';
$success = '';

function gen_password(int $len=10): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@#';
  $p = '';
  for ($i=0; $i<$len; $i++) $p .= $alphabet[random_int(0, strlen($alphabet)-1)];
  return $p;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if ($name === '' || $email === '') {
    $error = 'Completá nombre y email.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Email inválido.';
  } else {
    $chk = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) {
      $error = 'Ese email ya existe.';
    } else {
      $plain = gen_password(10);
      $hash  = password_hash($plain, PASSWORD_DEFAULT);

      // OJO: must_change_password = 1
      $ins = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_active,must_change_password) VALUES (?,?,?,?,1,1)");
      $ins->execute([$name, $email, $hash, 'teacher']);

      $success = "Docente creado ✅";
      $success .= "<br><b>Contraseña temporal:</b> <code>".h($plain)."</code>";
      $success .= "<br><span style='color:var(--muted)'>El docente será obligado a cambiarla al iniciar sesión.</span>";
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear docente</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:700px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .btnS{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800}
    code{font-size:16px}
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
        <h2 style="margin:0 0 6px;">Crear docente</h2>
        <p style="margin:0 0 14px;color:var(--muted);">Genera una contraseña temporal automáticamente.</p>

        <?php if ($error): ?>
          <div class="alert"><?= h($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
          <div style="margin-top:12px;padding:12px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);">
            <?= $success ?>
          </div>
        <?php endif; ?>

        <form method="post" style="margin-top:12px">
          <div class="field">
            <label>Nombre</label>
            <input name="name" required>
          </div>

          <div class="field">
            <label>Email</label>
            <input type="email" name="email" required placeholder="docente@conatradec.local">
          </div>

          <button class="btn" type="submit">Crear docente</button>
        </form>

        <div style="margin-top:14px">
          <a class="btnS" href="teachers.php">← Volver</a>
        </div>
      </section>
    </main>
  </div>
</body>
</html>