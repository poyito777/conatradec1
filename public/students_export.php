<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

$delim = ';';

$q      = trim((string)($_GET['q'] ?? ''));
$course = trim((string)($_GET['course_type'] ?? ''));
$level  = trim((string)($_GET['course_level'] ?? ''));
$dept   = trim((string)($_GET['department'] ?? ''));
$teacherId = (int)($_GET['teacher_id'] ?? 0);

$where = [];
$params = [];

if (($me['role'] ?? '') === 'teacher') {
  $where[] = "s.teacher_id = ?";
  $params[] = (int)$me['id'];
} else {
  if ($teacherId > 0) {
    $where[] = "s.teacher_id = ?";
    $params[] = $teacherId;
  }
}

if ($q !== '') {
  $where[] = "(s.full_name LIKE ? OR s.student_code LIKE ? OR s.cedula LIKE ? OR s.phone LIKE ? OR s.school LIKE ? OR s.organization_name LIKE ? OR s.characterization LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

if ($course !== '') {
  $where[] = "s.course_type = ?";
  $params[] = $course;
}

if ($level !== '') {
  $where[] = "s.course_level = ?";
  $params[] = $level;
}

if ($dept !== '') {
  $where[] = "s.department LIKE ?";
  $params[] = "%{$dept}%";
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    s.id,
    s.student_code,
    s.full_name,
    s.sex,
    s.education_level,
    s.profession,
    s.nationality,
    s.school,
    s.course_type,
    s.course_level,
    s.phone,
    s.cedula,
    s.department,
    s.municipality,
    s.community,
    s.organization_type,
    s.organization_name,
    s.organization_phone,
    s.organization_location,
    s.characterization,
    s.trademark_registration,
    s.course_purpose,
    s.number_of_members,
    s.future_projection,
    s.enrolled_at,
    s.observations,
    s.final_grade,
    s.status,
    u.name  AS teacher_name,
    u.email AS teacher_email,
    s.created_at,
    s.updated_at
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  $sqlWhere
  ORDER BY s.created_at DESC, s.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = "estudiantes_" . date("Y-m-d_H-i") . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, [
  'ID',
  'Codigo',
  'Nombre',
  'Sexo',
  'Nivel_escolar',
  'Profesion',
  'Nacionalidad',
  'Escuela',
  'Curso',
  'Nivel',
  'Telefono',
  'Cedula',
  'Departamento',
  'Municipio',
  'Comunidad',
  'Tipo_organizacion',
  'Nombre_organizacion',
  'Telefono_organizacion',
  'Ubicacion_organizacion',
  'Caracterizacion',
  'Registro_marca',
  'Proposito_curso',
  'Numero_socios',
  'Proyeccion_futuro',
  'Fecha_inscripcion',
  'Observaciones',
  'Nota_final',
  'Estado',
  'Docente',
  'Docente_email',
  'Creado',
  'Actualizado'
], $delim);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $row = [
    $r['id'],
    $r['student_code'],
    $r['full_name'],
    $r['sex'],
    $r['education_level'],
    $r['profession'],
    $r['nationality'],
    $r['school'],
    $r['course_type'],
    $r['course_level'],
    $r['phone'],
    $r['cedula'],
    $r['department'],
    $r['municipality'],
    $r['community'],
    $r['organization_type'],
    $r['organization_name'],
    $r['organization_phone'],
    $r['organization_location'],
    $r['characterization'],
    $r['trademark_registration'],
    $r['course_purpose'],
    $r['number_of_members'],
    $r['future_projection'],
    $r['enrolled_at'],
    $r['observations'],
    $r['final_grade'],
    $r['status'],
    $r['teacher_name'],
    $r['teacher_email'],
    $r['created_at'],
    $r['updated_at'],
  ];

  fputcsv($out, $row, $delim);
}

fclose($out);
exit;