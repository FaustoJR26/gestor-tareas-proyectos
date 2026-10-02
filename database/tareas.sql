CREATE TABLE IF NOT EXISTS `tareas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(190) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('pendiente','en_progreso','en_revision','completada') NOT NULL DEFAULT 'pendiente',
  `creado_por` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `tareas_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;