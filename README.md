# FlexArena - Sistema de Gestión Deportiva Modular

Plataforma web educativa para administrar torneos deportivos, mentales y electrónicos.

## Tecnologías

- Frontend: HTML, CSS, JavaScript y JSON.
- Backend: PHP orientado a objetos.
- Base de datos: MySQL.
- Arquitectura: Modelo-Vista-Controlador.
- Despliegue: XAMPP o Docker.

## Módulos implementados

- Usuarios y autenticación.
- Roles: administrador, organizador, participante y público.
- Participantes y equipos.
- Torneos.
- Liga todos contra todos.
- Eliminación directa, con llave inicial.
- Sistema suizo por rondas.
- Resultados y tabla de posiciones.
- Consulta pública.
- Auditoría y administración básica del sistema.

## Credenciales iniciales

- Email: `admin@sgdm.local`
- Password: `Admin1234!`

## Instalación con XAMPP en Linux

1. Copiar la carpeta `sgdm` a:

```bash
/opt/lampp/htdocs/sgdm
```

2. Iniciar XAMPP:

```bash
sudo /opt/lampp/lampp start
```

3. Importar la base:

```bash
sudo /opt/lampp/bin/mysql -u root < /opt/lampp/htdocs/sgdm/database/schema.sql
```

4. Abrir:

```txt
http://127.0.0.1/sgdm/public/login
```

## Instalación con Docker

```bash
docker compose up -d --build
```

Abrir:

```txt
http://127.0.0.1:8080/login
```

## Flujo de prueba recomendado

1. Entrar como admin.
2. Crear participantes o equipos.
3. Crear un torneo y elegir formato: liga, eliminación directa o suizo.
4. Entrar a Gestionar torneo.
5. Inscribir participantes.
6. Generar rondas.
7. Cargar resultados.
8. Revisar tabla de posiciones y vista pública.

## Seguridad aplicada

- `password_hash()` y `password_verify()` para contraseñas.
- Consultas preparadas con PDO.
- Validaciones básicas frontend/backend.
- Control de roles por controlador.
- Auditoría de acciones relevantes.
- Sesión regenerada al iniciar sesión.

## Nota académica

El código cubre el sistema funcional base. Los documentos externos pedidos por la letra —ESRE, Gantt, FODA, actas, manuales completos, estudio de hardware, OWASP formal, modelo 3D, etc.— deben completarse con información real del equipo de proyecto.

## Datos de prueba

Para cargar datos demo:

```bash
sudo /opt/lampp/bin/mysql -u root < /opt/lampp/htdocs/sgdm/database/seed.sql
```

Usuarios demo:

- `organizador@sgdm.local` / `Admin1234!`
- `participante1@sgdm.local` / `Admin1234!`

## Actualizar una instalación anterior

Para desarrollo local es más simple reiniciar la base:

```bash
sudo /opt/lampp/bin/mysql -u root -e "DROP DATABASE IF EXISTS sgdm;"
sudo /opt/lampp/bin/mysql -u root < /opt/lampp/htdocs/sgdm/database/schema.sql
```

Luego, opcionalmente, importar `seed.sql`.
# proyecto-tercero
