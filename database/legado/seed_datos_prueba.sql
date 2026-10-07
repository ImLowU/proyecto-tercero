/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: nextleague
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `usuarios`
--

INSERT IGNORE INTO `usuarios` (`id_usuario`, `nombre_usuario`, `correo`, `contrasena_hash`, `foto_perfil`, `biografia`, `rol`, `token_recuperacion`, `token_recuperacion_vence`, `activo`, `fecha_registro`) VALUES (1,'admin','admin@nextleague.com','$2y$10$Sav18ZWDPzVAI5fc4my9QOJpOkh13BK6kY8ddmcSI/rYxTwCcxxSe',NULL,NULL,'administrador',NULL,NULL,1,'2026-09-15 10:50:31'),
(2,'usuario1','usuario1@gmail.com','$2y$10$myVU0uCNwHRsC6fflISjdeMKN.AKhG81Ncb/PDk8IpdL6O/pAkrKG',NULL,NULL,'organizador',NULL,NULL,1,'2026-09-15 10:50:36'),
(3,'usuario2','usuario2@gmail.com','$2y$10$E5Z52XrhZavUsSie5HpBAu22lZtGyriqvlbaKH6hlGPiIyELbFGWm',NULL,NULL,'organizador',NULL,NULL,1,'2026-09-15 10:50:37'),
(4,'usuario3','usuario3@gmail.com','$2y$10$jveJ9xBzOA98XjJ0N8m5Q.V8wrtRwf.JNQyQjEeyA7ql2e61TGY6y',NULL,NULL,'organizador',NULL,NULL,1,'2026-09-15 10:50:37'),
(5,'usuario4','usuario4@gmail.com','$2y$10$qAZAPeZ8nYLMrgv4OOI/MO.YZI5jf2u2m4b9BAcfuuiGIjJDS0wWO',NULL,NULL,'organizador',NULL,NULL,1,'2026-09-15 10:50:37'),
(6,'usuario5','usuario5@gmail.com','$2y$10$yw5lqaD/Mb1NWQuwnuvwW.7/sANQALbMeotuUsVg12Uu8xgm5tw5i',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(7,'usuario6','usuario6@gmail.com','$2y$10$9E1ZVi4jYhJ3aLU.LXGa0.mbNNW9loyT5NhQlCZGQLDQbwZoTU6T2',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(8,'usuario7','usuario7@gmail.com','$2y$10$WOlOjz0a.Z3sv1QLFa9AkuEPnZ5n40NlT0IS6i07yy/PTcvPwcPU2',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(9,'usuario8','usuario8@gmail.com','$2y$10$.ibrixY4NOkNOKvVgDFnzuFyBq2rksPB7sgNfFMZ2OMAuFnXmZHzC',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(10,'usuario9','usuario9@gmail.com','$2y$10$8/cVQL84HtPar3zEYpnaGeIgV4dxkyq9Rtmr759Kcb3qyVtNVnH2G',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(11,'usuario10','usuario10@gmail.com','$2y$10$dXVMFq9NwIXXUMKp4itshekcAYMY47R/50/EqQZdz/L1GzK4FvmY2',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(12,'usuario11','usuario11@gmail.com','$2y$10$C62/aKf5SVpYBwTE/PO8jO0e5W.0sA6Jtod9aPkdC2TsTMZ58GA/e',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(13,'usuario12','usuario12@gmail.com','$2y$10$fqoz3pXxJMqIiHtCUDB0COw7PaYBQ2jxDKPJE3Fsbcv7rvrGW4XU.',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(14,'usuario13','usuario13@gmail.com','$2y$10$y0yJ6dNwXjw4pLueRxsYGei0K0Y35qacALv29Ga5FGV.cDU677Hwu',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(15,'usuario14','usuario14@gmail.com','$2y$10$By2ZvCd7rIVshvB3zEr3OOStFnQ7Al/ipddji5ujzU.BSOxSRktde',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(16,'usuario15','usuario15@gmail.com','$2y$10$Q5D9yXdIymvVDDQBzh.4DOxK/sShsoPK3Znofy0my9RsQkQHljn8y',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(17,'usuario16','usuario16@gmail.com','$2y$10$fgRSTc3gtuB8eICCcBSABO5xzrL.gqZw7SdthR4ijGH.1dpaRa6u6',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(18,'usuario17','usuario17@gmail.com','$2y$10$CNMdZ.tUjqvDUDVH6RQtyeJogwMEMaDiHcgSNtVtvHfxGKwEyrtYW',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:37'),
(19,'usuario18','usuario18@gmail.com','$2y$10$8iGY9mqQLeKZeIBMDAAAMOtE4310iFxvIuqoABHRnIQV6HkMpDM.e',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:38'),
(20,'usuario19','usuario19@gmail.com','$2y$10$cv1EZgd9Fd4uCVcBizkgO.pzkZm3Yn57j5drM2O7irDiaGDuaktUq',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:38'),
(21,'usuario20','usuario20@gmail.com','$2y$10$S46z3b2f6etvoudXarT0zOpisPEpJECtQkstQR/7Kw.0piS1Q3Xta',NULL,NULL,'participante',NULL,NULL,1,'2026-09-15 10:50:38');

--
-- Dumping data for table `torneos`
--

INSERT IGNORE INTO `torneos` (`id_torneo`, `nombre`, `id_juego`, `id_organizador`, `formato`, `num_equipos`, `premio`, `moneda`, `fecha_inicio`, `fecha_fin`, `descripcion`, `logo`, `reglas_pdf`, `estado`, `fecha_creacion`) VALUES (1,'Copa Demostración NextLeague',1,2,'eliminacion_directa',6,5000.00,'UYU','2026-09-20','2026-09-30','Torneo de demostracion para probar el sistema de punta a punta, con 6 equipos jugando eliminacion directa.','torneo_6aa9e1849e697.png','reglas_6aa9e1849e722.pdf','en_curso','2026-09-16 00:23:32');

--
-- Dumping data for table `equipos`
--

INSERT IGNORE INTO `equipos` (`id_equipo`, `id_torneo`, `nombre`, `logo`, `id_capitan`) VALUES (1,1,'Tigres',NULL,6),
(2,1,'Aguilas',NULL,7),
(3,1,'Leones',NULL,8),
(4,1,'Halcones',NULL,9),
(5,1,'Panteras',NULL,10),
(6,1,'Lobos',NULL,11);

--
-- Dumping data for table `equipo_miembros`
--

INSERT IGNORE INTO `equipo_miembros` (`id_equipo`, `id_usuario`, `fecha_union`) VALUES (1,6,'2026-09-16 00:23:43'),
(1,12,'2026-09-16 00:23:54'),
(2,7,'2026-09-16 00:23:43'),
(3,8,'2026-09-16 00:23:43'),
(4,9,'2026-09-16 00:23:43'),
(5,10,'2026-09-16 00:23:43'),
(6,11,'2026-09-16 00:23:43');

--
-- Dumping data for table `equipo_solicitudes`
--

INSERT IGNORE INTO `equipo_solicitudes` (`id_solicitud`, `id_equipo`, `id_usuario`, `estado`, `fecha_solicitud`, `fecha_respuesta`) VALUES (1,1,12,'aceptada','2026-09-16 00:23:54','2026-09-16 00:23:54'),
(2,2,13,'rechazada','2026-09-16 00:23:54','2026-09-16 00:23:54');

--
-- Dumping data for table `participantes`
--

INSERT IGNORE INTO `participantes` (`id_participante`, `id_torneo`, `id_usuario`, `id_equipo`, `fecha_inscripcion`) VALUES (1,1,NULL,1,'2026-09-16 00:23:43'),
(2,1,NULL,2,'2026-09-16 00:23:43'),
(3,1,NULL,3,'2026-09-16 00:23:43'),
(4,1,NULL,4,'2026-09-16 00:23:43'),
(5,1,NULL,5,'2026-09-16 00:23:43'),
(6,1,NULL,6,'2026-09-16 00:23:43');

--
-- Dumping data for table `enfrentamientos`
--

INSERT IGNORE INTO `enfrentamientos` (`id_enfrentamiento`, `id_torneo`, `ronda`, `id_equipo_local`, `id_equipo_visitante`, `fecha_hora`, `resultado_local`, `resultado_visitante`, `id_ganador`, `estado`) VALUES (1,1,1,4,NULL,NULL,NULL,NULL,4,'finalizado'),
(2,1,1,5,NULL,NULL,NULL,NULL,5,'finalizado'),
(3,1,1,3,2,NULL,2,1,3,'finalizado'),
(4,1,1,6,1,NULL,NULL,NULL,NULL,'pendiente'),
(5,1,2,4,5,NULL,NULL,NULL,NULL,'pendiente'),
(6,1,2,3,NULL,NULL,NULL,NULL,NULL,'pendiente'),
(7,1,3,NULL,NULL,NULL,NULL,NULL,NULL,'pendiente');

--
-- Dumping data for table `llaves`
--

INSERT IGNORE INTO `llaves` (`id_llave`, `id_torneo`, `ronda`, `posicion`, `id_enfrentamiento`) VALUES (1,1,1,0,1),
(2,1,1,1,2),
(3,1,1,2,3),
(4,1,1,3,4),
(5,1,2,0,5),
(6,1,2,1,6),
(7,1,3,0,7);

--
-- Dumping data for table `tabla_posiciones`
--


--
-- Dumping data for table `auditoria`
--

INSERT IGNORE INTO `auditoria` (`id_auditoria`, `id_usuario`, `accion`, `entidad`, `id_entidad`, `detalle`, `fecha`) VALUES (1,2,'crear','usuario',2,'Registro de cuenta nueva (organizador)','2026-09-15 10:50:36'),
(2,3,'crear','usuario',3,'Registro de cuenta nueva (organizador)','2026-09-15 10:50:37'),
(3,4,'crear','usuario',4,'Registro de cuenta nueva (organizador)','2026-09-15 10:50:37'),
(4,5,'crear','usuario',5,'Registro de cuenta nueva (organizador)','2026-09-15 10:50:37'),
(5,6,'crear','usuario',6,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(6,7,'crear','usuario',7,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(7,8,'crear','usuario',8,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(8,9,'crear','usuario',9,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(9,10,'crear','usuario',10,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(10,11,'crear','usuario',11,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(11,12,'crear','usuario',12,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(12,13,'crear','usuario',13,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(13,14,'crear','usuario',14,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(14,15,'crear','usuario',15,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(15,16,'crear','usuario',16,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(16,17,'crear','usuario',17,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(17,18,'crear','usuario',18,'Registro de cuenta nueva (participante)','2026-09-15 10:50:37'),
(18,19,'crear','usuario',19,'Registro de cuenta nueva (participante)','2026-09-15 10:50:38'),
(19,20,'crear','usuario',20,'Registro de cuenta nueva (participante)','2026-09-15 10:50:38'),
(20,21,'crear','usuario',21,'Registro de cuenta nueva (participante)','2026-09-15 10:50:38'),
(21,2,'crear','torneo',1,'Torneo \"Copa Demostración NextLeague\" creado','2026-09-16 00:23:32'),
(22,6,'crear','equipo',1,'Equipo \"Tigres\" creado','2026-09-16 00:23:43'),
(23,7,'crear','equipo',2,'Equipo \"Aguilas\" creado','2026-09-16 00:23:43'),
(24,8,'crear','equipo',3,'Equipo \"Leones\" creado','2026-09-16 00:23:43'),
(25,9,'crear','equipo',4,'Equipo \"Halcones\" creado','2026-09-16 00:23:43'),
(26,10,'crear','equipo',5,'Equipo \"Panteras\" creado','2026-09-16 00:23:43'),
(27,11,'crear','equipo',6,'Equipo \"Lobos\" creado','2026-09-16 00:23:43'),
(28,6,'aceptar','solicitud_equipo',1,'Solicitud a \"Tigres\" aceptada','2026-09-16 00:23:54'),
(29,7,'rechazar','solicitud_equipo',2,'Solicitud a \"Aguilas\" rechazada','2026-09-16 00:23:54'),
(30,2,'cargar','resultado',3,'Resultado 2-1','2026-09-16 00:24:09');
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-16  0:24:24
