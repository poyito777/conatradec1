<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireRole('admin');
requirePasswordChangeIfNeeded();

$teachers = $pdo->query("SELECT id, name, email, is_active, created_at FROM users WHERE role='teacher' ORDER BY id DESC")->fetchAll();
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Docentes</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:1100px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;font-size:14px}
    th{color:var(--muted);font-weight:700}
    tr:hover{background:rgba(255,255,255,.04)}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px}
    .on{border-color:rgba(47,191,113,.35);color:var(--green)}
    .off{border-color:rgba(255,90,95,.35);color:rgba(255,255,255,.85)}
    .btnS{display:inline-block;padding:8px 12px;border-radius:12px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:700}
    .btnD{display:inline-block;padding:8px 12px;border-radius:12px;border:1px solid rgba(255,90,95,.35);background:rgba(255,90,95,.10);color:rgba(255,255,255,.9);font-weight:700}
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
        <a href="dashboard.php">Dashboard</a>
        <a href="students.php">Estudiantes</a>
        <a href="teachers.php">Docentes</a>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 6px;">Gestión de docentes</h2>
            <p style="margin:0;color:var(--muted);">Crear, listar y restablecer contraseñas.</p>
          </div>
          <a class="btnS" href="teacher_new.php">+ Crear docente</a>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Activo</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($teachers as $t): ?>
              <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= h($t['name']) ?></td>
                <td><?= h($t['email']) ?></td>
                <td>
                  <?php if ((int)$t['is_active'] === 1): ?>
                    <span class="pill on">Sí</span>
                  <?php else: ?>
                    <span class="pill off">No</span>
                  <?php endif; ?>
                </td>
                <td><?= h($t['created_at']) ?></td>
                <td style="display:flex;gap:10px;flex-wrap:wrap">
                  <a class="btnS" href="teacher_reset.php?id=<?= (int)$t['id'] ?>"
                     onclick="return confirm('¿Restablecer contraseña de este docente?');">
                    Restablecer contraseña
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(!$teachers): ?>
              <tr><td colspan="6" style="color:var(--muted);">No hay docentes todavía.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>