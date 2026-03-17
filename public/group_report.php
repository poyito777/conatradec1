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

function statusClass($status){
    if ($status === 'aprobado') return 'ok';
    if ($status === 'desaprobado') return 'bad';
    return 'pending';
}

$groupId = (int)($_GET['id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    exit('Grupo inválido');
}

// =====================================================
// Traer grupo
// =====================================================
$stmt = $pdo->prepare("
    SELECT
        g.*,
        u.name AS teacher_name,
        u.email AS teacher_email
    FROM groups_table g
    JOIN users u ON u.id = g.teacher_id
    WHERE g.id = ?
    LIMIT 1
");
$stmt->execute([$groupId]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    http_response_code(404);
    exit('Grupo no encontrado');
}

if (($me['role'] ?? '') === 'teacher' && (int)$group['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit('Acceso denegado');
}

// =====================================================
// Traer estudiantes del grupo + notas
// =====================================================
$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.student_code,
        s.full_name,
        s.school,
        s.status,
        sg.exam1,
        sg.exam2,
        sg.exam3,
        sg.exam4,
        sg.exam5,
        sg.final_grade
    FROM group_students gs
    JOIN students s ON s.id = gs.student_id
    LEFT JOIN student_grades sg
        ON sg.group_id = gs.group_id
       AND sg.student_id = gs.student_id
    WHERE gs.group_id = ?
    ORDER BY s.full_name ASC
");
$stmt->execute([$groupId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// Resumen de asistencia por estudiante
// =====================================================
$stmt = $pdo->prepare("
    SELECT
        ai.student_id,
        COUNT(ai.id) AS total_records,
        SUM(CASE WHEN ai.present = 1 THEN 1 ELSE 0 END) AS total_present
    FROM attendances a
    JOIN attendance_items ai ON ai.attendance_id = a.id
    WHERE a.group_id = ?
    GROUP BY ai.student_id
");
$stmt->execute([$groupId]);
$attendanceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attendanceMap = [];
foreach ($attendanceRows as $row) {
    $studentId = (int)$row['student_id'];
    $totalRecords = (int)$row['total_records'];
    $totalPresent = (int)$row['total_present'];
    $percent = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100, 2) : null;

    $attendanceMap[$studentId] = [
        'total_records' => $totalRecords,
        'total_present' => $totalPresent,
        'percent' => $percent
    ];
}

// =====================================================
// Resumen general
// =====================================================
$totalStudents = count($students);
$totalApproved = 0;
$totalFailed = 0;
$totalPending = 0;
$sumFinalGrades = 0;
$countFinalGrades = 0;

foreach ($students as $s) {
    $status = $s['status'] ?? 'pendiente';

    if ($status === 'aprobado') $totalApproved++;
    elseif ($status === 'desaprobado') $totalFailed++;
    else $totalPending++;

    if ($s['final_grade'] !== null && $s['final_grade'] !== '') {
        $sumFinalGrades += (float)$s['final_grade'];
        $countFinalGrades++;
    }
}

$groupAverage = $countFinalGrades > 0 ? round($sumFinalGrades / $countFinalGrades, 2) : null;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte consolidado del grupo</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{
      padding:26px;
      max-width:1380px;
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

    .hero{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
    }

    .muted{
      color:var(--muted);
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
    }

    .btnS,.btnG,.btn2{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:14px;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .btnS{
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
      color:var(--green);
    }

    .btnG{
      border:1px solid rgba(148,163,184,.25);
      background:rgba(255,255,255,.05);
      color:#cbd5e1;
    }

    .btn2{
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      border:none;
    }

    .summary-grid{
      display:grid;
      grid-template-columns:repeat(5,1fr);
      gap:12px;
      margin-top:16px;
    }

    .summary-card{
      border:1px solid var(--line);
      border-radius:16px;
      padding:14px;
      background:rgba(255,255,255,.05);
    }

    .summary-card .k{
      font-size:12px;
      color:var(--muted);
      margin-bottom:6px;
    }

    .summary-card .v{
      font-size:26px;
      font-weight:900;
      color:#e5e7eb;
      line-height:1;
    }

    .group-info{
      margin-top:16px;
      padding:14px;
      border-radius:14px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.04);
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:12px;
    }

    .group-info .k{
      font-size:12px;
      color:var(--muted);
      margin-bottom:4px;
    }

    .group-info .v{
      font-weight:800;
      color:#e5e7eb;
    }

    .table-wrap{
      overflow-x:auto;
      margin-top:16px;
    }

    table{
      width:100%;
      min-width:1250px;
      border-collapse:collapse;
    }

    th, td{
      padding:12px;
      border-bottom:1px solid var(--line);
      text-align:left;
      font-size:13px;
      vertical-align:middle;
    }

    th{
      color:var(--muted);
      font-weight:700;
      background:rgba(255,255,255,.04);
      white-space:nowrap;
    }

    tr:hover{
      background:rgba(255,255,255,.04);
    }

    .student-name{
      font-weight:800;
      color:#e5e7eb;
    }

    .small{
      font-size:12px;
      color:var(--muted);
      margin-top:4px;
    }

    .status-pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:110px;
      padding:8px 10px;
      border-radius:999px;
      font-size:12px;
      font-weight:800;
      text-transform:capitalize;
      border:1px solid var(--line);
    }

    .status-pill.ok{
      color:#22c55e;
      border-color:rgba(34,197,94,.35);
      background:rgba(34,197,94,.10);
    }

    .status-pill.bad{
      color:#ef4444;
      border-color:rgba(239,68,68,.35);
      background:rgba(239,68,68,.10);
    }

    .status-pill.pending{
      color:#f59e0b;
      border-color:rgba(245,158,11,.35);
      background:rgba(245,158,11,.10);
    }

    .empty{
      padding:20px;
      text-align:center;
      color:var(--muted);
      font-weight:700;
    }

    @media(max-width:1100px){
      .summary-grid{
        grid-template-columns:repeat(2,1fr);
      }

      .group-info{
        grid-template-columns:1fr 1fr;
      }
    }

    @media(max-width:640px){
      .summary-grid,
      .group-info{
        grid-template-columns:1fr;
      }
    }

    @page{
      size: landscape;
      margin: 10mm;
    }

    @media print{
      body{
        background:#fff !important;
        color:#000 !important;
      }

      .actions,
      .sidebar,
      #appSidebar{
        display:none !important;
      }

      .container{
        max-width:none !important;
        width:100% !important;
        padding:0 !important;
        margin:0 !important;
      }

      .panel{
        box-shadow:none !important;
        border:none !important;
        background:#fff !important;
        color:#000 !important;
        padding:0 !important;
      }

      .hero{
        margin-bottom:8px !important;
      }

      .hero h2{
        font-size:18px !important;
        margin:0 0 4px !important;
        color:#000 !important;
      }

      .hero p,
      .muted,
      .small,
      .group-info .k,
      .summary-card .k{
        color:#444 !important;
      }

      .summary-grid{
        grid-template-columns:repeat(5,1fr) !important;
        gap:6px !important;
        margin-top:8px !important;
        margin-bottom:8px !important;
        page-break-inside:avoid;
      }

      .summary-card{
        padding:8px !important;
        border:1px solid #bbb !important;
        background:#fff !important;
        border-radius:8px !important;
      }

      .summary-card .v{
        font-size:18px !important;
        color:#000 !important;
      }

      .group-info{
        grid-template-columns:repeat(4,1fr) !important;
        gap:6px !important;
        margin-top:8px !important;
        margin-bottom:10px !important;
        padding:8px !important;
        border:1px solid #bbb !important;
        background:#fff !important;
        border-radius:8px !important;
        page-break-inside:avoid;
      }

      .group-info .v{
        color:#000 !important;
        font-size:13px !important;
        font-weight:700 !important;
      }

      .table-wrap{
        overflow:visible !important;
        margin-top:8px !important;
      }

      table{
        width:100% !important;
        min-width:0 !important;
        border-collapse:collapse !important;
        table-layout:fixed !important;
        page-break-inside:auto;
      }

      thead{
        display:table-header-group;
      }

      tr{
        page-break-inside:avoid;
        page-break-after:auto;
      }

      th, td{
        color:#000 !important;
        background:#fff !important;
        border:1px solid #bbb !important;
        padding:6px 7px !important;
        font-size:11px !important;
        vertical-align:top !important;
        word-wrap:break-word;
      }

      th{
        font-weight:700 !important;
      }

      .student-name{
        color:#000 !important;
        font-weight:700 !important;
      }

      .status-pill{
        min-width:auto !important;
        padding:4px 8px !important;
        border:1px solid #999 !important;
        background:#fff !important;
        color:#000 !important;
        font-size:10px !important;
      }

      .status-pill.ok,
      .status-pill.bad,
      .status-pill.pending{
        color:#000 !important;
        border-color:#999 !important;
        background:#fff !important;
      }

      .empty{
        color:#000 !important;
        border:1px solid #bbb !important;
        background:#fff !important;
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
        <h2 style="margin:0 0 6px;">Reporte consolidado del grupo</h2>
        <p style="margin:0;color:var(--muted);">
          Resumen académico y de asistencia del grupo seleccionado.
        </p>
      </div>

      <div class="actions">
        <a class="btnG" href="groups.php">← Volver</a>
        <button class="btn2" type="button" onclick="window.print()">Imprimir / Exportar PDF</button>
      </div>
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <div class="k">Total estudiantes</div>
        <div class="v"><?= (int)$totalStudents ?></div>
      </div>

      <div class="summary-card">
        <div class="k">Promedio del grupo</div>
        <div class="v"><?= $groupAverage !== null ? h($groupAverage) : '—' ?></div>
      </div>

      <div class="summary-card">
        <div class="k">Aprobados</div>
        <div class="v"><?= (int)$totalApproved ?></div>
      </div>

      <div class="summary-card">
        <div class="k">Desaprobados</div>
        <div class="v"><?= (int)$totalFailed ?></div>
      </div>

      <div class="summary-card">
        <div class="k">Pendientes</div>
        <div class="v"><?= (int)$totalPending ?></div>
      </div>
    </div>

    <div class="group-info">
      <div>
        <div class="k">Código</div>
        <div class="v"><?= h($group['group_code']) ?></div>
      </div>
      <div>
        <div class="k">Grupo</div>
        <div class="v"><?= h($group['name']) ?></div>
      </div>
      <div>
        <div class="k">Curso</div>
        <div class="v"><?= h(courseLabel($group['course_type'])) ?> / <?= h(levelLabel($group['course_level'])) ?></div>
      </div>
      <div>
        <div class="k">Docente</div>
        <div class="v"><?= h($group['teacher_name']) ?></div>
      </div>
      <div>
        <div class="k">Horario</div>
        <div class="v"><?= h($group['schedule'] ?: '—') ?></div>
      </div>
      <div>
        <div class="k">Ubicación</div>
        <div class="v"><?= h($group['location'] ?: '—') ?></div>
      </div>
      <div>
        <div class="k">Fecha inicio</div>
        <div class="v"><?= h($group['start_date'] ?: '—') ?></div>
      </div>
      <div>
        <div class="k">Fecha fin</div>
        <div class="v"><?= h($group['end_date'] ?: '—') ?></div>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:24%;">Estudiante</th>
            <th style="width:10%;">Asistencia</th>
            <th style="width:7%;">Prueba 1</th>
            <th style="width:7%;">Prueba 2</th>
            <th style="width:7%;">Prueba 3</th>
            <th style="width:7%;">Prueba 4</th>
            <th style="width:7%;">Prueba 5</th>
            <th style="width:9%;">Nota final</th>
            <th style="width:12%;">Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($students): ?>
            <?php foreach ($students as $s): ?>
              <?php
                $studentId = (int)$s['id'];
                $attendance = $attendanceMap[$studentId] ?? null;
                $attendancePercent = $attendance['percent'] ?? null;
                $attendancePresent = $attendance['total_present'] ?? 0;
                $attendanceTotal = $attendance['total_records'] ?? 0;
              ?>
              <tr>
                <td>
                  <div class="student-name"><?= h($s['full_name']) ?></div>
                  <div class="small">
                    <?= h($s['student_code'] ?: '—') ?> • <?= h($s['school'] ?: '—') ?>
                  </div>
                </td>

                <td>
                  <?= $attendancePercent !== null ? h($attendancePercent . '%') : '—' ?>
                  <div class="small">
                    <?= $attendanceTotal > 0 ? h($attendancePresent . '/' . $attendanceTotal . ' presentes') : 'Sin registros' ?>
                  </div>
                </td>

                <td><?= h($s['exam1'] ?? '—') ?></td>
                <td><?= h($s['exam2'] ?? '—') ?></td>
                <td><?= h($s['exam3'] ?? '—') ?></td>
                <td><?= h($s['exam4'] ?? '—') ?></td>
                <td><?= h($s['exam5'] ?? '—') ?></td>
                <td><strong><?= h($s['final_grade'] ?? '—') ?></strong></td>
                <td>
                  <span class="status-pill <?= h(statusClass($s['status'] ?: 'pendiente')) ?>">
                    <?= h($s['status'] ?: 'pendiente') ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9" class="empty">Este grupo no tiene estudiantes asignados.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
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
</body>
</html>