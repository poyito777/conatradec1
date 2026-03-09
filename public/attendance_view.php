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
    exit('Asistencia inválida');
}

$stmt = $pdo->prepare("
    SELECT
        a.*,
        g.id AS group_id,
        g.group_code,
        g.name AS group_name,
        g.course_type,
        g.course_level,
        g.teacher_id,
        g.location,
        g.schedule,
        u.name AS teacher_name
    FROM attendances a
    JOIN groups_table g ON g.id = a.group_id
    JOIN users u ON u.id = g.teacher_id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    exit('Asistencia no encontrada');
}

if (($me['role'] ?? '') === 'teacher' && (int)$attendance['teacher_id'] !== (int)$me['id']) {
    exit('Acceso denegado');
}

$stmt = $pdo->prepare("
    SELECT
        s.full_name,
        s.student_code,
        s.department,
        ai.present
    FROM attendance_items ai
    JOIN students s ON s.id = ai.student_id
    WHERE ai.attendance_id = ?
    ORDER BY s.full_name ASC
");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPresent = 0;
$totalAbsent = 0;

foreach ($items as $item) {
    if ((int)$item['present'] === 1) {
        $totalPresent++;
    } else {
        $totalAbsent++;
    }
}

$totalStudents = count($items);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Detalle de asistencia</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }

    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      padding:14px 24px;
      background:rgba(0,0,0,.35);
      border-bottom:1px solid var(--line);
      backdrop-filter:blur(8px);
    }

    .logo{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:700;
    }

    .logo img{
      width:34px;
      height:34px;
      object-fit:contain;
    }

    .nav{
      display:flex;
      gap:10px;
      align-items:center;
      flex-wrap:wrap;
    }

    .nav a{
      padding:8px 12px;
      border:1px solid var(--line);
      border-radius:12px;
      background:rgba(255,255,255,.06);
    }

    .container{
      padding:26px;
      max-width:1180px;
      width:100%;
      margin:0 auto;
    }

    .sheet{
      background:#fff;
      color:#111827;
      border-radius:20px;
      box-shadow:0 18px 50px rgba(0,0,0,.25);
      overflow:hidden;
    }

    .head{
      padding:26px 26px 12px;
      border-bottom:1px solid #e5e7eb;
    }

    .brand-row{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:18px;
      flex-wrap:wrap;
    }

    .brand img{
      height:64px;
      object-fit:contain;
    }

    .brand h2{
      margin:12px 0 8px;
      font-size:30px;
      line-height:1.1;
      color:#0f172a;
    }

    .brand p{
      margin:0;
      color:#4b5563;
      font-size:14px;
    }

    .meta{
      display:grid;
      grid-template-columns:repeat(2,minmax(240px,1fr));
      gap:10px 18px;
      margin-top:18px;
    }

    .meta p{
      margin:0;
      font-size:14px;
      color:#374151;
    }

    .meta b{
      color:#111827;
    }

    .summary{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:12px;
      margin-top:18px;
    }

    .sum-card{
      border-radius:16px;
      padding:16px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
    }

    .sum-card .k{
      font-size:12px;
      color:#6b7280;
      margin-bottom:6px;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.3px;
    }

    .sum-card .v{
      font-size:28px;
      font-weight:900;
      color:#111827;
      line-height:1;
    }

    .sum-card.ok{
      background:#ecfdf5;
      border-color:#bbf7d0;
    }

    .sum-card.ok .v{
      color:#166534;
    }

    .sum-card.bad{
      background:#fef2f2;
      border-color:#fecaca;
    }

    .sum-card.bad .v{
      color:#991b1b;
    }

    .table-wrap{
      padding:20px 26px 8px;
    }

    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      overflow:hidden;
      border:1px solid #e5e7eb;
      border-radius:16px;
      font-size:14px;
    }

    thead th{
      background:#0f172a;
      color:#fff;
      text-align:left;
      padding:14px 16px;
      font-size:13px;
      letter-spacing:.2px;
    }

    tbody td{
      padding:14px 16px;
      border-top:1px solid #e5e7eb;
      background:#fff;
      vertical-align:middle;
    }

    tbody tr:nth-child(even) td{
      background:#f9fafb;
    }

    .num{
      width:90px;
      font-weight:900;
      color:#374151;
    }

    .name{
      font-weight:800;
      color:#111827;
    }

    .muted{
      color:#6b7280;
      font-size:12px;
      margin-top:4px;
    }

    .status-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      font-weight:800;
      font-size:13px;
      border:1px solid transparent;
    }

    .status-badge.ok{
      background:#dcfce7;
      color:#166534;
      border-color:#86efac;
    }

    .status-badge.bad{
      background:#fee2e2;
      color:#991b1b;
      border-color:#fca5a5;
    }

    .footer{
      padding:22px 26px 28px;
    }

    .signature{
      margin-top:28px;
      width:320px;
      border-top:1px solid #9ca3af;
      padding-top:12px;
      color:#374151;
      font-size:14px;
    }

    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:18px;
    }

    .btnS{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid #bbf7d0;
      background:#ecfdf5;
      color:#16a34a;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .btnG{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid #d1d5db;
      background:#f9fafb;
      color:#374151;
      font-weight:800;
      text-decoration:none;
      cursor:pointer;
    }

    .empty{
      padding:24px;
      text-align:center;
      color:#6b7280;
      font-weight:700;
    }

    @media(max-width:860px){
      .summary{
        grid-template-columns:1fr;
      }

      .meta{
        grid-template-columns:1fr;
      }
    }

    @media print{
      body{
        background:#fff;
      }

      .topbar,
      .actions{
        display:none !important;
      }

      .container{
        padding:0;
        max-width:none;
      }

      .sheet{
        box-shadow:none;
        border-radius:0;
      }
    }
  </style>
</head>
<body>
<div class="app">
  <header class="topbar">
    <div class="logo">
      <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
      <span>CONATRADEC • Detalle de asistencia</span>
    </div>

    <div class="nav">
      <a href="attendance_history.php">Historial</a>
      <a href="logout.php">Salir</a>
    </div>
  </header>

  <main class="container">
    <section class="sheet">

      <div class="head">
        <div class="brand-row">
          <div class="brand">
            <img src="/docentes/assets/images/1.png" alt="Logo CONATRADEC">
            <h2>Detalle de asistencia</h2>
            <p>Registro oficial de presencia por grupo y fecha.</p>
          </div>
        </div>

        <div class="meta">
          <p><b>Grupo:</b> <?= h($attendance['group_name']) ?></p>
          <p><b>Código:</b> <?= h($attendance['group_code']) ?></p>
          <p><b>Curso:</b> <?= h($attendance['course_type']) ?></p>
          <p><b>Nivel:</b> <?= h($attendance['course_level']) ?></p>
          <p><b>Docente:</b> <?= h($attendance['teacher_name']) ?></p>
          <p><b>Fecha:</b> <?= h($attendance['attendance_date']) ?></p>
          <p><b>Horario:</b> <?= h($attendance['schedule'] ?: '—') ?></p>
          <p><b>Ubicación:</b> <?= h($attendance['location'] ?: '—') ?></p>
        </div>

        <div class="summary">
          <div class="sum-card">
            <div class="k">Total estudiantes</div>
            <div class="v"><?= (int)$totalStudents ?></div>
          </div>

          <div class="sum-card ok">
            <div class="k">Presentes</div>
            <div class="v"><?= (int)$totalPresent ?></div>
          </div>

          <div class="sum-card bad">
            <div class="k">Ausentes</div>
            <div class="v"><?= (int)$totalAbsent ?></div>
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:90px;">Número</th>
              <th>Nombre</th>
              <th style="width:180px;">Código</th>
              <th style="width:180px;">Departamento</th>
              <th style="width:200px;">Asistencia</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($items): ?>
              <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                  <td class="num"><?= $i++ ?></td>
                  <td>
                    <div class="name"><?= h($item['full_name']) ?></div>
                  </td>
                  <td><?= h($item['student_code'] ?: '—') ?></td>
                  <td><?= h($item['department'] ?: '—') ?></td>
                  <td>
                    <?php if ((int)$item['present'] === 1): ?>
                      <span class="status-badge ok">✔ Presente</span>
                    <?php else: ?>
                      <span class="status-badge bad">✘ Ausente</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="empty">No hay estudiantes registrados en esta asistencia.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="footer">
        <div class="signature">
          Firma del docente
        </div>

        <div class="actions">
          <button class="btnS" onclick="window.print()">Imprimir / Exportar PDF</button>
          <a class="btnG" href="attendance_history.php">← Volver</a>
        </div>
      </div>

    </section>
  </main>
</div>
</body>
</html>