<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  exit("Falta id");
}

$stmt = $pdo->prepare("
  SELECT s.*, u.name AS teacher_name, u.email AS teacher_email
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
  http_response_code(404);
  exit("Estudiante no encontrado");
}

// Permiso: docente solo ve los suyos
if (($me['role'] ?? '') === 'teacher' && (int)$s['teacher_id'] !== (int)$me['id']) {
  http_response_code(403);
  exit("Acceso denegado");
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}

function sexLabel($v){
  if ($v === 'masculino') return 'Masculino';
  if ($v === 'femenino') return 'Femenino';
  return '—';
}

function educationLabel($v){
  if ($v === 'secundaria') return 'Secundaria';
  if ($v === 'tecnico') return 'Técnico';
  if ($v === 'universitario') return 'Universitario';
  return '—';
}

function nationalityLabel($v){
  if ($v === 'nicaraguense') return 'Nicaragüense';
  if ($v === 'extranjero') return 'Extranjero';
  return '—';
}

function organizationTypeLabel($v){
  if ($v === 'institucion') return 'Institución';
  if ($v === 'privado') return 'Privado';
  if ($v === 'emprendimiento') return 'Emprendimiento';
  if ($v === 'estudiante') return 'Estudiante';
  if ($v === 'productor') return 'Productor';
  return '—';
}

function yesNoLabel($v){
  if ($v === 'si') return 'Sí';
  if ($v === 'no') return 'No';
  return '—';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Expediente • <?= h($s['full_name']) ?></title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1180px;
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

    .col4{grid-column:span 4}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}

    @media(max-width:860px){
      .col4,.col6{grid-column:span 12}
    }

    .btn2{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      font-weight:800;
      text-decoration:none;
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      text-decoration:none;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(148,163,184,.35);
      background:rgba(255,255,255,.06);
      color:rgba(255,255,255,.92);
      font-weight:800;
      text-decoration:none;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:12px;
    }

    .kv{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,.05);
    }

    .k{
      font-size:12px;
      color:var(--muted);
      margin:0 0 6px;
    }

    .v{
      margin:0;
      font-weight:800;
      color:#e5e7eb;
    }

    .muted{
      color:var(--muted);
    }

    .titleRow{
      display:flex;
      justify-content:space-between;
      gap:12px;
      align-items:flex-start;
      flex-wrap:wrap;
    }

    .boxNote{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      background:rgba(255,255,255,.04);
    }

    .boxNote p{
      margin:0;
      color:rgba(255,255,255,.88);
      line-height:1.7;
    }

    .tag{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
      color:#e5e7eb;
      background:rgba(255,255,255,.04);
    }

    .section-title{
      font-size:15px;
      font-weight:900;
      margin:16px 0 10px;
      color:#e5e7eb;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="titleRow">
      <div>
        <h2 style="margin:0 0 6px;">Expediente del estudiante</h2>
        <div class="muted">
          ID interno: <b><?= (int)$s['id'] ?></b>
          <?php if (!empty($s['student_code'])): ?>
            • Código: <span class="tag"><?= h($s['student_code']) ?></span>
          <?php endif; ?>
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
        </p>
      </div>

      <div class="section-title col12">Información personal</div>

      <div class="kv col4">
        <p class="k">Sexo</p>
        <p class="v"><?= h(sexLabel($s['sex'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nivel escolar</p>
        <p class="v"><?= h(educationLabel($s['education_level'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nacionalidad</p>
        <p class="v"><?= h(nationalityLabel($s['nationality'] ?? '')) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Profesión</p>
        <p class="v"><?= h($s['profession'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Caracterización</p>
        <p class="v"><?= h($s['characterization'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Teléfono</p>
        <p class="v"><?= h($s['phone'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Cédula</p>
        <p class="v"><?= h($s['cedula'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Formación</div>

      <div class="kv col6">
        <p class="k">Curso</p>
        <p class="v"><?= h(courseLabel($s['course_type'] ?? 'barismo')) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Nivel</p>
        <p class="v"><?= h(levelLabel($s['course_level'] ?? 'basico')) ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Fecha de inscripción</p>
        <p class="v"><?= h($s['enrolled_at'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Propósito del curso</p>
        <p class="v"><?= h($s['course_purpose'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Ubicación</div>

      <div class="kv col4">
        <p class="k">Departamento</p>
        <p class="v"><?= h($s['department'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Municipio</p>
        <p class="v"><?= h($s['municipality'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Comunidad</p>
        <p class="v"><?= h($s['community'] ?: '—') ?></p>
      </div>

      <div class="section-title col12">Organización</div>

      <div class="kv col4">
        <p class="k">Tipo de organización</p>
        <p class="v"><?= h(organizationTypeLabel($s['organization_type'] ?? '')) ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Nombre de organización</p>
        <p class="v"><?= h($s['organization_name'] ?: '—') ?></p>
      </div>

      <div class="kv col4">
        <p class="k">Teléfono de organización</p>
        <p class="v"><?= h($s['organization_phone'] ?: '—') ?></p>
      </div>

      <div class="kv col6">
        <p class="k">Ubicación de organización</p>
        <p class="v"><?= h($s['organization_location'] ?: '—') ?></p>
      </div>

      <div class="kv col3">
        <p class="k">Registro de marca</p>
        <p class="v"><?= h(yesNoLabel($s['trademark_registration'] ?? '')) ?></p>
      </div>

      <div class="kv col3">
        <p class="k">Número de socios</p>
        <p class="v"><?= h($s['number_of_members'] !== null && $s['number_of_members'] !== '' ? $s['number_of_members'] : '—') ?></p>
      </div>

      <div class="section-title col12">Proyección y observaciones</div>

      <div class="col12">
        <div class="boxNote">
          <p class="k" style="margin-bottom:10px;">Proyección a futuro</p>
          <p><?= h($s['future_projection'] ?: '—') ?></p>
        </div>
      </div>

      <div class="col12">
        <div class="boxNote">
          <p class="k" style="margin-bottom:10px;">Observaciones</p>
          <p><?= h($s['observations'] ?: '—') ?></p>
        </div>
      </div>

      <?php if (($me['role'] ?? '') === 'admin'): ?>
        <div class="section-title col12">Control administrativo</div>

        <div class="kv col12">
          <p class="k">Docente asignado</p>
          <p class="v"><?= h($s['teacher_name']) ?></p>
          <p class="muted" style="margin:8px 0 0;"><?= h($s['teacher_email']) ?></p>
        </div>
      <?php endif; ?>

      <div class="col12 muted" style="font-size:12px;">
        Registrado: <b><?= h($s['created_at'] ?? '—') ?></b>
        <?php if (!empty($s['updated_at'])): ?>
          • Última actualización: <b><?= h($s['updated_at']) ?></b>
        <?php endif; ?>
      </div>
    </div>
  </section>
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
</script>
</div>
</div>
</body>
</html>