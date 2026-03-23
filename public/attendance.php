<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';
require __DIR__ . '/../app/helpers/csrf.php';

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

$groupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
$date = trim((string)($_GET['date'] ?? date('Y-m-d')));

if ($groupId <= 0) {
    exit('Grupo inválido');
}

// =====================================================
// Traer grupo
// =====================================================
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

// Si es docente, solo puede entrar a sus grupos
if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

if (($group['status'] ?? '') === 'finalizado') {
    exit('Este grupo ya fue finalizado y no admite nuevas asistencias.');
}

// =====================================================
// Traer estudiantes del grupo
// =====================================================
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.full_name,
        s.student_code
    FROM group_students gs
    JOIN students s ON s.id = gs.student_id
    WHERE gs.group_id = ?
    ORDER BY s.full_name ASC
");
$stmt->execute([$groupId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// Buscar asistencia del día
// =====================================================
$stmt = $pdo->prepare("
    SELECT *
    FROM attendances
    WHERE group_id = ? AND attendance_date = ?
    LIMIT 1
");
$stmt->execute([$groupId, $date]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe, crearla
if (!$attendance) {
    $ins = $pdo->prepare("
        INSERT INTO attendances (group_id, teacher_id, attendance_date)
        VALUES (?, ?, ?)
    ");
    $ins->execute([$groupId, (int)$group['teacher_id'], $date]);

    $attendanceId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT *
        FROM attendances
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$attendanceId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
}

$attendanceId = (int)$attendance['id'];

// =====================================================
// Sincronizar attendance_items
// =====================================================
$stmt = $pdo->prepare("
    SELECT student_id
    FROM attendance_items
    WHERE attendance_id = ?
");
$stmt->execute([$attendanceId]);
$existingStudentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
$existingStudentIds = array_map('intval', $existingStudentIds);

$insItem = $pdo->prepare("
    INSERT INTO attendance_items (attendance_id, student_id, present)
    VALUES (?, ?, 0)
");

foreach ($students as $s) {
    $studentId = (int)$s['id'];
    if (!in_array($studentId, $existingStudentIds, true)) {
        $insItem->execute([$attendanceId, $studentId]);
    }
}

// =====================================================
// Guardar asistencia
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf_or_die();
    $attendanceStatus = $_POST['attendance'] ?? [];

    $upd = $pdo->prepare("
        UPDATE attendance_items
        SET present = ?
        WHERE attendance_id = ? AND student_id = ?
    ");

    foreach ($students as $s) {
        $studentId = (int)$s['id'];
        $present = isset($attendanceStatus[$studentId]) ? (int)$attendanceStatus[$studentId] : 0;
        $upd->execute([$present, $attendanceId, $studentId]);
    }

    header("Location: attendance.php?group_id={$groupId}&date={$date}&saved=1");
    exit;
}

// =====================================================
// Traer asistencia actual
// =====================================================
$stmt = $pdo->prepare("
    SELECT ai.student_id, ai.present
    FROM attendance_items ai
    WHERE ai.attendance_id = ?
");
$stmt->execute([$attendanceId]);

$presentMap = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $presentMap[(int)$row['student_id']] = (int)$row['present'];
}

$saved = isset($_GET['saved']) && $_GET['saved'] == '1';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asistencia</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1180px;width:100%;margin:0 auto}
    .sheet{background:#ffffff;color:#111827;border-radius:18px;box-shadow:0 18px 50px rgba(0,0,0,.25);overflow:hidden}
    .sheet-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:24px 24px 10px;flex-wrap:wrap}
    .sheet-brand img{height:62px;object-fit:contain}
    .sheet-brand h2{margin:10px 0 8px;font-size:28px;line-height:1.1}
    .meta{display:grid;grid-template-columns:repeat(2, minmax(220px, 1fr));gap:10px 18px;margin-top:10px}
    .meta p{margin:0;font-size:14px;color:#374151}
    .meta b{color:#111827}
    .date-box{display:flex;gap:10px;align-items:end;flex-wrap:wrap}
    .date-box label{display:block;font-size:13px;margin-bottom:6px;color:#374151;font-weight:700}
    .date-box input[type="date"]{background:#f3f4f6;color:#111827;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px}
    .toolbar-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:1px solid #bbf7d0;background:#ecfdf5;color:#16a34a;font-weight:800;text-decoration:none;cursor:pointer}
    .toolbar-btn.back{border-color:#d1d5db;background:#f9fafb;color:#374151}
    .toolbar-btn.print{border-color:#bbf7d0;background:#ecfdf5;color:#16a34a}
    .table-wrap{padding:16px 24px 8px}
    table{width:100%;border-collapse:separate;border-spacing:0;font-size:14px;overflow:hidden;border:1px solid #e5e7eb;border-radius:14px}
    thead th{background:#111827;color:#ffffff;text-align:left;padding:14px 16px;font-size:13px;letter-spacing:.2px}
    tbody td{padding:14px 16px;border-top:1px solid #e5e7eb;background:#ffffff;vertical-align:middle}
    tbody tr:nth-child(even) td{background:#f9fafb}
    .col-num{width:90px;font-weight:800;color:#374151}
    .student-name{font-weight:700;color:#111827}
    .student-code{font-size:12px;color:#6b7280;margin-top:4px}
    .attendance-cell{min-width:280px}
    .attendance-group{display:flex;gap:12px;flex-wrap:wrap}
    .attendance-option input{display:none}
    .attendance-option span{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:2px solid #d1d5db;background:#ffffff;color:#374151;font-weight:800;cursor:pointer;transition:.15s ease;user-select:none}
    .attendance-option.present input:checked + span{background:#dcfce7;border-color:#22c55e;color:#166534}
    .attendance-option.absent input:checked + span{background:#fee2e2;border-color:#ef4444;color:#991b1b}
    .attendance-option span:hover{transform:translateY(-1px)}
    .sheet-footer{padding:20px 24px 26px}
    .sign{margin-top:26px;padding-top:18px;width:320px;border-top:1px solid #9ca3af;color:#374151;font-size:14px}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
    .save-btn{width:100%;padding:14px 18px;border:none;border-radius:14px;background:linear-gradient(180deg, #34d399, #16a34a);color:#052e16;font-weight:900;cursor:pointer;font-size:15px}
    .save-btn:hover{filter:brightness(.98)}
    .empty{padding:24px;text-align:center;color:#6b7280;font-weight:600}
    .ok-msg{margin:0 24px 8px;padding:12px 14px;border-radius:12px;background:#ecfdf5;border:1px solid #bbf7d0;color:#166534;font-weight:700}
    @media print{
      body{background:#fff}
      .no-print,.sidebar,#appSidebar{display:none !important}
      .container{padding:0;max-width:none}
      .sheet{box-shadow:none;border-radius:0}
      .attendance-option span{border:1px solid #9ca3af;background:#fff !important;color:#111 !important}
    }
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="sheet">
    <div class="sheet-head">
      <div class="sheet-brand">
        <img src="/docentes/assets/images/1.png" alt="Logo CONATRADEC">
        <h2>Hoja de Asistencia</h2>

        <div class="meta">
          <p><b>Grupo:</b> <?= h($group['name']) ?></p>
          <p><b>Código:</b> <?= h($group['group_code']) ?></p>
          <p><b>Curso:</b> <?= h(courseLabel($group['course_type'])) ?></p>
          <p><b>Nivel:</b> <?= h(levelLabel($group['course_level'])) ?></p>
          <p><b>Docente:</b> <?= h($group['teacher_name']) ?></p>
          <p><b>Fecha:</b> <?= h($date) ?></p>
        </div>
      </div>

      <div class="date-box no-print">
        <form method="get">
          <input type="hidden" name="group_id" value="<?= $groupId ?>">
          <label for="date">Cambiar fecha</label>
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input id="date" type="date" name="date" value="<?= h($date) ?>">
            <button class="toolbar-btn" type="submit">Actualizar</button>
          </div>
        </form>
      </div>
    </div>

    <?php if ($saved): ?>
      <div class="ok-msg">Asistencia guardada correctamente.</div>
    <?php endif; ?>

    <form method="post">
      <?php echo csrf_input(); ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px;">Número</th>
              <th>Nombre</th>
              <th style="width:330px;">Asistencia</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students): ?>
              <?php $i = 1; foreach ($students as $s): ?>
                <?php
                  $studentId = (int)$s['id'];
                  $isPresent = isset($presentMap[$studentId]) ? (int)$presentMap[$studentId] : 0;
                ?>
                <tr>
                  <td class="col-num"><?= $i++ ?></td>
                  <td>
                    <div class="student-name"><?= h($s['full_name']) ?></div>
                    <div class="student-code"><?= h($s['student_code'] ?: '—') ?></div>
                  </td>
                  <td class="attendance-cell">
                    <div class="attendance-group">
                      <label class="attendance-option present">
                        <input type="radio" name="attendance[<?= $studentId ?>]" value="1" <?= $isPresent === 1 ? 'checked' : '' ?>>
                        <span>✔ Presente</span>
                      </label>

                      <label class="attendance-option absent">
                        <input type="radio" name="attendance[<?= $studentId ?>]" value="0" <?= $isPresent === 0 ? 'checked' : '' ?>>
                        <span>✘ Ausente</span>
                      </label>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" class="empty">No hay estudiantes asignados a este grupo.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="sheet-footer">
        <div class="sign">
          Firma del docente
        </div>

        <div class="actions no-print">
          <button class="save-btn" type="submit">Guardar asistencia</button>
          <button class="toolbar-btn print" type="button" onclick="window.print()">Imprimir / Exportar PDF</button>
          <a class="toolbar-btn back" href="groups.php">← Volver</a>
        </div>
      </div>
    </form>
  </section>
</main>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('appSidebar');
  if (!sidebar) return;

  if (window.innerWidth <= 960) {
    sidebar.classList.toggle('open');
  } else {
    sidebar.classList.toggle('collapsed');
  }
}
</script>
</body>
</html>