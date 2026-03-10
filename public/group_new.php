<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$error = '';
$success = '';

$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("SELECT id, name, email FROM users WHERE role='teacher' AND is_active=1 ORDER BY name ASC")->fetchAll();
}

function generate_group_code(PDO $pdo, string $courseType, string $courseLevel): string {
  $typeMap = [
    'barismo' => 'BAR',
    'catacion' => 'CAT'
  ];

  $levelMap = [
    'basico' => 'BAS',
    'avanzado' => 'AVA',
    'intensivo' => 'INT'
  ];

  $prefix = $typeMap[$courseType] . '-' . $levelMap[$courseLevel] . '-' . date('Y');

  $stmt = $pdo->prepare("SELECT COUNT(*) total FROM groups_table WHERE group_code LIKE ?");
  $stmt->execute([$prefix . '%']);
  $count = (int)$stmt->fetchColumn() + 1;

  return $prefix . '-' . str_pad((string)$count, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $teacher_id = (($me['role'] ?? '') === 'admin')
    ? (int)($_POST['teacher_id'] ?? 0)
    : (int)$me['id'];

  $name = trim($_POST['name'] ?? '');
  $course_type = trim($_POST['course_type'] ?? '');
  $course_level = trim($_POST['course_level'] ?? '');
  $start_date = trim($_POST['start_date'] ?? '');
  $end_date = trim($_POST['end_date'] ?? '');
  $capacity = trim($_POST['capacity'] ?? '');
  $schedule = trim($_POST['schedule'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if ($name === '') {
    $error = 'El nombre del grupo es obligatorio.';
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = 'Curso inválido.';
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = 'Nivel inválido.';
  } elseif ($teacher_id <= 0) {
    $error = 'Seleccioná un docente.';
  } else {
    $group_code = generate_group_code($pdo, $course_type, $course_level);

    $stmt = $pdo->prepare("
      INSERT INTO groups_table
      (teacher_id, group_code, name, capacity, schedule, location, course_type, course_level, start_date, end_date, status, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo', ?)
    ");

    $stmt->execute([
      $teacher_id,
      $group_code,
      $name,
      ($capacity === '' ? null : (int)$capacity),
      $schedule ?: null,
      $location ?: null,
      $course_type,
      $course_level,
      $start_date ?: null,
      $end_date ?: null,
      $notes ?: null
    ]);

    header("Location: groups.php");
    exit;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Crear grupo</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:900px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
    .col6{grid-column:span 6}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col6{grid-column:span 12}}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

  </header>

  <main class="container">
    <section class="panel">
      <h2 style="margin:0 0 8px;">Nuevo grupo</h2>
      <p style="margin:0;color:var(--muted);">El código se genera automáticamente al guardar.</p>

      <?php if ($error): ?>
        <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" style="margin-top:14px;">
        <div class="grid">

          <?php if (($me['role'] ?? '') === 'admin'): ?>
            <div class="field col12">
              <label>Docente</label>
              <select name="teacher_id" required>
                <option value="">Seleccionar docente</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= (int)$t['id'] ?>">
                    <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="field col12">
            <label>Nombre del grupo</label>
            <input name="name" required placeholder="Ej: Grupo A Marzo 2026">
          </div>

          <div class="field col6">
            <label>Curso</label>
            <select name="course_type" required>
              <option value="">Seleccionar</option>
              <option value="barismo">Barismo</option>
              <option value="catacion">Catación</option>
            </select>
          </div>

          <div class="field col6">
            <label>Nivel</label>
            <select name="course_level" required>
              <option value="">Seleccionar</option>
              <option value="basico">Básico</option>
              <option value="avanzado">Avanzado</option>
              <option value="intensivo">Intensivo</option>
            </select>
          </div>

          <div class="field col6">
            <label>Fecha inicio</label>
            <input type="date" name="start_date">
          </div>

          <div class="field col6">
            <label>Fecha fin</label>
            <input type="date" name="end_date">
          </div>

          <div class="field col6">
            <label>Cupo</label>
            <input type="number" name="capacity" min="1" placeholder="Ej: 20">
          </div>

          <div class="field col6">
            <label>Horario</label>
            <input name="schedule" placeholder="Ej: Lunes y miércoles 2:00 PM">
          </div>

          <div class="field col12">
            <label>Ubicación</label>
            <input name="location" placeholder="Ej: Aula 2, CONATRADEC Managua">
          </div>

          <div class="field col12">
            <label>Notas</label>
            <textarea name="notes" rows="4"></textarea>
          </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
          <button class="btn" type="submit">Crear grupo</button>
          <a class="btnS" href="groups.php">← Volver</a>
        </div>
      </form>
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
</div>
</body>
</html>