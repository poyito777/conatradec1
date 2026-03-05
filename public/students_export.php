<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

/**
 * CSV para Excel (ES): separador ; y BOM UTF-8
 * Admin: exporta todos
 * Teacher: exporta solo los suyos
 */

$delim = ';';

// Filtros opcionales (si vos ya los pasás desde students.php, los respetamos)
$q      = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$course = trim((string)($_GET['course_type'] ?? ''));
$level  = trim((string)($_GET['course_level'] ?? ''));
$dept   = trim((string)($_GET['department'] ?? ''));

$where = [];
$params = [];

// Permisos
if (($me['role'] ?? '') === 'teacher') {
  $where[] = "s.teacher_id = ?";
  $params[] = (int)$me['id'];
}

// Búsqueda
if ($q !== '') {
  $where[] = "(s.full_name LIKE ? OR s.student_code LIKE ? OR s.cedula LIKE ? OR s.phone LIKE ?)";
  $like = "%{$q}%";
  array_push($params, $like, $like, $like, $like);
}

if ($status !== '') { $where[] = "s.status = ?";       $params[] = $status; }
if ($course !== '') { $where[] = "s.course_type = ?";  $params[] = $course; }
if ($level  !== '') { $where[] = "s.course_level = ?"; $params[] = $level; }
if ($dept   !== '') { $where[] = "s.department = ?";   $params[] = $dept; }

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    s.id,
    s.full_name,
    s.student_code,
    s.school,
    s.course_type,
    s.course_level,
    s.department,
    s.phone,
    s.cedula,
    s.enrolled_at,
    s.final_grade,
    s.status,
    s.observations,
    s.notes,
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

// Nombre del archivo
$filename = "estudiantes_" . date("Y-m-d_H-i") . ".csv";

// Headers de descarga
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// BOM UTF-8 para que Excel respete tildes/ñ
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Encabezados
fputcsv($out, [
  'ID',
  'Nombre',
  'Codigo',
  'Escuela',
  'Curso',
  'Nivel',
  'Departamento',
  'Telefono',
  'Cedula',
  'Fecha_inscripcion',
  'Nota_final',
  'Estado',
  'Observaciones',
  'Notas',
  'Docente',
  'Docente_email',
  'Creado',
  'Actualizado'
], $delim);

// Filas
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

  // Normalizar valores para CSV
  $row = [
    $r['id'],
    $r['full_name'],
    $r['student_code'],
    $r['school'],
    $r['course_type'],
    $r['course_level'],
    $r['department'],
    $r['phone'],
    $r['cedula'],
    $r['enrolled_at'],
    $r['final_grade'],
    $r['status'],
    $r['observations'],
    $r['notes'],
    $r['teacher_name'],
    $r['teacher_email'],
    $r['created_at'],
    $r['updated_at'],
  ];

  fputcsv($out, $row, $delim);
}

fclose($out);
exit;