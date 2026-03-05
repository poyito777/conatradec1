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