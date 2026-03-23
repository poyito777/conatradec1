<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ===========================
// STATS (admin vs teacher)
// ===========================
$where = "";
$params = [];

if (($me['role'] ?? '') === 'teacher') {
  $where = "WHERE teacher_id = ?";
  $params[] = (int)$me['id'];
}

// Estado
$stmt = $pdo->prepare("SELECT status, COUNT(*) total FROM students $where GROUP BY status");
$stmt->execute($params);
$statusData = ['pendiente'=>0,'aprobado'=>0,'desaprobado'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['status'];
  if (isset($statusData[$k])) $statusData[$k] = (int)$r['total'];
}

// Curso
$stmt = $pdo->prepare("SELECT course_type, COUNT(*) total FROM students $where GROUP BY course_type");
$stmt->execute($params);
$courseData = ['barismo'=>0,'catacion'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['course_type'];
  if (isset($courseData[$k])) $courseData[$k] = (int)$r['total'];
}

// Nivel
$stmt = $pdo->prepare("SELECT course_level, COUNT(*) total FROM students $where GROUP BY course_level");
$stmt->execute($params);
$levelData = ['basico'=>0,'avanzado'=>0,'intensivo'=>0];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $k = $r['course_level'];
  if (isset($levelData[$k])) $levelData[$k] = (int)$r['total'];
}

$totalStudents = $statusData['pendiente'] + $statusData['aprobado'] + $statusData['desaprobado'];

// Departamentos
if (($me['role'] ?? '') === 'teacher') {
  $stmt = $pdo->prepare("
    SELECT d.name AS department_name, COUNT(*) total
    FROM students s
    LEFT JOIN departments d ON d.id = s.department_id
    WHERE s.teacher_id = ?
    GROUP BY d.id, d.name
    ORDER BY total DESC
  ");
  $stmt->execute([(int)$me['id']]);
} else {
  $stmt = $pdo->prepare("
    SELECT d.name AS department_name, COUNT(*) total
    FROM students s
    LEFT JOIN departments d ON d.id = s.department_id
    GROUP BY d.id, d.name
    ORDER BY total DESC
  ");
  $stmt->execute();
}

$deptLabels = [];
$deptTotals = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $dept = trim((string)($r['department_name'] ?? ''));
  if ($dept === '') {
    $dept = 'Sin datos';
  }
  $deptLabels[] = $dept;
  $deptTotals[] = (int)$r['total'];
}

if (!$deptLabels) {
  $deptLabels = ['Sin datos'];
  $deptTotals = [0];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .container{
      padding:26px;
      max-width:1200px;
      width:100%;
      margin:0 auto;
    }

    .panel{
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:18px;
      box-shadow:var(--shadow);
    }

    .grid{
      display:grid;
      grid-template-columns:repeat(12,1fr);
      gap:14px;
    }

    .col6{grid-column:span 6}
    .col12{grid-column:span 12}

    @media(max-width:860px){
      .col6{grid-column:span 12}
    }

    .btn2{
      display:inline-block;
      padding:10px 14px;
      border-radius:14px;
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      font-weight:800;
      text-decoration:none;
    }

    .btnS{
      display:inline-block;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:700;
      text-decoration:none;
    }

    .cards{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
      margin-top:12px;
    }

    @media(max-width:860px){
      .cards{grid-template-columns:1fr}
    }

    .card{
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
    }

    .card .k{
      font-size:12px;
      color:var(--muted);
    }

    .card .v{
      font-size:28px;
      font-weight:900;
      margin-top:6px;
    }

    .chartBox{
      background:rgba(255,255,255,.05);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
    }

    .chartTitle{
      font-weight:800;
      margin:0 0 10px;
    }

    .small{
      font-size:12px;
      color:var(--muted);
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      flex-wrap:wrap;
    }

    .hero-right{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <div class="grid">

    <section class="panel col12">
      <div class="hero">
        <div>
          <h2 style="margin:0 0 6px;">Hola, <?= h($me['name']) ?> 👋</h2>
          <p style="margin:0;color:var(--muted);">
            Rol: <b><?= h($me['role']) ?></b> •
            <?= (($me['role'] ?? '') === 'admin') ? 'Estadísticas globales' : 'Estadísticas de tus estudiantes' ?>
          </p>
        </div>

        <div class="hero-right">
          <a class="btn2" href="students.php">Ver estudiantes</a>
          <a class="btnS" href="groups.php">Ver grupos</a>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <div class="k">Total estudiantes</div>
          <div class="v"><?= (int)$totalStudents ?></div>
        </div>

        <div class="card">
          <div class="k">Aprobados (≥ 60)</div>
          <div class="v"><?= (int)$statusData['aprobado'] ?></div>
        </div>

        <div class="card">
          <div class="k">Pendientes (sin nota)</div>
          <div class="v"><?= (int)$statusData['pendiente'] ?></div>
        </div>
      </div>

      <div class="small" style="margin-top:10px;">
        Desaprobados: <b><?= (int)$statusData['desaprobado'] ?></b>
      </div>
    </section>

    <section class="panel col6">
      <h3 style="margin:0 0 10px;">Accesos rápidos</h3>
      <a class="btn2" href="students.php">Estudiantes</a>
      <span style="display:inline-block;width:10px"></span>
      <a class="btnS" href="change_password.php">Cambiar contraseña</a>
      <div class="small" style="margin-top:10px;">
        Tip: aplicá filtros en Estudiantes y luego descargá el CSV con el mismo filtro.
      </div>
    </section>

    <?php if (($me['role'] ?? '') === 'admin'): ?>
      <section class="panel col6">
        <h3 style="margin:0 0 10px;">Administración</h3>
        <a class="btn2" href="teachers.php">Gestionar docentes</a>
        <span style="display:inline-block;width:10px"></span>
        <a class="btnS" href="teacher_new.php">Crear docente</a>
        <div class="small" style="margin-top:10px;">
          Si un docente olvida contraseña, usá reset para forzar cambio al primer login.
        </div>
      </section>
    <?php endif; ?>

    <section class="panel col12">
      <h3 style="margin:0 0 6px;">Estadísticas</h3>
      <p style="margin:0;color:var(--muted);">
        Vista: <?= (($me['role'] ?? '') === 'admin') ? 'todos los estudiantes' : 'solo tus estudiantes' ?>.
      </p>

      <div class="grid" style="margin-top:14px;">
        <div class="chartBox col6">
          <p class="chartTitle">Estado</p>
          <canvas id="statusChart" height="180"></canvas>
        </div>

        <div class="chartBox col6">
          <p class="chartTitle">Curso</p>
          <canvas id="courseChart" height="180"></canvas>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Nivel</p>
          <canvas id="levelChart" height="120"></canvas>
        </div>

        <div class="chartBox col12">
          <p class="chartTitle">Estudiantes por departamento</p>
          <canvas id="deptChart" height="140"></canvas>
          <div class="small" style="margin-top:8px;">
            Datos obtenidos desde el catálogo de departamentos.
          </div>
        </div>
      </div>
    </section>

  </div>
</main>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('appSidebar');
  if (window.innerWidth <= 960) {
    sidebar.classList.toggle('open');
  } else {
    sidebar.classList.toggle('collapsed');
  }
}

// Estado
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Pendiente','Aprobado','Desaprobado'],
    datasets: [{
      data: [
        <?= (int)$statusData['pendiente'] ?>,
        <?= (int)$statusData['aprobado'] ?>,
        <?= (int)$statusData['desaprobado'] ?>
      ]
    }]
  },
  options: {
    plugins: { legend: { position: 'bottom' } }
  }
});

// Curso
new Chart(document.getElementById('courseChart'), {
  type: 'bar',
  data: {
    labels: ['Barismo','Catación'],
    datasets: [{
      data: [
        <?= (int)$courseData['barismo'] ?>,
        <?= (int)$courseData['catacion'] ?>
      ]
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

// Nivel
new Chart(document.getElementById('levelChart'), {
  type: 'bar',
  data: {
    labels: ['Básico','Avanzado','Intensivo'],
    datasets: [{
      data: [
        <?= (int)$levelData['basico'] ?>,
        <?= (int)$levelData['avanzado'] ?>,
        <?= (int)$levelData['intensivo'] ?>
      ]
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

// Departamentos
new Chart(document.getElementById('deptChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($deptLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
      data: <?= json_encode($deptTotals, JSON_UNESCAPED_UNICODE) ?>
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});
</script>
</body>
</html>