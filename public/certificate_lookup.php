<?php
require __DIR__ . '/../app/config/db.php';

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$error = '';
$student = null;
$certificates = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = trim((string)($_POST['student_code'] ?? ''));

  if ($code === '') {
    $error = 'Ingresá tu código de estudiante.';
  } else {
    $stmt = $pdo->prepare("
      SELECT
        s.id,
        s.full_name,
        s.student_code,
        sc.name AS school_name
      FROM students s
      LEFT JOIN schools sc ON sc.id = s.school_id
      WHERE s.student_code = ?
      LIMIT 1
    ");
    $stmt->execute([$code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
      $error = 'No se encontró un estudiante con ese código.';
    } else {
      $stmt = $pdo->prepare("
        SELECT
          g.id AS group_id,
          g.group_code,
          g.name AS group_name,
          g.course_type,
          g.course_level,
          g.start_date,
          g.end_date,
          sg.final_grade,
          sg.status AS academic_status
        FROM group_students gs
        JOIN groups_table g
          ON g.id = gs.group_id
        JOIN student_grades sg
          ON sg.group_id = gs.group_id
         AND sg.student_id = gs.student_id
        WHERE gs.student_id = ?
          AND sg.status = 'aprobado'
          AND sg.final_grade >= 60
        ORDER BY g.created_at DESC, g.id DESC
      ");
      $stmt->execute([(int)$student['id']]);
      $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (!$certificates) {
        $error = 'Tu certificado aún no está disponible. Consultá con tu docente o administración.';
        $student = null;
      }
    }
  }
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
  <title>Descargar certificado</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    body{
      margin:0;
      min-height:100vh;
      background:
        radial-gradient(circle at top, rgba(47,191,113,.16), transparent 30%),
        linear-gradient(180deg, #08110c, #0b1220);
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px;
    }

    .card{
      width:100%;
      max-width:720px;
      background:linear-gradient(180deg,var(--card2),var(--card));
      border:1px solid var(--line);
      border-radius:24px;
      box-shadow:var(--shadow);
      padding:28px;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:18px;
    }

    .brand img{
      width:52px;
      height:52px;
      object-fit:contain;
    }

    .brand h1{
      margin:0;
      font-size:24px;
      color:#fff;
    }

    .sub{
      margin:4px 0 0;
      color:var(--muted);
      font-size:14px;
    }

    .field{
      margin-top:16px;
    }

    .field label{
      display:block;
      margin-bottom:8px;
      font-weight:700;
      color:#e5e7eb;
    }

    .field input{
      width:100%;
      padding:14px 16px;
      border-radius:14px;
      border:1px solid var(--line);
      background:rgba(255,255,255,.05);
      color:#fff;
      outline:none;
      font-size:15px;
    }

    .field input:focus{
      border-color:rgba(47,191,113,.45);
      box-shadow:0 0 0 3px rgba(47,191,113,.08);
    }

    .btn{
      margin-top:18px;
      width:100%;
      border:none;
      padding:14px 18px;
      border-radius:14px;
      background:linear-gradient(180deg,var(--green),var(--green2));
      color:#06110a;
      font-weight:900;
      cursor:pointer;
      font-size:15px;
    }

    .alert{
      margin-top:16px;
      padding:12px 14px;
      border-radius:14px;
      border:1px solid rgba(255,90,95,.35);
      background:rgba(255,90,95,.10);
      color:#fff;
    }

    .ok{
      margin-top:18px;
      padding:16px;
      border-radius:16px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.10);
    }

    .ok h3{
      margin:0 0 8px;
      color:#d1fae5;
    }

    .ok p{
      margin:6px 0;
      color:#e5e7eb;
    }

    .cert-list{
      margin-top:14px;
      display:flex;
      flex-direction:column;
      gap:12px;
    }

    .cert-item{
      padding:14px;
      border-radius:14px;
      border:1px solid rgba(255,255,255,.10);
      background:rgba(255,255,255,.05);
    }

    .download{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      margin-top:12px;
      padding:12px 16px;
      border-radius:12px;
      border:1px solid rgba(47,191,113,.35);
      background:rgba(47,191,113,.16);
      color:#d1fae5;
      font-weight:800;
      text-decoration:none;
    }

    .hint{
      margin-top:12px;
      color:var(--muted);
      font-size:13px;
      line-height:1.6;
    }
  </style>
</head>
<body>
  <section class="card">
    <div class="brand">
      <img src="/docentes/assets/images/1.png" alt="CONATRADEC">
      <div>
        <h1>Descargar certificado</h1>
        <p class="sub">Ingresá tu código de estudiante para consultar tus certificados disponibles.</p>
      </div>
    </div>

    <form method="post">
      <div class="field">
        <label for="student_code">Código de estudiante</label>
        <input
          id="student_code"
          name="student_code"
          placeholder="Ej: EST-2026-0001"
          value="<?= h($_POST['student_code'] ?? '') ?>"
          required
        >
      </div>

      <button class="btn" type="submit">Buscar certificado</button>
    </form>

    <?php if ($error): ?>
      <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($student && $certificates): ?>
      <div class="ok">
        <h3>Certificados disponibles</h3>
        <p><b>Estudiante:</b> <?= h($student['full_name']) ?></p>
        <p><b>Código:</b> <?= h($student['student_code']) ?></p>
        <p><b>Escuela:</b> <?= h($student['school_name'] ?: '—') ?></p>

        <div class="cert-list">
          <?php foreach ($certificates as $c): ?>
            <div class="cert-item">
              <p><b>Grupo:</b> <?= h($c['group_code']) ?> • <?= h($c['group_name']) ?></p>
              <p><b>Curso:</b> <?= h(courseLabel($c['course_type'])) ?> / <?= h(levelLabel($c['course_level'])) ?></p>
              <p><b>Nota final:</b> <?= h($c['final_grade']) ?></p>
              <p><b>Fechas:</b> <?= h($c['start_date'] ?: '—') ?> a <?= h($c['end_date'] ?: '—') ?></p>

              <a class="download" href="certificate_download.php?student_id=<?= (int)$student['id'] ?>&group_id=<?= (int)$c['group_id'] ?>">
                Descargar certificado
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="hint">
      El certificado estará disponible únicamente para los grupos donde el estudiante haya aprobado.
    </div>
  </section>
</body>
</html>