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
$error = '';

// Escuelas fijas
$schools = [
  'Escuela Café Boutique Managua',
  'Cra. Natividad Martínez Sanchez',
  'Cra. Eudosia Abdulia Gomez Chavarria "La Docha"',
  'Cro. Gabriel Martínez Herrera San Juan de Río Coco-Madriz'
];

// Default row (nuevo)
$row = [
  'id' => 0,
  'teacher_id' => $me['id'],
  'full_name' => '',
  'student_code' => '',
  'school' => '',
  'course_type' => 'barismo',
  'course_level' => 'basico',
  'phone' => '',
  'cedula' => '',
  'department' => '',
  'organization' => '',
  'characterization' => '',
  'enrolled_at' => '',
  'observations' => ''
];

// Si edición: cargar y validar permisos
if ($id > 0) {
  $st = $pdo->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $found = $st->fetch();

  if (!$found) {
    http_response_code(404);
    exit("No existe");
  }

  if (($me['role'] ?? '') === 'teacher' && (int)$found['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit("Acceso denegado");
  }

  $row = $found;
}

// Dropdown docentes (solo admin)
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='teacher' AND is_active=1
    ORDER BY name ASC
  ")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $school = trim($_POST['school'] ?? '');

  $course_type = trim($_POST['course_type'] ?? 'barismo');
  $course_level = trim($_POST['course_level'] ?? 'basico');

  $phone = trim($_POST['phone'] ?? '');
  $cedula = trim($_POST['cedula'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $organization = trim($_POST['organization'] ?? '');
  $characterization = trim($_POST['characterization'] ?? '');
  $enrolled_at = trim($_POST['enrolled_at'] ?? '');
  $observations = trim($_POST['observations'] ?? '');

  // teacher_id: admin elige, teacher fijo
  $teacher_id = (int)$row['teacher_id'];
  if (($me['role'] ?? '') === 'admin') {
    $teacher_id = (int)($_POST['teacher_id'] ?? $teacher_id);
  } else {
    $teacher_id = (int)$me['id'];
  }

  // Validaciones
  $phoneDigits = preg_replace('/\D+/', '', $phone);
  $cedulaDigits = preg_replace('/\D+/', '', $cedula);

  if ($full_name === '') {
    $error = "El nombre es obligatorio.";
  } elseif (!in_array($school, $schools, true)) {
    $error = "Seleccioná una escuela válida.";
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = "Tipo de curso inválido.";
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = "Nivel inválido.";
  } elseif ($phone !== '' && strlen($phoneDigits) !== 8) {
    $error = "El teléfono debe tener exactamente 8 dígitos.";
  } elseif ($cedula !== '' && strlen($cedulaDigits) !== 14) {
    $error = "La cédula debe tener exactamente 14 dígitos.";
  } else {

    if ($id > 0) {
      // UPDATE
      $up = $pdo->prepare("
        UPDATE students SET
          teacher_id=?,
          full_name=?,
          school=?,
          course_type=?,
          course_level=?,
          phone=?,
          cedula=?,
          department=?,
          organization=?,
          characterization=?,
          enrolled_at=?,
          observations=?
        WHERE id=?
      ");

      $up->execute([
        $teacher_id,
        $full_name,
        $school,
        $course_type,
        $course_level,
        ($phone === '' ? null : $phoneDigits),
        ($cedula === '' ? null : $cedulaDigits),
        $department,
        $organization,
        $characterization,
        ($enrolled_at === '' ? null : $enrolled_at),
        $observations,
        $id
      ]);

      header("Location: students.php");
      exit;

    } else {
      // INSERT inicial sin student_code
      $ins = $pdo->prepare("
        INSERT INTO students
        (teacher_id, full_name, school, course_type, course_level, phone, cedula, department, organization, characterization, enrolled_at, observations)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
      ");

      $ins->execute([
        $teacher_id,
        $full_name,
        $school,
        $course_type,
        $course_level,
        ($phone === '' ? null : $phoneDigits),
        ($cedula === '' ? null : $cedulaDigits),
        $department,
        $organization,
        $characterization,
        ($enrolled_at === '' ? null : $enrolled_at),
        $observations
      ]);

      // Generar student_code automático tipo EST-2026-0001
      $newId = (int)$pdo->lastInsertId();
      $studentCode = 'EST-' . date('Y') . '-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);

      $upCode = $pdo->prepare("UPDATE students SET student_code=? WHERE id=?");
      $upCode->execute([$studentCode, $newId]);

      header("Location: students.php");
      exit;
    }
  }

  // Rehidratar si hubo error
  $row['teacher_id'] = $teacher_id;
  $row['full_name'] = $full_name;
  $row['school'] = $school;
  $row['course_type'] = $course_type;
  $row['course_level'] = $course_level;
  $row['phone'] = $phone;
  $row['cedula'] = $cedula;
  $row['department'] = $department;
  $row['organization'] = $organization;
  $row['characterization'] = $characterization;
  $row['enrolled_at'] = $enrolled_at;
  $row['observations'] = $observations;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:980px;
      width:100%;
      margin:0 auto;
    }

    .panel{
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:var(--radius);
      padding:20px;
      box-shadow:var(--shadow);
    }

    .grid{
      display:grid;
      grid-template-columns:repeat(12,1fr);
      gap:12px;
    }

    .col6{grid-column:span 6}
    .col12{grid-column:span 12}

    @media(max-width:860px){
      .col6{grid-column:span 12}
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:14px;
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
      cursor:pointer;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    textarea{
      resize:vertical;
    }

    .hint{
      font-size:12px;
      color:var(--muted);
      margin-top:6px;
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
      flex-wrap:wrap;
      margin-bottom:12px;
    }

    .muted{
      color:var(--muted);
    }

    .info-box{
      margin-top:12px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(255,255,255,.04);
      border:1px solid var(--line);
      color:var(--muted);
      line-height:1.6;
      font-size:14px;
    }

    .readonly-box{
      padding:12px 14px;
      border-radius:12px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.04);
      color:#e5e7eb;
      font-weight:700;
      min-height:46px;
      display:flex;
      align-items:center;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;"><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</h2>
        <p style="margin:0;color:var(--muted);">
          El código del estudiante se genera automáticamente al crear el registro.
        </p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px;">
      <div class="grid">

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <div class="field col12">
            <label>Docente</label>
            <select name="teacher_id" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$row['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field col12">
          <label>Nombre completo *</label>
          <input name="full_name" value="<?= h($row['full_name']) ?>" required>
        </div>

        <div class="field col6">
          <label>Código del estudiante</label>
          <div class="readonly-box">
            <?= $id > 0 ? h($row['student_code'] ?: 'Sin código') : 'Se generará automáticamente al guardar' ?>
          </div>
        </div>

        <div class="field col6">
          <label>Escuela</label>
          <select name="school" required>
            <option value="">Seleccionar escuela</option>
            <?php foreach ($schools as $school): ?>
              <option value="<?= h($school) ?>" <?= ($row['school'] ?? '') === $school ? 'selected' : '' ?>>
                <?= h($school) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col6">
          <label>Tipo de curso</label>
          <select name="course_type">
            <option value="barismo" <?= ($row['course_type'] ?? '') === 'barismo' ? 'selected' : '' ?>>Barismo</option>
            <option value="catacion" <?= ($row['course_type'] ?? '') === 'catacion' ? 'selected' : '' ?>>Catación</option>
          </select>
        </div>

        <div class="field col6">
          <label>Nivel</label>
          <select name="course_level">
            <option value="basico" <?= ($row['course_level'] ?? '') === 'basico' ? 'selected' : '' ?>>Básico</option>
            <option value="avanzado" <?= ($row['course_level'] ?? '') === 'avanzado' ? 'selected' : '' ?>>Avanzado</option>
            <option value="intensivo" <?= ($row['course_level'] ?? '') === 'intensivo' ? 'selected' : '' ?>>Intensivo</option>
          </select>
        </div>

        <div class="field col6">
          <label>Teléfono</label>
          <input
            name="phone"
            value="<?= h($row['phone'] ?? '') ?>"
            placeholder="Ej: 88888888"
            maxlength="8"
            inputmode="numeric"
          >
          <div class="hint">Debe tener exactamente 8 dígitos.</div>
        </div>

        <div class="field col6">
          <label>Cédula</label>
          <input
            name="cedula"
            value="<?= h($row['cedula'] ?? '') ?>"
            placeholder="Ej: 00112345678901"
            maxlength="14"
            inputmode="numeric"
          >
          <div class="hint">Debe tener exactamente 14 dígitos.</div>
        </div>

        <div class="field col6">
          <label>Departamento</label>
          <select name="department">
            <?php $dep = $row['department'] ?? ''; ?>
            <option value="">Seleccionar departamento</option>
            <option value="Matagalpa" <?= $dep=='Matagalpa'?'selected':'' ?>>Matagalpa</option>
            <option value="Jinotega" <?= $dep=='Jinotega'?'selected':'' ?>>Jinotega</option>
            <option value="Nueva Segovia" <?= $dep=='Nueva Segovia'?'selected':'' ?>>Nueva Segovia</option>
            <option value="Madriz" <?= $dep=='Madriz'?'selected':'' ?>>Madriz</option>
            <option value="Estelí" <?= $dep=='Estelí'?'selected':'' ?>>Estelí</option>
            <option value="Chinandega" <?= $dep=='Chinandega'?'selected':'' ?>>Chinandega</option>
            <option value="León" <?= $dep=='León'?'selected':'' ?>>León</option>
            <option value="Managua" <?= $dep=='Managua'?'selected':'' ?>>Managua</option>
            <option value="Masaya" <?= $dep=='Masaya'?'selected':'' ?>>Masaya</option>
            <option value="Granada" <?= $dep=='Granada'?'selected':'' ?>>Granada</option>
            <option value="Carazo" <?= $dep=='Carazo'?'selected':'' ?>>Carazo</option>
            <option value="Rivas" <?= $dep=='Rivas'?'selected':'' ?>>Rivas</option>
            <option value="Boaco" <?= $dep=='Boaco'?'selected':'' ?>>Boaco</option>
            <option value="Chontales" <?= $dep=='Chontales'?'selected':'' ?>>Chontales</option>
            <option value="Río San Juan" <?= $dep=='Río San Juan'?'selected':'' ?>>Río San Juan</option>
            <option value="RAAN" <?= $dep=='RAAN'?'selected':'' ?>>RAAN</option>
            <option value="RAAS" <?= $dep=='RAAS'?'selected':'' ?>>RAAS</option>
          </select>
        </div>

        <div class="field col6">
          <label>Fecha de inscripción</label>
          <input type="date" name="enrolled_at" value="<?= h($row['enrolled_at'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Organización</label>
          <input name="organization" value="<?= h($row['organization'] ?? '') ?>" placeholder="Ej: cooperativa, asociación, empresa...">
        </div>

        <div class="field col6">
          <label>Caracterización</label>
          <input name="characterization" value="<?= h($row['characterization'] ?? '') ?>" placeholder="Ej: productor, tostador, barista, catador...">
        </div>

        <div class="field col12">
          <label>Observaciones</label>
          <textarea name="observations" rows="4"><?= h($row['observations'] ?? '') ?></textarea>
        </div>

      </div>

      <div class="actions">
        <button class="btn" type="submit"><?= $id > 0 ? 'Guardar cambios' : 'Crear estudiante' ?></button>
        <a class="btnS" href="students.php">← Cancelar</a>
        <a class="btnG" href="dashboard.php">Ir al dashboard</a>
      </div>
    </form>

    <div class="info-box">
      Verificá bien la escuela, el tipo de curso, el nivel y el departamento antes de guardar.
      Esos datos se usan en grupos, asistencia y estadísticas.
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