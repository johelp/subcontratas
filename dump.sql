-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-11-2025 a las 20:51:00
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `snow_subcontratas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contrapartes`
--

CREATE TABLE `contrapartes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `tipo` enum('escuela','autonomo') DEFAULT 'escuela',
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `contrapartes`
--

INSERT INTO `contrapartes` (`id`, `nombre`, `tipo`, `contacto`, `telefono`, `email`, `usuario_id`, `notas`, `activo`, `fecha_creacion`) VALUES
(1, 'ESS', 'escuela', '', '', '', NULL, 'European Ski School', 1, '2025-11-12 18:36:26'),
(2, 'GONDOLAS', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(3, 'THE NEW SCHOOL', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(4, 'EIS - MAS SKI', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(5, 'LUSA', 'escuela', '', '', '', NULL, 'No se anotan según Excel', 1, '2025-11-12 18:36:26'),
(6, 'RIO SPORT', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(7, 'IGLU', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(8, 'FUNDACIÓN DEPORTE Y DESAFIO', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(9, 'WHITE CAMPS', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(10, 'NIVALIS CLUB', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(11, 'CLUB MULHACÉN', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(12, 'SNOWPICKAPP', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(13, 'CARVING RENTAL', 'escuela', '', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(14, 'DUBI DISCAS', 'autonomo', 'Dubi Discas', '', '', NULL, 'Autónomo', 1, '2025-11-12 18:36:26'),
(15, 'RAFITA SKI & DO', 'autonomo', 'Rafita', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(16, 'DANI SKI&DO', 'autonomo', 'Dani', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(17, 'JUANJO NARANJITO', 'autonomo', 'Juanjo', '', '', NULL, '', 1, '2025-11-12 18:36:26'),
(18, 'FRAN GUZMAN', 'autonomo', 'Fran Guzmán', '', '', NULL, '', 1, '2025-11-12 18:36:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `fecha_pago` date NOT NULL,
  `contraparte_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `horas_saldadas` decimal(6,2) NOT NULL,
  `concepto` varchar(255) DEFAULT NULL,
  `quien_paga` enum('nosotros','ellos') NOT NULL COMMENT 'nosotros=Snow Motion paga, ellos=nos pagan',
  `notas` text DEFAULT NULL,
  `usuario_registro_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temporadas`
--

CREATE TABLE `temporadas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `temporadas`
--

INSERT INTO `temporadas` (`id`, `nombre`, `fecha_inicio`, `fecha_fin`, `activa`, `fecha_creacion`) VALUES
(1, '2025/2026', '2025-11-01', '2026-04-30', 1, '2025-11-10 01:54:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `contraparte_id` int(11) NOT NULL,
  `tipo` enum('favor','contra') NOT NULL COMMENT 'favor=cedimos, contra=solicitamos',
  `horas` decimal(6,2) NOT NULL,
  `disciplina` enum('ski','snowboard','ambos') DEFAULT NULL,
  `nivel` enum('principiante','intermedio','avanzado') DEFAULT NULL,
  `idiomas` varchar(200) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `usuario_registro_id` int(11) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id`, `fecha`, `contraparte_id`, `tipo`, `horas`, `disciplina`, `nivel`, `idiomas`, `notas`, `usuario_registro_id`, `fecha_creacion`) VALUES
(1, '2025-11-12', 3, 'favor', 3.00, 'ski', 'principiante', '', '', 1, '2025-11-12 22:31:55'),
(2, '2025-11-12', 3, 'favor', 3.00, 'ski', 'principiante', '', '', 1, '2025-11-12 22:33:52'),
(3, '2025-11-12', 3, 'contra', 2.00, '', '', '', '', 1, '2025-11-12 22:34:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `rol` enum('admin','escuela') DEFAULT 'escuela',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `nombre`, `rol`, `activo`, `fecha_creacion`) VALUES
(1, 'admin', '$2y$10$rgVmRedPJLfro9YmybRhGexC/qqnlv3anMm/t1U2FJEO9Ixz6.dWy', 'Administrador', 'admin', 1, '2025-11-10 01:54:52');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `contrapartes`
--
ALTER TABLE `contrapartes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contraparte_id` (`contraparte_id`),
  ADD KEY `usuario_registro_id` (`usuario_registro_id`);

--
-- Indices de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contraparte_id` (`contraparte_id`),
  ADD KEY `usuario_registro_id` (`usuario_registro_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `contrapartes`
--
ALTER TABLE `contrapartes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `contrapartes`
--
ALTER TABLE `contrapartes`
  ADD CONSTRAINT `contrapartes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`contraparte_id`) REFERENCES `contrapartes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`usuario_registro_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`contraparte_id`) REFERENCES `contrapartes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transacciones_ibfk_2` FOREIGN KEY (`usuario_registro_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
