SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attendance_items;
DROP TABLE IF EXISTS attendances;
DROP TABLE IF EXISTS group_students;
DROP TABLE IF EXISTS groups_table;
DROP TABLE IF EXISTS student_grades;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;

-- ======================================================
-- USERS
-- ======================================================

CREATE TABLE users (

  id INT AUTO_INCREMENT PRIMARY KEY,

  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,

  role ENUM('admin','teacher') DEFAULT 'teacher',

  must_change_password TINYINT(1) DEFAULT 1,
  is_active TINYINT(1) DEFAULT 1,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ======================================================
-- STUDENTS
-- ======================================================

CREATE TABLE students (

  id INT AUTO_INCREMENT PRIMARY KEY,

  teacher_id INT NOT NULL,

  student_code VARCHAR(20) UNIQUE,

  full_name VARCHAR(200) NOT NULL,

  sex ENUM('masculino','femenino'),

  education_level ENUM(
    'secundaria',
    'tecnico',
    'universitario'
  ),

  profession VARCHAR(150),

  nationality ENUM(
    'nicaraguense',
    'extranjero'
  ),

  phone VARCHAR(20),
  cedula VARCHAR(20),

  school VARCHAR(200),

  course_type ENUM(
    'barismo',
    'catacion'
  ) DEFAULT 'barismo',

  course_level ENUM(
    'basico',
    'avanzado',
    'intensivo'
  ) DEFAULT 'basico',

  enrolled_at DATE,

  department VARCHAR(120),
  municipality VARCHAR(120),
  community VARCHAR(120),

  organization_type ENUM(
    'institucion',
    'privado',
    'emprendimiento',
    'estudiante',
    'productor'
  ),

  organization_name VARCHAR(200),
  organization_phone VARCHAR(20),
  organization_location VARCHAR(200),

  characterization VARCHAR(200),

  trademark_registration ENUM('si','no'),

  course_purpose TEXT,
  number_of_members INT,
  future_projection TEXT,

  final_grade DECIMAL(5,2),

  status ENUM(
    'pendiente',
    'aprobado',
    'desaprobado'
  ) DEFAULT 'pendiente',

  observations TEXT,
  notes TEXT,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_teacher (teacher_id),

  CONSTRAINT fk_student_teacher
  FOREIGN KEY (teacher_id)
  REFERENCES users(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ======================================================
-- GROUPS TABLE
-- ======================================================


CREATE TABLE groups_table (

  id INT AUTO_INCREMENT PRIMARY KEY,

  teacher_id INT NOT NULL,

  name VARCHAR(200) NOT NULL,

  course_type ENUM(
    'barismo',
    'catacion'
  ),

  course_level ENUM(
    'basico',
    'avanzado',
    'intensivo'
  ),

  department VARCHAR(120),

  start_date DATE,
  end_date DATE,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_group_teacher
  FOREIGN KEY (teacher_id)
  REFERENCES users(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ======================================================
-- GROUPS STUDENTS
-- ======================================================

CREATE TABLE group_students (

  id INT AUTO_INCREMENT PRIMARY KEY,

  group_id INT NOT NULL,
  student_id INT NOT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_group_student (group_id, student_id),

  CONSTRAINT fk_groupstudent_group
  FOREIGN KEY (group_id)
  REFERENCES groups_table(id)
  ON DELETE CASCADE,

  CONSTRAINT fk_groupstudent_student
  FOREIGN KEY (student_id)
  REFERENCES students(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ======================================================
-- ATTENDANCES
-- ======================================================

CREATE TABLE attendances (

  id INT AUTO_INCREMENT PRIMARY KEY,

  group_id INT NOT NULL,

  attendance_date DATE NOT NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT fk_attendance_group
  FOREIGN KEY (group_id)
  REFERENCES groups_table(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ======================================================
-- ATTENDANCE ITEMS
-- ======================================================

CREATE TABLE attendance_items (

  id INT AUTO_INCREMENT PRIMARY KEY,

  attendance_id INT NOT NULL,
  student_id INT NOT NULL,

  status ENUM(
    'presente',
    'ausente'
  ) DEFAULT 'presente',

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY unique_attendance_student (attendance_id, student_id),

  CONSTRAINT fk_attendanceitem_attendance
  FOREIGN KEY (attendance_id)
  REFERENCES attendances(id)
  ON DELETE CASCADE,

  CONSTRAINT fk_attendanceitem_student
  FOREIGN KEY (student_id)
  REFERENCES students(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- ======================================================
-- STUDENT GRADES
-- ======================================================


CREATE TABLE student_grades (

  id INT AUTO_INCREMENT PRIMARY KEY,

  student_id INT NOT NULL,
  group_id INT NOT NULL,
  teacher_id INT NOT NULL,

  exam1 DECIMAL(5,2),
  exam2 DECIMAL(5,2),
  exam3 DECIMAL(5,2),
  exam4 DECIMAL(5,2),
  exam5 DECIMAL(5,2),

  final_grade DECIMAL(5,2),

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY unique_student_grade (student_id, group_id),

  CONSTRAINT fk_grade_student
  FOREIGN KEY (student_id)
  REFERENCES students(id)
  ON DELETE CASCADE,

  CONSTRAINT fk_grade_group
  FOREIGN KEY (group_id)
  REFERENCES groups_table(id)
  ON DELETE CASCADE,

  CONSTRAINT fk_grade_teacher
  FOREIGN KEY (teacher_id)
  REFERENCES users(id)
  ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;