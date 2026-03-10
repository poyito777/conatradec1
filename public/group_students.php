<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$groupId = (int)($_GET['id'] ?? $_POST['group_id'] ?? 0);

if ($groupId <= 0) {
    exit('Grupo inválido');
}

// Traer grupo
$stmt = $pdo->prepare("
    SELECT g.*, u.name AS teacher_name
    FROM groups_table g
    JOIN users u ON u.id = g.teacher_id
    WHERE g.id = ?
    LIMIT 1
");
$stmt->execute([$groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    exit('Grupo no encontrado');
}

// Permisos: docente solo sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

// Si el grupo ya está finalizado, no permitir cambios
if (($group['status'] ?? '') === 'finalizado') {
    exit('Este grupo ya fue finalizado y no admite cambios.');
}

// Guardar asignaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentIds = $_POST['student_ids'] ?? [];

    // Borrar asignaciones anteriores del grupo
    $del = $pdo->prepare("DELETE FROM group_students WHERE group_id = ?");
    $del->execute([$groupId]);

    if (!empty($studentIds)) {
        $ins = $pdo->prepare("INSERT INTO group_students (group_id, student_id) VALUES (?, ?)");

        foreach ($studentIds as $studentId) {
            $ins->execute([$groupId, (int)$studentId]);
        }
    }

    header("Location: group_students.php?id={$groupId}&saved=1");
    exit;
}

// Búsqueda
$q = trim($_GET['q'] ?? '');

// Traer estudiantes del mismo curso que el grupo
// Admin: todos los estudiantes de ese curso
// Teacher: solo sus estudiantes de ese curso
$where = ["s.course_type = ?", "s.course_level = ?"];
$params = [$group['course_type'], $group['course_level']];

if (($me['role'] ?? '') === 'teacher') {
    $where[] = "s.teacher_id = ?";
    $params[] = (int)$me['id'];
}

if ($q !== '') {
    $where[] = "(s.full_name LIKE ? OR s.student_code LIKE ?)";
    $like = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
}

$sqlWhere = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT s.*
    FROM students s
    $sqlWhere
    ORDER BY s.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traer estudiantes ya asignados
$stmt = $pdo->prepare("SELECT student_id FROM group_students WHERE group_id = ?");
$stmt->execute([$groupId]);
$assignedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$assignedIds = array_map('intval', $assignedIds);

$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asignar estudiantes</title>
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
      max-width:1180px;
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
    .toolbar{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
      margin:14px 0 18px;
    }
    .toolbar input[type="text"]{
      flex:1;
      min-width:260px;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.05);
      color:white;
      outline:none;
    }
    .mini-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
      font-weight:800;
      cursor:pointer;
      text-decoration:none;
    }
    .mini-btn.gray{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
    }
    table{
      width:100%;
      border-collapse:collapse;
      margin-top:8px;
    }
    th,td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      vertical-align:middle;
      font-size:14px;
    }
    th{
      color:var(--muted);
      background:rgba(255,255,255,.04);
    }
    .code{
      font-weight:800;
      color:#e5e7eb;
    }
    .chip{
      display:inline-block;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid var(--line);
      font-size:12px;
    }
    .status-p{
      color:#fde68a;
      border-color:rgba(245,158,11,.35);
    }
    .status-a{
      color:var(--green);
      border-color:rgba(47,191,113,.35);
    }
    .status-d{
      color:#fca5a5;
      border-color:rgba(239,68,68,.35);
    }
    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:16px;
    }
    .empty{
      padding:22px;
      text-align:center;
      color:var(--muted);
      font-weight:700;
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

  </header>

  <main class="container">
    <section class="panel">
      <h2 style="margin:0 0 8px;">Asignar estudiantes al grupo</h2>
      <p style="margin:0;color:var(--muted);">
        <b><?= h($group['name']) ?></b> •
        <?= h($group['group_code']) ?> •
        <?= h($group['course_type']) ?> / <?= h($group['course_level']) ?>
      </p>

      <?php if ($saved): ?>
        <div class="ok-msg">Estudiantes del grupo guardados correctamente.</div>
      <?php endif; ?>

      <!-- búsqueda -->
      <form method="get" class="toolbar">
        <input type="hidden" name="id" value="<?= $groupId ?>">

        <input
          type="text"
          name="q"
          value="<?= h($q) ?>"
          placeholder="Buscar estudiante por nombre o código..."
        >

        <button class="mini-btn" type="submit">Buscar</button>
        <a class="mini-btn gray" href="group_students.php?id=<?= $groupId ?>">Limpiar</a>
      </form>

      <!-- asignación -->
      <form method="post" id="groupStudentsForm">
        <input type="hidden" name="group_id" value="<?= $groupId ?>">

        <div class="toolbar" style="margin-top:0;">
          <button class="mini-btn" type="button" onclick="selectAllStudents()">Seleccionar todos</button>
          <button class="mini-btn gray" type="button" onclick="clearAllStudents()">Quitar todos</button>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width:120px;">Seleccionar</th>
              <th>Nombre</th>
              <th style="width:140px;">Código</th>
              <th>Escuela</th>
              <th style="width:140px;">Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students): ?>
              <?php foreach ($students as $s): ?>
                <?php
                  $statusClass = 'status-p';
                  if (($s['status'] ?? '') === 'aprobado') $statusClass = 'status-a';
                  if (($s['status'] ?? '') === 'desaprobado') $statusClass = 'status-d';
                ?>
                <tr>
                  <td>
                    <input
                      class="student-checkbox"
                      type="checkbox"
                      name="student_ids[]"
                      value="<?= (int)$s['id'] ?>"
                      <?= in_array((int)$s['id'], $assignedIds, true) ? 'checked' : '' ?>
                    >
                  </td>
                  <td><?= h($s['full_name']) ?></td>
                  <td class="code"><?= h($s['student_code'] ?: '—') ?></td>
                  <td><?= h($s['school'] ?: '—') ?></td>
                  <td>
                    <span class="chip <?= $statusClass ?>">
                      <?= h($s['status'] ?: 'pendiente') ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="empty">
                  No se encontraron estudiantes del curso <?= h($group['course_type']) ?> con esa búsqueda.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="actions">
          <button class="btn" type="submit">Guardar estudiantes del grupo</button>
          <a class="mini-btn gray" href="groups.php">← Volver</a>
        </div>
      </form>
    </section>
  </main>
</div>

<script>
function selectAllStudents() {
  document.querySelectorAll('.student-checkbox').forEach(cb => {
    cb.checked = true;
  });
}

function clearAllStudents() {
  document.querySelectorAll('.student-checkbox').forEach(cb => {
    cb.checked = false;
  });
}
</script>
</body>
</html>