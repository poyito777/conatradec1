<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function courseLabel($t){
    return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
    if ($l === 'avanzado') return 'Avanzado';
    if ($l === 'intensivo') return 'Intensivo';
    return 'Básico';
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

$isFinalized = (($group['status'] ?? '') === 'finalizado');

// Guardar asignaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isFinalized) {
        exit('Este grupo ya fue finalizado y no admite cambios.');
    }

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

    .muted{
      color:var(--muted);
    }

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom:8px;
    }

    .group-meta{
      margin-top:10px;
      padding:14px;
      border-radius:14px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.04);
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:12px;
    }

    .group-meta .k{
      font-size:12px;
      color:var(--muted);
      margin-bottom:4px;
    }

    .group-meta .v{
      font-weight:800;
      color:#e5e7eb;
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

    .warn-msg{
      margin-top:14px;
      padding:12px 14px;
      border-radius:12px;
      background:rgba(245,158,11,.10);
      border:1px solid rgba(245,158,11,.35);
      color:#f59e0b;
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

    .mini-btn.disabled{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.04);
      color:#94a3b8;
      cursor:not-allowed;
      pointer-events:none;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:8px;
    }

    table{
      width:100%;
      border-collapse:collapse;
      min-width:900px;
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
      white-space:nowrap;
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

    input.student-checkbox[disabled]{
      opacity:.55;
      cursor:not-allowed;
    }

    @media(max-width:960px){
      .group-meta{
        grid-template-columns:1fr 1fr;
      }
    }

    @media(max-width:640px){
      .group-meta{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 8px;">Asignar estudiantes al grupo</h2>
        <p style="margin:0;color:var(--muted);">
          Administrá los estudiantes asignados al grupo.
        </p>
      </div>
    </div>

    <div class="group-meta">
      <div>
        <div class="k">Grupo</div>
        <div class="v"><?= h($group['name']) ?></div>
      </div>
      <div>
        <div class="k">Código</div>
        <div class="v"><?= h($group['group_code']) ?></div>
      </div>
      <div>
        <div class="k">Curso</div>
        <div class="v"><?= h(courseLabel($group['course_type'])) ?> / <?= h(levelLabel($group['course_level'])) ?></div>
      </div>
      <div>
        <div class="k">Estado</div>
        <div class="v"><?= h($group['status']) ?></div>
      </div>
    </div>

    <?php if ($saved): ?>
      <div class="ok-msg">Estudiantes del grupo guardados correctamente.</div>
    <?php endif; ?>

    <?php if ($isFinalized): ?>
      <div class="warn-msg">
        Este grupo ya fue finalizado. La información se muestra en modo solo lectura y no admite cambios.
      </div>
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
        <?php if (!$isFinalized): ?>
          <button class="mini-btn" type="button" onclick="selectAllStudents()">Seleccionar todos</button>
          <button class="mini-btn gray" type="button" onclick="clearAllStudents()">Quitar todos</button>
        <?php else: ?>
          <span class="mini-btn disabled">Grupo finalizado</span>
        <?php endif; ?>
      </div>

      <div class="table-wrap">
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
                      <?= $isFinalized ? 'disabled' : '' ?>
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
                  No se encontraron estudiantes del curso <?= h(courseLabel($group['course_type'])) ?> con esa búsqueda.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="actions">
        <?php if (!$isFinalized): ?>
          <button class="btn" type="submit">Guardar estudiantes del grupo</button>
        <?php else: ?>
          <span class="mini-btn disabled">Solo lectura</span>
        <?php endif; ?>
        <a class="mini-btn gray" href="groups.php">← Volver</a>
      </div>
    </form>
  </section>
</main>

<script>
function selectAllStudents() {
  document.querySelectorAll('.student-checkbox:not([disabled])').forEach(cb => {
    cb.checked = true;
  });
}

function clearAllStudents() {
  document.querySelectorAll('.student-checkbox:not([disabled])').forEach(cb => {
    cb.checked = false;
  });
}
</script>
</body>
</html>