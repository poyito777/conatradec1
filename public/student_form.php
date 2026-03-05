<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
$error = '';

// Default row (nuevo)
$row = [
  'id'=>0,
  'teacher_id'=>$me['id'],
  'full_name'=>'',
  'student_code'=>'',
  'school'=>'',
  'course_type'=>'barismo',
  'course_level'=>'basico',
  'phone'=>'',
  'cedula'=>'',
  'department'=>'',
  'enrolled_at'=>'',
  'final_grade'=>null,
  'status'=>'pendiente',
  'notes'=>'',
  'observations'=>''
];

// Si edición: cargar y validar permisos
if ($id > 0) {
  $st = $pdo->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $found = $st->fetch();
  if (!$found) { http_response_code(404); exit("No existe"); }

  if (($me['role'] ?? '') === 'teacher' && (int)$found['teacher_id'] !== (int)$me['id']) {
    http_response_code(403); exit("Acceso denegado");
  }
  $row = $found;
}

// Dropdown docentes (solo admin)
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("SELECT id, name, email FROM users WHERE role='teacher' AND is_active=1 ORDER BY name ASC")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $student_code = trim($_POST['student_code'] ?? '');
  $school = trim($_POST['school'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  $course_type  = trim($_POST['course_type'] ?? 'barismo');
  $course_level = trim($_POST['course_level'] ?? 'basico');

  $phone = trim($_POST['phone'] ?? '');
  $cedula = trim($_POST['cedula'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $enrolled_at = trim($_POST['enrolled_at'] ?? '');

  $final_grade_raw = trim($_POST['final_grade'] ?? '');
  $final_grade = ($final_grade_raw === '') ? null : (float)$final_grade_raw;

  // Estado automático por nota
  if ($final_grade === null) $status = 'pendiente';
  else $status = ($final_grade >= 60) ? 'aprobado' : 'desaprobado';

  $observations = trim($_POST['observations'] ?? '');

  // teacher_id: admin elige, teacher fijo
  $teacher_id = (int)$row['teacher_id'];
  if (($me['role'] ?? '') === 'admin') {
    $teacher_id = (int)($_POST['teacher_id'] ?? $teacher_id);
  } else {
    $teacher_id = (int)$me['id'];
  }

  // Validaciones
  if ($full_name === '') {
    $error = "El nombre es obligatorio.";
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = "Tipo de curso inválido.";
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = "Nivel inválido.";
  } elseif ($final_grade !== null && ($final_grade < 0 || $final_grade > 100)) {
    $error = "La nota debe estar entre 0 y 100.";
  } else {

    if ($id > 0) {
      // UPDATE
      $up = $pdo->prepare("UPDATE students SET
        teacher_id=?,
        full_name=?,
        student_code=?,
        school=?,
        course_type=?,
        course_level=?,
        phone=?,
        cedula=?,
        department=?,
        enrolled_at=?,
        final_grade=?,
        status=?,
        notes=?,
        observations=?
      WHERE id=?");

      $up->execute([
        $teacher_id,
        $full_name,
        $student_code,
        $school,
        $course_type,
        $course_level,
        $phone,
        $cedula,
        $department,
        ($enrolled_at === '' ? null : $enrolled_at),
        $final_grade,
        $status,
        $notes,
        $observations,
        $id
      ]);

      header("Location: students.php"); exit;

    } else {
      // INSERT
      $ins = $pdo->prepare("INSERT INTO students
      (teacher_id, full_name, student_code, school, course_type, course_level, phone, cedula, department, enrolled_at, final_grade, status, notes, observations)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

      $ins->execute([
        $teacher_id,
        $full_name,
        $student_code,
        $school,
        $course_type,
        $course_level,
        $phone,
        $cedula,
        $department,
        ($enrolled_at === '' ? null : $enrolled_at),
        $final_grade,
        $status,
        $notes,
        $observations
      ]);

      header("Location: students.php"); exit;
    }
  }

  // Rehidratar si hubo error
  $row['teacher_id'] = $teacher_id;
  $row['full_name'] = $full_name;
  $row['student_code'] = $student_code;
  $row['school'] = $school;
  $row['course_type'] = $course_type;
  $row['course_level'] = $course_level;
  $row['phone'] = $phone;
  $row['cedula'] = $cedula;
  $row['department'] = $department;
  $row['enrolled_at'] = $enrolled_at;
  $row['final_grade'] = $final_grade;
  $row['status'] = $status;
  $row['notes'] = $notes;
  $row['observations'] = $observations;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $id>0 ? 'Editar' : 'Agregar' ?> estudiante</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:920px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col6{grid-column:span 12}}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .btnS{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800}
    textarea{resize:vertical}
    .hint{font-size:12px;color:var(--muted);margin-top:6px}
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
        <?php if (($me['role'] ?? '') === 'admin'): ?><a href="teachers.php">Docentes</a><?php endif; ?>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <h2 style="margin:0 0 6px;"><?= $id>0 ? 'Editar' : 'Agregar' ?> estudiante</h2>
        <p style="margin:0;color:var(--muted);">El estado se calcula automáticamente con la nota (>=60 aprobado).</p>

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
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$row['teacher_id']===(int)$t['id']?'selected':'' ?>>
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
              <label>Código / ID</label>
              <input name="student_code" value="<?= h($row['student_code']) ?>">
            </div>

            <div class="field col6">
              <label>Escuela</label>
              <input name="school" value="<?= h($row['school']) ?>">
            </div>

            <div class="field col6">
              <label>Tipo de curso</label>
              <select name="course_type">
                <option value="barismo" <?= ($row['course_type'] ?? '')==='barismo'?'selected':'' ?>>Barismo</option>
                <option value="catacion" <?= ($row['course_type'] ?? '')==='catacion'?'selected':'' ?>>Catación</option>
              </select>
            </div>

            <div class="field col6">
              <label>Nivel</label>
              <select name="course_level">
                <option value="basico" <?= ($row['course_level'] ?? '')==='basico'?'selected':'' ?>>Básico</option>
                <option value="avanzado" <?= ($row['course_level'] ?? '')==='avanzado'?'selected':'' ?>>Avanzado</option>
                <option value="intensivo" <?= ($row['course_level'] ?? '')==='intensivo'?'selected':'' ?>>Intensivo</option>
              </select>
            </div>

            <div class="field col6">
              <label>Teléfono</label>
              <input name="phone" value="<?= h($row['phone'] ?? '') ?>" placeholder="Ej: 8888-8888">
            </div>

            <div class="field col6">
              <label>Cédula</label>
              <input name="cedula" value="<?= h($row['cedula'] ?? '') ?>" placeholder="Ej: 001-xxxxxx-xxxxx">
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
              <label>Nota final (0–100)</label>
              <input type="number" step="0.01" min="0" max="100" name="final_grade"
                     value="<?= h($row['final_grade'] ?? '') ?>"
                     placeholder="Ej: 78.50">
              <div class="hint">Si está vacío → queda <b>pendiente</b>. Si >= 60 → <b>aprobado</b>.</div>
            </div>

            <div class="field col6">
              <label>Estado (automático)</label>
              <input value="<?= h($row['status'] ?? 'pendiente') ?>" disabled>
            </div>

            <div class="field col12">
              <label>Notas</label>
              <textarea name="notes" rows="3"><?= h($row['notes'] ?? '') ?></textarea>
            </div>

            <div class="field col12">
              <label>Observaciones</label>
              <textarea name="observations" rows="4"><?= h($row['observations'] ?? '') ?></textarea>
            </div>

          </div>

          <div class="actions">
            <button class="btn" type="submit"><?= $id>0 ? 'Guardar cambios' : 'Crear estudiante' ?></button>
            <a class="btnS" href="students.php">← Cancelar</a>
          </div>
        </form>
      </section>
    </main>
  </div>
</body>
</html>