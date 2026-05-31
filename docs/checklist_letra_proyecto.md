# Checklist FlexArena vs letra oficial

## Cumplido en el código

- Arquitectura MVC: modelos, vistas, controladores, configuración, assets y scripts separados.
- Frontend: HTML, CSS, JavaScript y JSON preparado para configuración/consumo futuro.
- Backend: PHP orientado a objetos.
- Base de datos: MySQL con modelo relacional.
- Roles: administrador, organizador, participante y usuario público.
- Autenticación: login/logout, sesiones, password_hash/password_verify.
- Usuarios: crear, editar, activar/desactivar.
- Participantes: crear, editar, activar/desactivar.
- Equipos: crear, editar y cargar miembros.
- Torneos: crear, editar, cambiar estado, visibilidad pública.
- Liga: generación automática de calendario todos contra todos.
- Eliminación directa: generación de llave inicial.
- Sistema suizo: generación de rondas por rendimiento evitando repeticiones cuando es posible.
- Resultados: carga y edición de marcadores.
- Posiciones: recálculo automático tras cargar resultados.
- Consulta pública: torneos publicados, participantes, calendario, resultados y posiciones.
- Administración del sistema: auditoría y resumen de módulos.
- Seguridad básica: consultas preparadas, hashes, validaciones, control de roles y auditoría.
- Docker: Dockerfile y docker-compose.
- Scripts: instalación XAMPP, respaldo y monitoreo básico.
- DCL: archivo con usuario de base de datos de permisos limitados.
- Datos de prueba: seed.sql con 50 participantes demo.

## Pendiente fuera del código

Estos puntos dependen de información real del grupo y deben documentarse aparte:

- ESRE IEEE 29148 completo.
- Casos de uso y UML formales.
- Diagramas de clase completos.
- Gantt y seguimiento del proyecto.
- Actas de reunión.
- FODA.
- Estudio costo-beneficio.
- Manual de usuario completo por rol.
- Manual de administrador completo.
- Plan de testing formal.
- Informe OWASP formal.
- Estudio de hardware, red, firewall, antivirus, SSL y políticas de respaldo en ambiente real.
- Modelo 3D de producto para marketing.
