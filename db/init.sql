-- Tabla de las notas.
-- MariaDB ejecuta este fichero solo la primera vez, cuando el disco está vacío.
-- La base de datos y el usuario los crea la imagen a partir del Secret.
CREATE TABLE IF NOT EXISTS notas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  texto VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
