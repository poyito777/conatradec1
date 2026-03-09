<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$where = [];
$params = [];

if (($me['role'] ?? '') === 'teacher') {
    $where[] = "g.teacher_id = ?";
    $params[] = (int)$me['id'];
}

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');
$q        = trim($_GET['q'] ?? '');

if ($dateFrom !== '') {
    $where[] = "a.attendance_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "a.attendance_date <= ?";
    $params[] = $dateTo;
}

if ($q !== '') {
    $where[] = "(g.name LIKE ? OR g.group_code LIKE ? OR u.name LIKE ?)";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like);
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
SELECT
    a.id,
    a.attendance_date,
    g.id AS group_id,
    g.group_code,
    g.name AS group_name,
    g.course_type,
    g.course_level,
    g.status AS group_status,
    u.name AS teacher_name,
    (
        SELECT COUNT(*)
        FROM attendance_items ai
        WHERE ai.attendance_id = a.id AND ai.present = 1
    ) AS total_present,
    (
        SELECT COUNT(*)
        FROM attendance_items ai
        WHERE ai.attendance_id = a.id AND ai.present = 0
    ) AS total_absent
FROM attendances a
JOIN groups_table g ON g.id = a.group_id
JOIN users u ON u.id = g.teacher_id
$sqlWhere
ORDER BY a.attendance_date DESC, a.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Historial de asistencias</title>
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
    th{color:var(--muted)}
    .filters{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:end;
      margin-top:12px
    }
    .filters .field{
      margin-top:0;
      min-width:180px
    }
    .btnS{
      display:inline-block;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      text-decoration:none
    }
    .btnG{
      display:inline-block;
      padding:8px 12px;
      border-radius:12px;
      border:1px solid rgba(148,163,184,.35);
      background:rgba(255,255,255,.06);
      color:rgba(255,255,255,.92);
      font-weight:700;
      text-decoration:none
    }
    .pill{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px
    }
    .ok{
      border-color:rgba(47,191,113,.45);
      color:var(--green)
    }
    .bad{
      border-color:rgba(255,90,95,.45);
      color:#fff
    }
    .muted{color:var(--muted)}
  </style>
</head>
<body>
<div class="app">
  <header class="topbar">
    <div class="logo">
      <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
      <span>CONATRADEC • Historial de Asistencias</span>
    </div>

    <div class="nav">
      <a href="dashboard.php">Dashboard</a>
      <a href="groups.php">Grupos</a>
      <a href="attendance_history.php">Asistencias</a>
      <a href="logout.php">Salir</a>
    </div>
  </header>

  <main class="container">
    <section class="panel">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
          <h2 style="margin:0 0 6px;">Historial de asistencias</h2>
          <p style="margin:0;color:var(--muted);">
            <?= (($me['role'] ?? '') === 'admin') ? 'Vista global' : 'Solo tus grupos' ?>
          </p>
        </div>
      </div>

      <form method="get" class="filters">
        <div class="field">
          <label>Buscar</label>
          <input name="q" value="<?= h($q) ?>" placeholder="Grupo, código o docente">
        </div>

        <div class="field">
          <label>Desde</label>
          <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        </div>

        <div class="field">
          <label>Hasta</label>
          <input type="date" name="date_to" value="<?= h($dateTo) ?>">
        </div>

        <div class="field">
          <button class="btn" type="submit">Filtrar</button>
        </div>

        <div class="field">
          <a class="btnG" href="attendance_history.php">Limpiar</a>
        </div>
      </form>

      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Grupo</th>
            <th>Código</th>
            <th>Curso</th>
            <th>Nivel</th>
            <?php if (($me['role'] ?? '') === 'admin'): ?>
              <th>Docente</th>
            <?php endif; ?>
            <th>Presentes</th>
            <th>Ausentes</th>
            <th>Estado grupo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h($r['attendance_date']) ?></td>
              <td><?= h($r['group_name']) ?></td>
              <td><b><?= h($r['group_code']) ?></b></td>
              <td><?= h($r['course_type']) ?></td>
              <td><?= h($r['course_level']) ?></td>

              <?php if (($me['role'] ?? '') === 'admin'): ?>
                <td><?= h($r['teacher_name']) ?></td>
              <?php endif; ?>

              <td>
                <span class="pill ok"><?= (int)$r['total_present'] ?></span>
              </td>
              <td>
                <span class="pill bad"><?= (int)$r['total_absent'] ?></span>
              </td>
              <td><?= h($r['group_status']) ?></td>
              <td>
                <a class="btnS" href="attendance_view.php?id=<?= (int)$r['id'] ?>">Ver detalle</a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$rows): ?>
            <tr>
              <td colspan="<?= (($me['role'] ?? '') === 'admin') ? 10 : 9 ?>" class="muted">
                No hay asistencias registradas.
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