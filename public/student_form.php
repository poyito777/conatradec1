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
$error = '';

// Escuelas fijas
$schools = [
  'Escuela Café Boutique Managua',
  'Cra. Natividad Martínez Sanchez',
  'Cra. Eudosia Abdulia Gomez Chavarria "La Docha"',
  'Cro. Gabriel Martínez Herrera San Juan de Río Coco-Madriz'
];

// Departamentos y municipios
$municipios = [
  'Boaco' => ['Boaco','Camoapa','San José de los Remates','San Lorenzo','Santa Lucía','Teustepe'],
  'Carazo' => ['Diriamba','Dolores','El Rosario','Jinotepe','La Conquista','La Paz de Carazo','San Marcos','Santa Teresa'],
  'Chinandega' => ['Chichigalpa','Chinandega','Cinco Pinos','Corinto','El Realejo','El Viejo','Posoltega','Puerto Morazán','San Francisco del Norte','San Pedro del Norte','Santo Tomás del Norte','Somotillo','Villanueva'],
  'Chontales' => ['Acoyapa','Comalapa','Cuapa','El Coral','Juigalpa','La Libertad','San Francisco de Cuapa','San Pedro de Lóvago','Santo Domingo','Santo Tomás','Villa Sandino'],
  'Estelí' => ['Condega','Estelí','La Trinidad','Pueblo Nuevo','San Juan de Limay','San Nicolás'],
  'Granada' => ['Diriá','Diriomo','Granada','Nandaime'],
  'Jinotega' => ['El Cuá','Jinotega','La Concordia','San José de Bocay','San Rafael del Norte','San Sebastián de Yalí','Santa María de Pantasma','Wiwilí de Jinotega'],
  'León' => ['Achuapa','El Jicaral','La Paz Centro','Larreynaga','León','Nagarote','Quezalguaque','Santa Rosa del Peñón','Telica'],
  'Madriz' => ['Las Sabanas','Palacagüina','San José de Cusmapa','San Juan de Río Coco','San Lucas','Somoto','Telpaneca','Totogalpa','Yalagüina'],
  'Managua' => ['Ciudad Sandino','El Crucero','Managua','San Francisco Libre','San Rafael del Sur','Tipitapa','Ticuantepe','Villa Carlos Fonseca'],
  'Masaya' => ['Catarina','La Concepción','Masatepe','Masaya','Nandasmo','Nindirí','Niquinohomo','San Juan de Oriente','Tisma'],
  'Matagalpa' => ['Ciudad Darío','El Tuma - La Dalia','Esquipulas','Matagalpa','Matiguás','Muy Muy','Rancho Grande','Río Blanco','San Dionisio','San Isidro','San Ramón','Sébaco','Terrabona'],
  'Nueva Segovia' => ['Ciudad Antigua','Dipilto','El Jícaro','Jalapa','Macuelizo','Mozonte','Murra','Ocotal','Quilalí','San Fernando','Santa María','Wiwilí de Nueva Segovia'],
  'Río San Juan' => ['El Almendro','El Castillo','Morrito','San Carlos','San Juan de Nicaragua'],
  'Rivas' => ['Altagracia','Belén','Buenos Aires','Cárdenas','Moyogalpa','Potosí','Rivas','San Jorge','San Juan del Sur','Tola'],
  'RAAN' => ['Bonanza','Mulukukú','Prinzapolka','Puerto Cabezas','Rosita','Siuna','Waslala','Waspam'],
  'RAAS' => ['Bluefields','Corn Island','Desembocadura de Río Grande','El Ayote','El Rama','Kukra Hill','La Cruz de Río Grande','Laguna de Perlas','Muelle de los Bueyes','Nueva Guinea','Paiwas']
];

// Defaults
$row = [
  'id' => 0,
  'teacher_id' => $me['id'],
  'full_name' => '',
  'sex' => '',
  'education_level' => '',
  'profession' => '',
  'nationality' => '',
  'student_code' => '',
  'school' => '',
  'course_type' => 'barismo',
  'course_level' => 'basico',
  'phone' => '',
  'cedula' => '',
  'department' => '',
  'municipality' => '',
  'community' => '',
  'organization_type' => '',
  'organization_name' => '',
  'organization_phone' => '',
  'organization_location' => '',
  'organization' => '',
  'characterization' => '',
  'trademark_registration' => '',
  'course_purpose' => '',
  'number_of_members' => '',
  'future_projection' => '',
  'enrolled_at' => '',
  'observations' => ''
];

// Editar
if ($id > 0) {
  $st = $pdo->prepare("SELECT * FROM students WHERE id=? LIMIT 1");
  $st->execute([$id]);
  $found = $st->fetch(PDO::FETCH_ASSOC);

  if (!$found) {
    http_response_code(404);
    exit("No existe");
  }

  if (($me['role'] ?? '') === 'teacher' && (int)$found['teacher_id'] !== (int)$me['id']) {
    http_response_code(403);
    exit("Acceso denegado");
  }

  $row = array_merge($row, $found);
}

// Docentes para admin
$teachers = [];
if (($me['role'] ?? '') === 'admin') {
  $teachers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role='teacher' AND is_active=1
    ORDER BY name ASC
  ")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $sex = trim($_POST['sex'] ?? '');
  $education_level = trim($_POST['education_level'] ?? '');
  $profession = trim($_POST['profession'] ?? '');
  $nationality = trim($_POST['nationality'] ?? '');

  $school = trim($_POST['school'] ?? '');
  $course_type = trim($_POST['course_type'] ?? 'barismo');
  $course_level = trim($_POST['course_level'] ?? 'basico');

  $phone = trim($_POST['phone'] ?? '');
  $cedula = strtoupper(trim($_POST['cedula'] ?? ''));
  $department = trim($_POST['department'] ?? '');
  $municipality = trim($_POST['municipality'] ?? '');
  $community = trim($_POST['community'] ?? '');

  $organization_type = trim($_POST['organization_type'] ?? '');
  $organization_name = trim($_POST['organization_name'] ?? '');
  $organization_phone = trim($_POST['organization_phone'] ?? '');
  $organization_location = trim($_POST['organization_location'] ?? '');
  $characterization = trim($_POST['characterization'] ?? '');
  $trademark_registration = trim($_POST['trademark_registration'] ?? '');
  $course_purpose = trim($_POST['course_purpose'] ?? '');
  $number_of_members_raw = trim($_POST['number_of_members'] ?? '');
  $future_projection = trim($_POST['future_projection'] ?? '');

  $enrolled_at = trim($_POST['enrolled_at'] ?? '');
  $observations = trim($_POST['observations'] ?? '');

  $number_of_members = ($number_of_members_raw === '') ? null : (int)$number_of_members_raw;

  $teacher_id = (int)$row['teacher_id'];
  if (($me['role'] ?? '') === 'admin') {
    $teacher_id = (int)($_POST['teacher_id'] ?? $teacher_id);
  } else {
    $teacher_id = (int)$me['id'];
  }

  $phoneDigits = preg_replace('/\D+/', '', $phone);
  $orgPhoneDigits = preg_replace('/\D+/', '', $organization_phone);

  // Validaciones
  if ($full_name === '') {
    $error = "El nombre es obligatorio.";
  } elseif (!in_array($school, $schools, true)) {
    $error = "Seleccioná una escuela válida.";
  } elseif ($sex !== '' && !in_array($sex, ['masculino','femenino'], true)) {
    $error = "Sexo inválido.";
  } elseif ($education_level !== '' && !in_array($education_level, ['secundaria','tecnico','universitario'], true)) {
    $error = "Nivel escolar inválido.";
  } elseif (!in_array($course_type, ['barismo','catacion'], true)) {
    $error = "Tipo de curso inválido.";
  } elseif (!in_array($course_level, ['basico','avanzado','intensivo'], true)) {
    $error = "Nivel inválido.";
  } elseif ($phone !== '' && strlen($phoneDigits) !== 8) {
    $error = "El teléfono debe tener exactamente 8 dígitos.";
  } elseif ($cedula !== '' && !preg_match('/^\d{13}[A-Z]$/', $cedula)) {
    $error = "La cédula debe tener exactamente 13 números y 1 letra.";
  } elseif ($organization_phone !== '' && strlen($orgPhoneDigits) !== 8) {
    $error = "El teléfono de la organización debe tener exactamente 8 dígitos.";
  } elseif ($organization_type !== '' && !in_array($organization_type, ['institucion','privado','emprendimiento','estudiante','productor'], true)) {
    $error = "Tipo de organización inválido.";
  } elseif ($trademark_registration !== '' && !in_array($trademark_registration, ['si','no'], true)) {
    $error = "Registro de marca inválido.";
  } elseif ($number_of_members !== null && $number_of_members < 0) {
    $error = "El número de socios no puede ser negativo.";
  } else {

    if ($id > 0) {
      $up = $pdo->prepare("
        UPDATE students SET
          teacher_id=?,
          full_name=?,
          sex=?,
          education_level=?,
          profession=?,
          nationality=?,
          school=?,
          course_type=?,
          course_level=?,
          phone=?,
          cedula=?,
          department=?,
          municipality=?,
          community=?,
          organization_type=?,
          organization_name=?,
          organization_phone=?,
          organization_location=?,
          characterization=?,
          trademark_registration=?,
          course_purpose=?,
          number_of_members=?,
          future_projection=?,
          enrolled_at=?,
          observations=?
        WHERE id=?
      ");

      $up->execute([
        $teacher_id,
        $full_name,
        ($sex === '' ? null : $sex),
        ($education_level === '' ? null : $education_level),
        $profession,
        ($nationality === '' ? null : $nationality),
        $school,
        $course_type,
        $course_level,
        ($phone === '' ? null : $phoneDigits),
        ($cedula === '' ? null : $cedula),
        $department,
        $municipality,
        $community,
        ($organization_type === '' ? null : $organization_type),
        $organization_name,
        ($organization_phone === '' ? null : $orgPhoneDigits),
        $organization_location,
        $characterization,
        ($trademark_registration === '' ? null : $trademark_registration),
        $course_purpose,
        $number_of_members,
        $future_projection,
        ($enrolled_at === '' ? null : $enrolled_at),
        $observations,
        $id
      ]);

      header("Location: students.php");
      exit;

    } else {
      $ins = $pdo->prepare("
        INSERT INTO students
        (
          teacher_id, full_name, sex, education_level, profession, nationality,
          school, course_type, course_level,
          phone, cedula, department, municipality, community,
          organization_type, organization_name, organization_phone, organization_location,
          characterization, trademark_registration, course_purpose, number_of_members, future_projection,
          enrolled_at, observations
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");

      $ins->execute([
        $teacher_id,
        $full_name,
        ($sex === '' ? null : $sex),
        ($education_level === '' ? null : $education_level),
        $profession,
        ($nationality === '' ? null : $nationality),
        $school,
        $course_type,
        $course_level,
        ($phone === '' ? null : $phoneDigits),
        ($cedula === '' ? null : $cedula),
        $department,
        $municipality,
        $community,
        ($organization_type === '' ? null : $organization_type),
        $organization_name,
        ($organization_phone === '' ? null : $orgPhoneDigits),
        $organization_location,
        $characterization,
        ($trademark_registration === '' ? null : $trademark_registration),
        $course_purpose,
        $number_of_members,
        $future_projection,
        ($enrolled_at === '' ? null : $enrolled_at),
        $observations
      ]);

      $newId = (int)$pdo->lastInsertId();
      $studentCode = 'EST-' . date('Y') . '-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);

      $upCode = $pdo->prepare("UPDATE students SET student_code=? WHERE id=?");
      $upCode->execute([$studentCode, $newId]);

      header("Location: students.php");
      exit;
    }
  }

  // Rehidratar
  $row['teacher_id'] = $teacher_id;
  $row['full_name'] = $full_name;
  $row['sex'] = $sex;
  $row['education_level'] = $education_level;
  $row['profession'] = $profession;
  $row['nationality'] = $nationality;
  $row['school'] = $school;
  $row['course_type'] = $course_type;
  $row['course_level'] = $course_level;
  $row['phone'] = $phone;
  $row['cedula'] = $cedula;
  $row['department'] = $department;
  $row['municipality'] = $municipality;
  $row['community'] = $community;
  $row['organization_type'] = $organization_type;
  $row['organization_name'] = $organization_name;
  $row['organization_phone'] = $organization_phone;
  $row['organization_location'] = $organization_location;
  $row['characterization'] = $characterization;
  $row['trademark_registration'] = $trademark_registration;
  $row['course_purpose'] = $course_purpose;
  $row['number_of_members'] = $number_of_members_raw;
  $row['future_projection'] = $future_projection;
  $row['enrolled_at'] = $enrolled_at;
  $row['observations'] = $observations;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</title>
  <link rel="stylesheet" href="/docentes/assets/css/app.css">
  <style>
    .container{padding:26px;max-width:1080px;width:100%;margin:0 auto;}
    .panel{background:linear-gradient(180deg,var(--card2),var(--card));border:1px solid var(--line);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px;}
    .col4{grid-column:span 4}
    .col6{grid-column:span 6}
    .col8{grid-column:span 8}
    .col12{grid-column:span 12}
    @media(max-width:860px){.col4,.col6,.col8{grid-column:span 12}}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;}
    .btnS,.btnG{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:14px;font-weight:800;text-decoration:none;cursor:pointer;}
    .btnS{border:1px solid rgba(47,191,113,.35);background:rgba(47,191,113,.10);color:var(--green);}
    .btnG{border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.05);color:#cbd5e1;}
    textarea{resize:vertical}
    .hint{font-size:12px;color:var(--muted);margin-top:6px}
    .hero{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:12px;}
    .info-box{margin-top:12px;padding:12px 14px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid var(--line);color:var(--muted);line-height:1.6;font-size:14px;}
    .readonly-box{padding:12px 14px;border-radius:12px;border:1px solid var(--line);background:rgba(255,255,255,.04);color:#e5e7eb;font-weight:700;min-height:46px;display:flex;align-items:center;}
  </style>
</head>
<body>
<?php require __DIR__ . '/partials/sidebar.php'; ?>

<main class="container">
  <section class="panel">
    <div class="hero">
      <div>
        <h2 style="margin:0 0 6px;"><?= $id > 0 ? 'Editar' : 'Agregar' ?> estudiante</h2>
        <p style="margin:0;color:var(--muted);">El código del estudiante se genera automáticamente al crear el registro.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert" style="margin-top:12px;"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px;">
      <div class="grid">

        <?php if (($me['role'] ?? '') === 'admin'): ?>
          <div class="field col12">
            <label>Docente</label>
            <select name="teacher_id" required>
              <?php foreach($teachers as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$row['teacher_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                  <?= h($t['name']) ?> (<?= h($t['email']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="field col8">
          <label>Nombre completo *</label>
          <input name="full_name" value="<?= h($row['full_name']) ?>" required>
        </div>

        <div class="field col4">
          <label>Código del estudiante</label>
          <div class="readonly-box">
            <?= $id > 0 ? h($row['student_code'] ?: 'Sin código') : 'Se generará automáticamente al guardar' ?>
          </div>
        </div>

        <div class="field col4">
          <label>Sexo</label>
          <select name="sex">
            <option value="">Seleccionar</option>
            <option value="masculino" <?= ($row['sex'] ?? '') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
            <option value="femenino" <?= ($row['sex'] ?? '') === 'femenino' ? 'selected' : '' ?>>Femenino</option>
          </select>
        </div>

        <div class="field col4">
          <label>Nivel escolar</label>
          <select name="education_level">
            <option value="">Seleccionar</option>
            <option value="secundaria" <?= ($row['education_level'] ?? '') === 'secundaria' ? 'selected' : '' ?>>Secundaria</option>
            <option value="tecnico" <?= ($row['education_level'] ?? '') === 'tecnico' ? 'selected' : '' ?>>Técnico</option>
            <option value="universitario" <?= ($row['education_level'] ?? '') === 'universitario' ? 'selected' : '' ?>>Universitario</option>
          </select>
        </div>

        <div class="field col4">
          <label>Nacionalidad</label>
          <input
            type="text"
            name="nationality"
            value="<?= h($row['nationality'] ?? '') ?>"
            placeholder="Ej: Nicaragüense"
          >
        </div>

        <div class="field col6">
          <label>Profesión</label>
          <input name="profession" value="<?= h($row['profession'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Escuela</label>
          <select name="school" required>
            <option value="">Seleccionar escuela</option>
            <?php foreach ($schools as $school): ?>
              <option value="<?= h($school) ?>" <?= ($row['school'] ?? '') === $school ? 'selected' : '' ?>>
                <?= h($school) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col4">
          <label>Tipo de curso</label>
          <select name="course_type">
            <option value="barismo" <?= ($row['course_type'] ?? '') === 'barismo' ? 'selected' : '' ?>>Barismo</option>
            <option value="catacion" <?= ($row['course_type'] ?? '') === 'catacion' ? 'selected' : '' ?>>Catación</option>
          </select>
        </div>

        <div class="field col4">
          <label>Nivel</label>
          <select name="course_level">
            <option value="basico" <?= ($row['course_level'] ?? '') === 'basico' ? 'selected' : '' ?>>Básico</option>
            <option value="avanzado" <?= ($row['course_level'] ?? '') === 'avanzado' ? 'selected' : '' ?>>Avanzado</option>
            <option value="intensivo" <?= ($row['course_level'] ?? '') === 'intensivo' ? 'selected' : '' ?>>Intensivo</option>
          </select>
        </div>

        <div class="field col4">
          <label>Fecha de inscripción</label>
          <input type="date" name="enrolled_at" value="<?= h($row['enrolled_at'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label>Teléfono</label>
          <input name="phone" value="<?= h($row['phone'] ?? '') ?>" placeholder="Ej: 88888888" maxlength="8" inputmode="numeric">
          <div class="hint">Debe tener exactamente 8 dígitos.</div>
        </div>

        <div class="field col4">
          <label>Cédula</label>
          <input
            name="cedula"
            value="<?= h($row['cedula'] ?? '') ?>"
            placeholder="Ej: 0011234567890A"
            maxlength="14"
            pattern="\d{13}[A-Za-z]"
            title="Debe contener 13 números y 1 letra"
            style="text-transform:uppercase;"
          >
          <div class="hint">Debe tener exactamente 13 números y 1 letra.</div>
        </div>

        <div class="field col4">
          <label>Departamento</label>
          <select name="department" id="departmentSelect">
            <option value="">Seleccionar departamento</option>
            <?php foreach (array_keys($municipios) as $dep): ?>
              <option value="<?= h($dep) ?>" <?= ($row['department'] ?? '') === $dep ? 'selected' : '' ?>><?= h($dep) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field col4">
          <label>Municipio</label>
          <select name="municipality" id="municipalitySelect" data-selected="<?= h($row['municipality'] ?? '') ?>">
            <option value="">Seleccionar municipio</option>
          </select>
        </div>

        <div class="field col4">
          <label>Comunidad</label>
          <input name="community" value="<?= h($row['community'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label>Tipo de organización</label>
          <select name="organization_type">
            <option value="">Seleccionar</option>
            <option value="institucion" <?= ($row['organization_type'] ?? '') === 'institucion' ? 'selected' : '' ?>>Institución</option>
            <option value="privado" <?= ($row['organization_type'] ?? '') === 'privado' ? 'selected' : '' ?>>Privado</option>
            <option value="emprendimiento" <?= ($row['organization_type'] ?? '') === 'emprendimiento' ? 'selected' : '' ?>>Emprendimiento</option>
            <option value="estudiante" <?= ($row['organization_type'] ?? '') === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
            <option value="productor" <?= ($row['organization_type'] ?? '') === 'productor' ? 'selected' : '' ?>>Productor</option>
          </select>
        </div>

        <div class="field col4">
          <label>Nombre de organización</label>
          <input name="organization_name" value="<?= h($row['organization_name'] ?? '') ?>">
        </div>

        <div class="field col4">
          <label>Teléfono de la organización</label>
          <input name="organization_phone" value="<?= h($row['organization_phone'] ?? '') ?>" placeholder="Ej: 88888888" maxlength="8" inputmode="numeric">
        </div>

        <div class="field col6">
          <label>Ubicación de la organización</label>
          <input name="organization_location" value="<?= h($row['organization_location'] ?? '') ?>">
        </div>

        <div class="field col6">
          <label>Caracterización</label>
          <input name="characterization" value="<?= h($row['characterization'] ?? '') ?>" placeholder="Ej: productor, tostador, barista, catador...">
        </div>

        <div class="field col4">
          <label>Registro de marca</label>
          <select name="trademark_registration">
            <option value="">Seleccionar</option>
            <option value="si" <?= ($row['trademark_registration'] ?? '') === 'si' ? 'selected' : '' ?>>Sí</option>
            <option value="no" <?= ($row['trademark_registration'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
          </select>
        </div>

        <div class="field col4">
          <label>Número de socios</label>
          <input type="number" min="0" name="number_of_members" value="<?= h($row['number_of_members'] ?? '') ?>">
        </div>

        <div class="field col12">
          <label>Propósito del curso</label>
          <textarea name="course_purpose" rows="3"><?= h($row['course_purpose'] ?? '') ?></textarea>
        </div>

        <div class="field col12">
          <label>Proyección a futuro</label>
          <textarea name="future_projection" rows="3"><?= h($row['future_projection'] ?? '') ?></textarea>
        </div>

        <div class="field col12">
          <label>Observaciones</label>
          <textarea name="observations" rows="4"><?= h($row['observations'] ?? '') ?></textarea>
        </div>

      </div>

      <div class="actions">
        <button class="btn" type="submit"><?= $id > 0 ? 'Guardar cambios' : 'Crear estudiante' ?></button>
        <a class="btnS" href="students.php">← Cancelar</a>
        <a class="btnG" href="dashboard.php">Ir al dashboard</a>
      </div>
    </form>

    <div class="info-box">
      Verificá bien los datos personales, ubicación, organización y formación antes de guardar.
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

const municipiosPorDepartamento = <?= json_encode($municipios, JSON_UNESCAPED_UNICODE) ?>;

function loadMunicipalities() {
  const depSelect = document.getElementById('departmentSelect');
  const muniSelect = document.getElementById('municipalitySelect');
  const selected = muniSelect.dataset.selected || '';
  const dep = depSelect.value;

  muniSelect.innerHTML = '<option value="">Seleccionar municipio</option>';

  if (dep && municipiosPorDepartamento[dep]) {
    municipiosPorDepartamento[dep].forEach(function(muni) {
      const opt = document.createElement('option');
      opt.value = muni;
      opt.textContent = muni;
      if (muni === selected) opt.selected = true;
      muniSelect.appendChild(opt);
    });
  }
}

document.addEventListener('DOMContentLoaded', function() {
  loadMunicipalities();

  document.getElementById('departmentSelect').addEventListener('change', function() {
    const muniSelect = document.getElementById('municipalitySelect');
    muniSelect.dataset.selected = '';
    loadMunicipalities();
  });

  const cedulaInput = document.querySelector('input[name="cedula"]');
  if (cedulaInput) {
    cedulaInput.addEventListener('input', function() {
      this.value = this.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);
    });
  }
});
</script>
</body>
</html>