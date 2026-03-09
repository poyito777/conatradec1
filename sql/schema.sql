-- =========================
--  DB: docentes_db (schema)
-- =========================

-- (Opcional) si vas a recrear desde cero:
-- DROP TABLE IF EXISTS students;
-- DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  full_name VARCHAR(160) NOT NULL,
  student_code VARCHAR(60),
  school VARCHAR(120),

  -- NUEVOS CAMPOS (como en phpMyAdmin)
  course_type ENUM('barismo','catacion') NOT NULL DEFAULT 'barismo',
  course_level ENUM('basico','avanzado','intensivo') NOT NULL DEFAULT 'basico',
  phone VARCHAR(25) NULL,
  cedula VARCHAR(25) NULL,
  department VARCHAR(60) NULL,
  enrolled_at DATE NULL,
  final_grade DECIMAL(5,2) NULL,
  observations TEXT NULL,

  -- Estado (lo seguimos guardando, aunque se calcule por nota en el código)
  status ENUM('pendiente','aprobado','desaprobado') NOT NULL DEFAULT 'pendiente',

  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_students_teacher
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE groups_table (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  course_type ENUM('barismo','catacion') NOT NULL,
  course_level ENUM('basico','avanzado','intensivo') NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('activo','finalizado') NOT NULL DEFAULT 'activo',
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_groups_teacher
    FOREIGN KEY (teacher_id) REFERENCES users(id)
    ON DELETE CASCADE
);

CREATE TABLE group_students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_group_students_group
    FOREIGN KEY (group_id) REFERENCES groups_table(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_group_students_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,

  CONSTRAINT uq_group_student UNIQUE (group_id, student_id)
);

CREATE TABLE attendances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  group_id INT NOT NULL,
  teacher_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_attendances_group
    FOREIGN KEY (group_id) REFERENCES groups_table(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_attendances_teacher
    FOREIGN KEY (teacher_id) REFERENCES users(id)
    ON DELETE CASCADE,

  CONSTRAINT uq_group_attendance_date UNIQUE (group_id, attendance_date)
);

CREATE TABLE attendance_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attendance_id INT NOT NULL,
  student_id INT NOT NULL,
  present TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_attendance_items_attendance
    FOREIGN KEY (attendance_id) REFERENCES attendances(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_attendance_items_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,

  CONSTRAINT uq_attendance_student UNIQUE (attendance_id, student_id)
);