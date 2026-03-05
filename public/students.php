<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$courseType = trim($_GET['course_type'] ?? '');
$courseLevel = trim($_GET['course_level'] ?? '');
$department = trim($_GET['department'] ?? '');

$where = [];
$params = [];

// Docente solo ve los suyos
if (($me['role'] ?? '') === 'teacher') {
  $where[] = "s.teacher_id = :me";
  $params[':me'] = (int)$me['id'];
} else {
  // Admin puede filtrar por docente
  if ($teacherId > 0) {
    $where[] = "s.teacher_id = :tid";
    $params[':tid'] = $teacherId;
  }
}

// Filtro por estado
if ($status !== '') {
  $where[] = "s.status = :status";
  $params[':status'] = $status;
}

// Filtro por tipo/ nivel / departamento
if ($courseType !== '') {
  $where[] = "s.course_type = :ctype";
  $params[':ctype'] = $courseType;
}
if ($courseLevel !== '') {
  $where[] = "s.course_level = :clevel";
  $params[':clevel'] = $courseLevel;
}
if ($department !== '') {
  $where[] = "s.department LIKE :dept";
  $params[':dept'] = "%{$department}%";
}

// Búsqueda general
if ($q !== '') {
  $where[] = "(s.full_name LIKE :q OR s.student_code LIKE :q OR s.school LIKE :q OR s.cedula LIKE :q OR s.phone LIKE :q OR s.department LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Dropdown docentes (solo admin)
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("SELECT id, name, email FROM users WHERE role='teacher' ORDER BY name ASC")->fetchAll();
}

$sql = "
SELECT s.*, u.name AS teacher_name
FROM students s
JOIN users u ON u.id = s.teacher_id
{$whereSql}
ORDER BY s.id DESC
LIMIT 800
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function pillClass($status){
  if ($status === 'aprobado') return 'aprobado';
  if ($status === 'desaprobado') return 'desaprobado';
  return 'pendiente';
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estudiantes</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{display:flex;justify-content:space-between;align-items:center;padding:14px 24px;background:rgba(0,0,0,.35);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
    .logo{display:flex;align-items:center;gap:10px;font-weight:700}
    .logo img{width:34px;height:34px;object-fit:contain}
    .nav{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .nav a{padding:8px 12px;border:1px solid var(--line);border-radius:12px;background:rgba(255,255,255,.06)}
    .container{padding:26px;max-width:1250px;width:100%;margin:0 auto}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}
    .toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:12px}
    .filters .field{margin-top:0;min-width:180px}
    .btnS{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);font-weight:800}
    .btnD{display:inline-block;padding:10px 14px;border-radius:14px;border:1px solid rgba(255,90,95,.35);background:rgba(255,90,95,.10);color:rgba(255,255,255,.92);font-weight:800}
    .btnG{display:inline-block;padding:8px 12px;border-radius:12px;border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.12);color:var(--green);font-weight:700}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th,td{padding:12px;border-bottom:1px solid var(--line);text-align:left;font-size:13px;vertical-align:top}
    th{color:var(--muted);font-weight:700}
    tr:hover{background:rgba(255,255,255,.04)}
    .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px}
    .pill.aprobado{border-color:rgba(47,191,113,.45);color:var(--green)}
    .pill.desaprobado{border-color:rgba(255,90,95,.45);color:rgba(255,255,255,.92)}
    .pill.pendiente{border-color:rgba(245,158,11,.45);color:rgba(255,255,255,.92)}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .muted{color:var(--muted)}
    .small{font-size:12px;color:var(--muted)}
    .nowrap{white-space:nowrap}
  </style>
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div class="logo">
        <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
        <span>CEDOCAFÉ</span>
      </div>
      <div class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="students.php">Estudiantes</a>
        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <a href="teachers.php">Docentes</a>
        <?php endif; ?>
        <a href="logout.php">Salir</a>
      </div>
    </header>

    <main class="container">
      <section class="panel">
        <div class="toolbar">
          <div>
            <h2 style="margin:0 0 6px;">Estudiantes</h2>
            <p style="margin:0;color:var(--muted);">
              <?= ($me['role'] === 'admin') ? 'Vista global (admin)' : 'Tus estudiantes (docente)' ?>
            </p>
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btnS" href="student_form.php">+ Agregar estudiante</a>
            <a class="btnS" href="students_export.php?<?= http_build_query($_GET) ?>">Descargar CSV</a>
          </div>
        </div>

        <form method="get" class="filters">
          <div class="field">
            <label>Buscar</label>
            <input name="q" value="<?= h($q) ?>" placeholder="Nombre, cédula, teléfono, escuela...">
          </div>

          <div class="field" style="min-width:170px">
            <label>Estado</label>
            <select name="status">
              <option value="">Todos</option>
              <option value="pendiente" <?= $status==='pendiente'?'selected':'' ?>>Pendiente</option>
              <option value="aprobado" <?= $status==='aprobado'?'selected':'' ?>>Aprobado</option>
              <option value="desaprobado" <?= $status==='desaprobado'?'selected':'' ?>>Desaprobado</option>
            </select>
          </div>

          <div class="field" style="min-width:170px">
            <label>Curso</label>
            <select name="course_type">
              <option value="">Todos</option>
              <option value="barismo" <?= $courseType==='barismo'?'selected':'' ?>>Barismo</option>
              <option value="catacion" <?= $courseType==='catacion'?'selected':'' ?>>Catación</option>
            </select>
          </div>

          <div class="field" style="min-width:170px">
            <label>Nivel</label>
            <select name="course_level">
              <option value="">Todos</option>
              <option value="basico" <?= $courseLevel==='basico'?'selected':'' ?>>Básico</option>
              <option value="avanzado" <?= $courseLevel==='avanzado'?'selected':'' ?>>Avanzado</option>
              <option value="intensivo" <?= $courseLevel==='intensivo'?'selected':'' ?>>Intensivo</option>
            </select>
          </div>

          <div class="field" style="min-width:220px">
            <label>Departamento</label>
            <input name="department" value="<?= h($department) ?>" placeholder="Ej: Matagalpa">
          </div>

          <?php if (($me['role'] ?? '') === 'admin'): ?>
            <div class="field" style="min-width:260px">
              <label>Docente</label>
              <select name="teacher_id">
                <option value="0">Todos los docentes</option>
                <?php foreach($teachers as $t): ?>
                  <option value="<?= (int)$t['id'] ?>" <?= $teacherId===(int)$t['id']?'selected':'' ?>>
                    <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="field" style="min-width:140px">
            <button class="btn" type="submit">Filtrar</button>
          </div>
          <div class="field" style="min-width:140px">
            <a class="btnD" href="students.php">Limpiar</a>
          </div>
        </form>

        <div class="small" style="margin-top:10px;">
          Resultados: <b><?= count($rows) ?></b>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th class="nowrap">Curso / Nivel</th>
              <th>Depto</th>
              <th class="nowrap">Inscripción</th>
              <th class="nowrap">Teléfono</th>
              <th class="nowrap">Cédula</th>
              <th class="nowrap">Nota</th>
              <th>Estado</th>
              <?php if ($me['role']==='admin'): ?><th>Docente</th><?php endif; ?>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>

                <td>
                  <div style="font-weight:800;"><?= h($r['full_name']) ?></div>
                  <div class="small">
                    <?= h($r['school']) ?><?= $r['student_code'] ? " • ".h($r['student_code']) : "" ?>
                  </div>
                  <?php if (!empty($r['observations'])): ?>
                    <div class="small">Obs: <?= h($r['observations']) ?></div>
                  <?php endif; ?>
                </td>

                <td>
                  <div style="font-weight:700;"><?= h(courseLabel($r['course_type'])) ?></div>
                  <div class="small"><?= h(levelLabel($r['course_level'])) ?></div>
                </td>

                <td class="muted"><?= h($r['department']) ?></td>

                <td class="muted nowrap"><?= h($r['enrolled_at']) ?></td>

                <td class="muted nowrap"><?= h($r['phone']) ?></td>

                <td class="muted nowrap"><?= h($r['cedula']) ?></td>

                <td class="muted nowrap">
                  <?= ($r['final_grade'] === null ? '-' : h($r['final_grade'])) ?>
                </td>

                <td>
                  <span class="pill <?= h(pillClass($r['status'])) ?>"><?= h($r['status']) ?></span>
                </td>

                <?php if ($me['role']==='admin'): ?>
                  <td class="muted"><?= h($r['teacher_name']) ?></td>
                <?php endif; ?>

                <td>
                  <div class="actions">
                    <a class="btnG" href="student_profile.php?id=<?= (int)$r['id'] ?>">Ver</a>
                    <a class="btnG" href="student_form.php?id=<?= (int)$r['id'] ?>">Editar</a>
                    <a class="btnG" href="student_letter.php?id=<?= (int)$r['id'] ?>" target="_blank">Constancia</a> 
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if(!$rows): ?>
              <tr>
                <td colspan="<?= $me['role']==='admin' ? 11 : 10 ?>" class="muted">
                  No hay estudiantes para mostrar.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>