CREATE TABLE IF NOT EXISTS solicitudes_permiso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  solicitante_id INT NOT NULL,
  permiso VARCHAR(50) NOT NULL,
  estado ENUM('pendiente','aprobada','aplicada','rechazada') NOT NULL DEFAULT 'pendiente',
  creada_en DATETIME NOT NULL,
  resuelto_por INT NULL,
  resuelto_en DATETIME NULL,
  motivo_rechazo VARCHAR(255) NULL
);

CREATE TABLE IF NOT EXISTS permisos_usuario (
  usuario_id INT NOT NULL,
  permiso VARCHAR(50) NOT NULL,
  concedido_en DATETIME NOT NULL,
  PRIMARY KEY (usuario_id, permiso)
);