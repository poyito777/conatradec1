<?php
require __DIR__ . '/../app/config/db.php';

function h($v){
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$code = trim((string)($_GET['code'] ?? ''));

if ($code === '') {
  http_response_code(400);
  exit('Falta el código del estudiante.');
}

$stmt = $pdo->prepare("
  SELECT
    s.*,
    u.name AS teacher_name
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  WHERE s.student_code = ?
  LIMIT 1
");
$stmt->execute([$code]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
  http_response_code(404);
  exit('No se encontró un estudiante con ese código.');
}

if ($student['final_grade'] === null || (float)$student['final_grade'] < 60) {
  http_response_code(403);
  exit('Este certificado aún no está disponible.');
}

function courseLabel($t){
  return $t === 'catacion' ? 'Catación' : 'Barismo';
}

function levelLabel($l){
  if ($l === 'avanzado') return 'Avanzado';
  if ($l === 'intensivo') return 'Intensivo';
  return 'Básico';
}

$today = date('d/m/Y');
$certificateNumber = 'CERT-' . date('Y') . '-' . str_pad((string)$student['id'], 5, '0', STR_PAD_LEFT);

$logoGob = '/docentes/assets/images/grun.png';
$logoSnpcc = '/docentes/assets/images/SNPCC.png';
$logoCona = '/docentes/assets/images/conatradec.png';

/* firma digital opcional */
$signatureImage = '/docentes/assets/images/firma.png';

$schoolName = trim((string)($student['school'] ?? ''));
if ($schoolName === '') {
  $schoolName = 'Escuela de Catación y Barismo CONATRADEC';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Certificado - <?= h($student['full_name']) ?></title>
  <style>
    :root{
      --paper:#fffdf8;
      --ink:#1f2937;
      --muted:#6b7280;
      --gold:#b8891f;
      --gold-soft:#f6ecd2;
      --line:#e7d9b0;
    }

    *{
      box-sizing:border-box;
    }

    body{
      margin:0;
      padding:24px;
      background:#0b1220;
      font-family:"Georgia","Times New Roman",serif;
    }

    .actions{
      max-width:1100px;
      margin:0 auto 14px;
      display:flex;
      justify-content:flex-end;
      gap:10px;
      flex-wrap:wrap;
    }

    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:12px;
      border:1px solid rgba(255,255,255,.15);
      background:rgba(255,255,255,.06);
      color:#fff;
      font-weight:700;
      text-decoration:none;
      cursor:pointer;
      font-family:Arial,sans-serif;
    }

    .btn.print{
      border-color:rgba(47,191,113,.45);
      background:rgba(47,191,113,.14);
      color:#d1fae5;
    }

    .page{
      max-width:1100px;
      min-height:760px;
      margin:0 auto;
      background:var(--paper);
      color:var(--ink);
      border:10px solid var(--gold-soft);
      box-shadow:0 18px 60px rgba(0,0,0,.35);
      position:relative;
      overflow:hidden;
    }

    .frame{
      border:2px solid var(--gold);
      margin:18px;
      min-height:calc(760px - 36px);
      padding:34px 48px;
      position:relative;
    }

    .top{
      display:grid;
      grid-template-columns:1fr auto 1fr;
      align-items:center;
      gap:18px;
      padding-bottom:8px;
      border-bottom:1px solid var(--line);
    }

    .top-left,
    .top-center,
    .top-right{
      display:flex;
      align-items:center;
    }

    .top-left{ justify-content:flex-start; }
    .top-center{ justify-content:center; }
    .top-right{ justify-content:flex-end; }

    .logo-gob{
      max-width:220px;
      max-height:74px;
      object-fit:contain;
    }

    .logo-snpcc{
      max-width:180px;
      max-height:70px;
      object-fit:contain;
    }

    .logo-cona{
      max-width:220px;
      max-height:74px;
      object-fit:contain;
    }

    .cert-number{
      text-align:right;
      font-family:Arial,sans-serif;
      font-size:13px;
      color:var(--muted);
      margin-top:12px;
    }

    .title{
      text-align:center;
      margin-top:30px;
    }

    .title h2{
      margin:0;
      font-size:46px;
      color:#7a5a12;
      letter-spacing:1px;
    }

    .title h4{
      margin:10px 0 0;
      font-size:18px;
      color:var(--muted);
      font-family:Arial,sans-serif;
      font-weight:700;
    }

    .body{
      margin-top:34px;
      text-align:center;
      padding:0 24px;
    }

    .body p{
      margin:0 0 18px;
      font-size:22px;
      line-height:1.7;
    }

    .student-name{
      margin:24px 0 16px;
      font-size:42px;
      font-weight:700;
      color:#3f2f08;
      border-top:1px solid var(--line);
      border-bottom:1px solid var(--line);
      padding:18px 10px;
    }

    .footer{
      margin-top:42px;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap:26px;
      text-align:center;
    }

    .date-box{
      font-family:Arial,sans-serif;
      color:var(--muted);
      font-size:14px;
    }

    .signature{
      width:320px;
      text-align:center;
      font-family:Arial,sans-serif;
      margin:0 auto;
    }

    .signature-image-wrap{
      height:80px;
      display:flex;
      align-items:flex-end;
      justify-content:center;
      margin-bottom:6px;
    }

    .signature-image{
      max-width:200px;
      max-height:80px;
      object-fit:contain;
      display:block;
    }

    .signature .line{
      border-top:1px solid #8b7350;
      margin-bottom:8px;
      padding-top:8px;
      color:#4b5563;
      font-size:13px;
    }

    .seal{
      position:absolute;
      right:38px;
      bottom:28px;
      width:120px;
      height:120px;
      border:3px solid rgba(184,137,31,.45);
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      text-align:center;
      font-size:13px;
      color:rgba(122,90,18,.85);
      font-weight:700;
      transform:rotate(-10deg);
      opacity:.9;
      background:rgba(246,236,210,.45);
      font-family:Arial,sans-serif;
    }

    @media print{
      body{
        background:#fff;
        padding:0;
      }

      .actions{
        display:none !important;
      }

      .page{
        box-shadow:none;
        max-width:none;
        width:100%;
        min-height:auto;
        border:8px solid var(--gold-soft);
      }

      .frame{
        min-height:auto;
      }
    }
  </style>
</head>
<body>

<div class="actions">
  <a class="btn" href="certificate_lookup.php">← Volver</a>
  <button class="btn print" onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<section class="page">
  <div class="frame">

    <div class="top">
      <div class="top-left">
        <img class="logo-gob" src="<?= h($logoGob) ?>" alt="Gobierno">
      </div>

      <div class="top-center">
        <img class="logo-snpcc" src="<?= h($logoSnpcc) ?>" alt="SNPCC">
      </div>

      <div class="top-right">
        <img class="logo-cona" src="<?= h($logoCona) ?>" alt="CONATRADEC">
      </div>
    </div>

    <div class="cert-number">
      <div><b>N.º de certificado:</b> <?= h($certificateNumber) ?></div>
      <div><b>Código estudiante:</b> <?= h($student['student_code']) ?></div>
    </div>

    <div class="title">
      <h2>CONATRADEC</h2>
      <h4>La Comisión Nacional para la Transformación y Desarrollo de la Caficultura</h4>
    </div>

    <div class="body">
      <p>OTORGA EL PRESENTE CERTIFICADO A:</p>

      <div class="student-name">
        <?= h($student['full_name']) ?>
      </div>

      <p>
        Por haber aprobado el Curso de
        <b><?= h(courseLabel($student['course_type'])) ?></b>,
        del nivel <b><?= h(levelLabel($student['course_level'])) ?></b>,
        logrando satisfactoriamente cumplir con el programa de formación, impartidos por los instructores de la
        <b><?= h($schoolName) ?></b>.
      </p>
    </div>

      <div class="signature">
        <div class="signature-image-wrap">
          <img class="signature-image" src="<?= h($signatureImage) ?>" alt="Firma digital">
        </div>
        <div class="line"></div>
        <div><b>Eduardo Escobar García</b></div>
        <div>Secretario Ejecutivo</div>
        <div><b>CONATRADEC</b></div>
      </div>
    </div>

  </div>
</section>

</body>
</html>
