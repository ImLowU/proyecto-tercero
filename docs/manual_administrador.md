# Manual de Administrador — SportTime

## Administrar usuarios

Cada rol tiene su apartado en el menú lateral, y los de **administradores** y **organizadores**
son la misma pantalla: lista, alta, edición, activar o desactivar, bloquear y desbloquear. Los
participantes se registran solos (Registros pendientes) y se gestionan en **Participantes**.

**Crear un administrador u organizador:**
1. Menú lateral → Gestión → **Administradores** (o **Organizadores**) → «+ Nuevo administrador»
2. Completar nombre, email y contraseña (mínimo 8 caracteres, con mayúscula, minúscula, número y símbolo)
3. Guardar

El **rol lo fija el apartado**, no el formulario: lo que se crea en Administradores es administrador
y lo que se crea en Organizadores es organizador. Cada apartado solo muestra y modifica las cuentas
de su rol; una cuenta de otro rol no se puede editar, activar ni desactivar desde ahí.

**Editar:** «Editar» en la fila. Dejar la contraseña en blanco para no cambiarla. El estado de una
cuenta bloqueada no se cambia desde el formulario: se desbloquea con «Desbloquear».

**Desactivar usuario:**
1. Clic en "Desactivar" en la fila del usuario (se vuelve «Activar» para reactivarla)
2. El usuario no puede iniciar sesión (estado = inactivo)
3. No se elimina para preservar auditoría

En la lista, la cuenta propia lleva la marca **Vos** y la fila dice «Es tu cuenta» en lugar de
ofrecer Desactivar o Bloquear; lo mismo pasa con el **único administrador activo** («Único
administrador activo»). Una cuenta bloqueada muestra hasta cuándo, y solo ofrece Desbloquear.

## Cuentas bloqueadas

Una cuenta se bloquea sola tras **5 intentos fallidos** de iniciar sesión (o a mano, desde el panel). El
bloqueo automático dura **15 minutos** y se levanta solo: pasado ese tiempo la persona puede volver a
entrar con su clave. El bloqueo manual dura hasta que alguien lo desbloquee.

- **Desbloquear** antes de tiempo: en la fila de la cuenta, «Desbloquear». Solo funciona con cuentas
  bloqueadas; desbloquear no activa una cuenta pendiente de aprobación ni una rechazada.
- Una persona cuya cuenta se bloquea, desactiva o cambia de rol **mientras tiene la sesión abierta** pierde
  la sesión en su siguiente pedido (con un aviso), no cuando la sesión vence.
- La sesión se cierra sola tras **60 minutos sin actividad**.
- No se puede bloquear ni desactivar al **único administrador activo**, ni a la propia cuenta: antes de
  hacerlo hay que crear o activar otro administrador.
- Una misma conexión (IP) que acumula **20 intentos fallidos en 15 minutos** queda rechazada un rato,
  aunque use la clave correcta. Si la rechazada es la de una sala compartida, esperar pasada la ventana.

Los tres números (5 intentos, 15 minutos de bloqueo, 20 por IP) son ajustes de la tabla
`configuracion_sistema` (`login_max_intentos`, `login_minutos_bloqueo`, `login_max_intentos_ip`,
`login_ventana_minutos`). Con `login_minutos_bloqueo = 0` el bloqueo automático dura hasta que un
administrador lo levante, salvo el del único administrador, que siempre vence.

## Asignar organizadores a torneos

1. Admin → Torneos → [torneo] → Gestionar
2. Usar el botón "Asignar organizador" (si está disponible) o crear el torneo con organizador

## Revisar auditoría

Panel Admin → Sistema → Auditoría:
- Filtrar por **acción**, por **rango de fechas** (el día «Hasta» entra completo) y buscar por usuario,
  descripción o tabla. 50 registros por página.
- Ver acciones críticas: logins, cambios de resultados, creaciones y **exportaciones de reportes**
  (`exportar_reporte`: quién descargó qué archivo y cuántas filas tenía).

## Buscar y filtrar las listas

Las listas largas (Torneos, Participantes, Equipos, Organizadores, Administradores y Auditoría) tienen
arriba una barra de búsqueda:

- Escribí parte del nombre y apretá **Buscar** (o Enter). No importan las mayúsculas ni las tildes:
  «avila» encuentra «Ávila». Participantes también busca por apodo, documento y correo; Equipos, por
  categoría y disciplina; las cuentas, por correo.
- Los **selectores** (formato, estado) se combinan con el texto.
- **Limpiar** vuelve a la lista completa. Abajo, «Mostrando 16–30 de 52» y los botones de página; al
  cambiar de página los filtros se conservan.
- La dirección de la página lleva los filtros (`/admin/torneos?q=liga&estado=en_curso&page=2`), así que
  se puede copiar y mandar.

## Reportes

**Sistema → Reportes** (solo administrador) muestra el estado general: torneos, participantes, equipos y
partidos jugados; torneos por estado y por formato; correcciones pendientes, aprobadas y rechazadas; cuentas
por rol, pendientes y bloqueadas; y una tabla con todos los torneos y su avance (se filtra igual que
las listas).

- **Imprimir / PDF** abre el diálogo del navegador con la hoja ya preparada (sin menús ni botones). Para
  guardar en PDF, elegir «Guardar como PDF» como impresora.
- **Descargar CSV** baja la tabla de torneos con el filtro aplicado, completa (no solo la página que se ve).
  Abre en Excel con doble clic: separador `;`, tildes bien y decimales con coma.

Cada torneo tiene además su propio reporte (botón **Reporte** en la lista y en su pantalla de gestión):
resumen, avance, tabla de posiciones (liga y suizo), todos los partidos, y tres descargas CSV
(posiciones, partidos e inscritos). Los CSV no llevan datos de contacto. Cada descarga queda en la
auditoría.

## Configuración del sistema

Panel Admin → Sistema → **Configuración**. Dos secciones:

- **Sitio**: el *nombre del sitio* (sale en el menú, el título de cada página y el pie), el *correo de contacto* (se
  muestra en el pie; vacío = no se muestra) y el interruptor de **perfiles públicos**. Con los perfiles
  desactivados, los perfiles de participantes y equipos solo los ve quien inició sesión; un visitante que intenta
  abrirlos va a la pantalla de inicio de sesión. Los nombres siguen apareciendo en las tablas de los torneos.
- **Inicio de sesión**: intentos fallidos antes de bloquear una cuenta (1 a 20), minutos que dura el bloqueo (0 = hasta que
  un administrador la desbloquee), intentos fallidos por dirección IP (1 a 1000) y la ventana, en minutos, en que se
  cuentan.

Al **Guardar cambios** la pantalla dice qué cambió. Si algún valor no sirve, no se guarda ninguno y se explica el
motivo sin perder lo que escribiste. Cada cambio queda en Auditoría (acción `actualizar_configuracion`) con los
valores de antes y de después.

## Gestionar módulos

Panel Admin → Sistema → Módulos:
- Activar/desactivar módulos del sistema
- Los módulos desactivados no permiten acciones relacionadas

## Respaldo de la base de datos

```bash
# Desde el servidor o contenedor
./scripts/backup.sh
# Genera: backups/sporttime_YYYYMMDD_HHMMSS.sql.gz
```

El script **verifica el volcado antes de darlo por bueno**: tamaño mínimo,
integridad del gzip, presencia de sentencias `CREATE TABLE`, de las tablas
propias de SportTime y de la línea final de `mysqldump` (`-- Dump completed`).
Si algo de eso falla, borra el archivo parcial y termina con código distinto de
cero, en vez de dejar en `backups/` un `.gz` vacío con nombre de respaldo bueno
(que es lo que pasaba antes cuando el `mysqldump` no llegaba a correr).

El respaldo incluye las **vistas** y los **triggers** —o sea, también el historial de la
base de datos (`historial_bd`) y las reglas que lo siguen escribiendo— y se hace como
administrador **dentro** del contenedor de la base, con la clave que ese contenedor ya
tiene: no hace falta definir ninguna contraseña en el servidor para respaldar. Al
restaurar, las vistas y los triggers vuelven con él y la restauración en sí no agrega
filas al historial.

Conviene revisar el código de salida en cualquier tarea programada:

```bash
./scripts/backup.sh || echo "EL RESPALDO FALLÓ" | mail -s "SportTime" admin@...
```

## Restaurar respaldo

```bash
./scripts/restore.sh backups/sporttime_20260601_120000.sql.gz
```

## Cuando alguien ve «Ocurrió un error inesperado»

La persona ve un mensaje con un **código de referencia** de 8 caracteres, por ejemplo `a1b2c3d4`. Con ese
código se encuentra el detalle en el registro de PHP: la clase del error, el archivo, qué pedido lo
provocó (ruta, IP y usuario) y la traza.

```bash
docker compose exec app grep -A14 "a1b2c3d4" /var/log/apache2/php_errors.log
```

Los mensajes que explican qué corregir («El nick no puede superar los 80 caracteres», «Ya existe un equipo
con ese nombre») **no** llevan código: son reglas que no se cumplieron, no errores del sistema.

## Monitoreo de la BD

```bash
./scripts/monitor_db.sh
# Muestra conteos por tabla, torneos por estado, últimas acciones
```

## Gestión de contenedores Docker

```bash
# Iniciar
./scripts/server_management.sh start

# Ver logs en tiempo real
./scripts/server_management.sh logs

# Acceder al contenedor de PHP
./scripts/server_management.sh shell-app

# Acceder a MySQL
./scripts/server_management.sh shell-db
```

## Quién corrige un resultado

Un resultado ya cargado se corrige de dos maneras, y solo una es directa:

- **El administrador corrige** desde el partido («Corregir»): el cambio se aplica en el acto, con
  motivo obligatorio y registro del valor anterior y el nuevo.
- **El organizador solicita**: ve «Solicitar corrección», solo en los partidos de **sus** torneos, y
  el cambio no se aplica hasta que un administrador lo aprueba en **Correcciones** (o lo rechaza,
  indicando el motivo).

El permiso `resultados / editar` de la matriz de Permisos habilita al organizador a *solicitar*; no
le da la corrección directa, que es solo del administrador. Un pedido armado a mano hacia la
corrección directa, o hacia el partido de otro organizador, se rechaza con «Sin permisos» y queda
anotado en el registro del servidor (`ACCESO_DENEGADO`).

## Corrección de resultados bloqueados

En Eliminación Directa: si el ganador ya avanzó a la siguiente ronda, la corrección queda bloqueada. Para corregir manualmente:
1. Identificar el enfrentamiento en la ronda posterior
2. Eliminar el enfrentamiento posterior desde MySQL con acceso de root
3. Luego corregir el resultado original

Esta operación debe registrarse manualmente en auditoría.

## Variables de entorno importantes

| Variable | Descripción |
|----------|-------------|
| `APP_ENV` | `development` o `production` |
| `DB_PASS` | Contraseña del usuario sporttime_user |
| `DB_ROOT_PASS` | Contraseña de root MySQL (arranque del contenedor `app`, que la usa para ajustar privilegios y sembrar datos). El respaldo y la restauración no la necesitan en el servidor: usan la del propio contenedor `db` |
| `ADMIN_PASS` | Contraseña inicial del administrador. Vacía = se genera una y se imprime una sola vez en `docker compose logs app` |
| `DEMO_PASS` | Contraseña común de las cuentas de demostración. Vacía = esas cuentas quedan sin clave (lo correcto en producción) |
