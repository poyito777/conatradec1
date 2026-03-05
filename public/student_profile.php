<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit("Falta id"); }

$stmt = $pdo->prepare("
  SELECT s.*, u.name AS teacher_name, u.email AS teacher_email
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) { http_response_code(404); exit("Estudiante no encontrado"); }

// Permiso: docente solo ve los suyos
if (($me['role'] ?? '') === 'teacher' && (int)$s['teacher_id'] !== (int)$me['id']) {
  http_response_code(403); exit("Acceso denegado");
}

function courseLabel($t){ return $t === 'catacion' ? 'Catación' : 'Barismo'; }
function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}
function pillClass($status){
  if ($status === 'aprobado') return 'aprobado';
  if ($status === 'desaprobado') return 'desaprobado';
  return 'pendiente';
}

// Estado “bonito”
$statusTxt = $s['status'] ?? 'pendiente';
if ($statusTxt === 'pendiente') $statusTxt = 'Pendiente';
if ($statusTxt === 'aprobado') $statusTxt = 'Aprobado';
if ($statusTxt === 'desaprobado') $statusTxt = 'Desaprobado';

// Nota
$gradeTxt = ($s['final_grade'] === null) ? '—' : (string)$s['final_grade'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Expediente • <?= h($s['full_name']) ?></title>
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
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col6{grid-column:span 12}}

    .btn2{display:inline-block;padding:10px 14px;border-radius:14px;background:linear-gradient(180deg,var(--green),var(--green2));color:#06110a;font-weight:800}
    .btnS{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800}
    .btnG{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.06);color:rgba(255,255,255,.92);font-weight:800}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}

    .kv{border:1px solid var(--line);border-radius:14px;padding:14px;background:rgba(255,255,255,.05)}
    .k{font-size:12px;color:var(--muted);margin:0 0 6px}
    .v{margin:0;font-weight:800}
    .muted{color:var(--muted)}
    .titleRow{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}

    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px}
    .pill.aprobado{border-color:rgba(47,191,113,.45);color:var(--green)}
    .pill.desaprobado{border-color:rgba(255,90,95,.45);color:rgba(255,255,255,.92)}
    .pill.pendiente{border-color:rgba(245,158,11,.45);color:rgba(255,255,255,.92)}

    .boxNote{border:1px solid var(--line);border-radius:14px;padding:14px;background:rgba(255,255,255,.04)}
    .boxNote p{margin:0;color:rgba(255,255,255,.88);line-height:1.6}
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
        <?php if (($me['role'] ?? '') === 'admin'): ?><a href="teachers.php">Docentes</a><?php endif; ?>
        <a href="help.php">Guía</a>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <div class="titleRow">
          <div>
            <h2 style="margin:0 0 6px;">Expediente del estudiante</h2>
            <div class="muted">
              ID: <b><?= (int)$s['id'] ?></b> •
              Estado: <span class="pill <?= h(pillClass($s['status'] ?? 'pendiente')) ?>"><?= h($statusTxt) ?></span>
            </div>
          </div>

          <div class="actions">
            <a class="btnG" href="students.php">← Volver</a>
            <a class="btnS" href="student_form.php?id=<?= (int)$s['id'] ?>">Editar</a>
            <a class="btn2" href="student_letter.php?id=<?= (int)$s['id'] ?>" target="_blank">Constancia</a>
          </div>
        </div>

        <hr style="border:0;border-top:1px solid var(--line);margin:14px 0;">

        <div class="grid">
          <div class="kv col12">
            <p class="k">Nombre completo</p>
            <p class="v" style="font-size:18px;"><?= h($s['full_name']) ?></p>
            <p class="muted" style="margin:8px 0 0;">
              <?= h($s['school'] ?: 'Escuela no especificada') ?>
              <?= $s['student_code'] ? " • Código: " . h($s['student_code']) : "" ?>
            </p>
          </div>

          <div class="kv col6">
            <p class="k">Curso</p>
            <p class="v"><?= h(courseLabel($s['course_type'] ?? 'barismo')) ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Nivel</p>
            <p class="v"><?= h(levelLabel($s['course_level'] ?? 'basico')) ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Departamento</p>
            <p class="v"><?= h($s['department'] ?: '—') ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Fecha de inscripción</p>
            <p class="v"><?= h($s['enrolled_at'] ?: '—') ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Teléfono</p>
            <p class="v"><?= h($s['phone'] ?: '—') ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Cédula</p>
            <p class="v"><?= h($s['cedula'] ?: '—') ?></p>
          </div>

          <div class="kv col6">
            <p class="k">Nota final</p>
            <p class="v"><?= h($gradeTxt) ?></p>
            <p class="muted" style="margin:8px 0 0;">(0–100)</p>
          </div>

          <div class="kv col6">
            <p class="k">Estado</p>
            <p class="v"><?= h($statusTxt) ?></p>
            <p class="muted" style="margin:8px 0 0;">
              <?= ($s['final_grade'] === null) ? 'Sin nota → Pendiente' : 'Calculado por nota (≥60 aprobado)' ?>
            </p>
          </div>

          <?php if (($me['role'] ?? '') === 'admin'): ?>
            <div class="kv col12">
              <p class="k">Docente asignado</p>
              <p class="v"><?= h($s['teacher_name']) ?></p>
              <p class="muted" style="margin:8px 0 0;"><?= h($s['teacher_email']) ?></p>
            </div>
          <?php endif; ?>

          <div class="col12">
            <div class="boxNote">
              <p class="k" style="margin-bottom:10px;">Observaciones</p>
              <p><?= h($s['observations'] ?: '—') ?></p>
            </div>
          </div>

          <div class="col12">
            <div class="boxNote">
              <p class="k" style="margin-bottom:10px;">Notas internas</p>
              <p><?= h($s['notes'] ?: '—') ?></p>
            </div>
          </div>

          <div class="col12 muted" style="font-size:12px;">
            Registrado: <b><?= h($s['created_at'] ?? '—') ?></b>
            <?php if (!empty($s['updated_at'])): ?> • Última actualización: <b><?= h($s['updated_at']) ?></b><?php endif; ?>
          </div>
        </div>
      </section>
    </main>
  </div>
</body>
</html>