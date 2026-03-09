<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$where = "";
$params = [];

if (($me['role'] ?? '') === 'teacher') {
    $where = "WHERE g.teacher_id = ?";
    $params[] = (int)$me['id'];
}

$sql = "
SELECT 
    g.*,
    u.name AS teacher_name,
    (
        SELECT COUNT(*) 
        FROM group_students gs 
        WHERE gs.group_id = g.id
    ) AS total_students
FROM groups_table g
JOIN users u ON u.id = g.teacher_id
$where
ORDER BY 
  CASE WHEN g.status = 'activo' THEN 0 ELSE 1 END,
  g.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

$finalized = isset($_GET['finalized']) && $_GET['finalized'] == '1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Grupos</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:14px 24px;
      background:rgba(0,0,0,.35);
      border-bottom:1px solid var(--line);
      backdrop-filter:blur(8px)
    }
    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:700
    }
    .logo img{
      width:34px;
      height:34px;
      object-fit:contain
    }
    .nav{
      display:flex;
      gap:10px;
      align-items:center;
      flex-wrap:wrap
    }
    .nav a{
      padding:8px 12px;
      border:1px solid var(--line);
      border-radius:12px;
      background:rgba(255,255,255,.06)
    }
    .container{
      padding:26px;
      max-width:1250px;
      width:100%;
      margin:0 auto
    }
    .panel{
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow)
    }
    table{
      width:100%;
      border-collapse:collapse;
      margin-top:14px
    }
    th,td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:14px;
      vertical-align:top
    }
    th{
      color:var(--muted)
    }
    .btnS{
      display:inline-block;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      text-decoration:none;
      margin-right:6px;
      margin-bottom:6px
    }
    .btnDanger{
      display:inline-block;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
      color:#fca5a5;
      font-weight:800;
      text-decoration:none;
      margin-right:6px;
      margin-bottom:6px;
      cursor:pointer;
    }
    .btnMuted{
      display:inline-block;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.04);
      color:#cbd5e1;
      font-weight:700;
      margin-right:6px;
      margin-bottom:6px;
    }
    .pill{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px
    }
    .activo{
      border-color:rgba(47,191,113,.45);
      color:var(--green)
    }
    .finalizado{
      border-color:rgba(239,68,68,.45);
      color:#fca5a5
    }
    .muted{
      color:var(--muted)
    }
    .ok-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(47,191,113,.10);
      border:1px solid rgba(47,191,113,.35);
      color:var(--green);
      font-weight:700;
    }
    form.inline{
      display:inline;
    }
  </style>
</head>
<body>
<div class="app">
  <header class="topbar">
    <div class="logo">
      <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
      <span>CONATRADEC • Grupos</span>
    </div>

    <div class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="students.php">Estudiantes</a>
      <a href="groups.php">Grupos</a>
      <a href="attendance_history.php">Asistencias</a>
      <a href="help.php">Guía</a>
      <a href="logout.php">Salir</a>
    </div>
  </header>

  <main class="container">
    <section class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 6px;">Grupos</h2>
          <p style="margin:0;color:var(--muted);">
            <?= ($me['role'] === 'admin') ? 'Vista global de grupos' : 'Tus grupos asignados' ?>
          </p>
        </div>

        <a class="btnS" href="group_new.php">+ Crear grupo</a>
      </div>

      <?php if ($finalized): ?>
        <div class="ok-msg">Grupo finalizado correctamente.</div>
      <?php endif; ?>

      <table>
        <thead>
          <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Curso</th>
            <th>Nivel</th>
            <th>Horario</th>
            <th>Ubicación</th>
            <th>Estudiantes</th>
            <?php if (($me['role'] ?? '') === 'admin'): ?>
              <th>Docente</th>
            <?php endif; ?>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><b><?= h($g['group_code']) ?></b></td>
              <td><?= h($g['name']) ?></td>
              <td><?= h($g['course_type']) ?></td>
              <td><?= h($g['course_level']) ?></td>
              <td><?= h($g['schedule'] ?: '—') ?></td>
              <td><?= h($g['location'] ?: '—') ?></td>
              <td><?= (int)$g['total_students'] ?></td>

              <?php if (($me['role'] ?? '') === 'admin'): ?>
                <td><?= h($g['teacher_name']) ?></td>
              <?php endif; ?>

              <td>
                <span class="pill <?= h($g['status']) ?>">
                  <?= h($g['status']) ?>
                </span>
              </td>

              <td>
                <a class="btnS" href="group_students.php?id=<?= (int)$g['id'] ?>">Estudiantes</a>

                <?php if (($g['status'] ?? '') === 'activo'): ?>
                  <a class="btnS" href="attendance.php?group_id=<?= (int)$g['id'] ?>">Asistencia</a>

                  <form class="inline" method="post" action="group_finalize.php" onsubmit="return confirm('¿Seguro que deseas finalizar este grupo?');">
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button class="btnDanger" type="submit">Finalizar</button>
                  </form>
                <?php else: ?>
                  <span class="btnMuted">Grupo cerrado</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$groups): ?>
            <tr>
              <td colspan="<?= (($me['role'] ?? '') === 'admin') ? 10 : 9 ?>" class="muted">
                No hay grupos todavía.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>
</body>
</html>