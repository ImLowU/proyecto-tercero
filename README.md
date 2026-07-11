# SportTime

**Sistema de Gestión Deportiva Modular**

Plataforma web para organizar, administrar y consultar torneos deportivos, mentales y electrónicos.

---

## Inicio rápido

### Requisitos

- Docker Desktop (Windows/Mac/Linux)

### Levantar el proyecto

```bash
# 1. Copiar variables de entorno
cp .env.example .env

# 2. Levantar todos los contenedores
docker compose up -d

# 3. Acceder a la aplicación
#    App:       http://localhost:8082
#    phpMyAdmin: http://localhost:8083
```

La base de datos (`sporttime`) se inicializa automáticamente con el schema y el seed
(`seed_sporttime.sql`) al primer inicio.

### Credenciales de prueba

| Rol           | Email                  |
|---------------|------------------------|
| Administrador | admin@sporttime.com    |
| Organizador   | bruno@sporttime.com    |
| Organizador   | carla@sporttime.com    |
| Participante  | ivan@sporttime.com     |

> Las contraseñas son fuertes y únicas por usuario y **no se versionan** en el repositorio;
> se entregan por separado al responsable del proyecto. Se pueden (re)generar desde
> Panel Admin → Usuarios → Editar.

---

## Estructura del proyecto

```
sgdm/
├── app/
│   ├── controllers/   — Reciben requests, validan, llaman servicios
│   ├── models/        — Acceso a datos con PDO
│   ├── services/      — Lógica de negocio (formatos de torneo)
│   └── views/         — Plantillas PHP con layouts
├── config/            — Configuración de la app y rutas
├── core/              — Router, Database, Session, Auth, View, CSRF
├── database/          — schema.sql, seed_sporttime.sql, scripts SQL
├── docs/              — Documentación completa
├── public/            — Front controller y assets (CSS, JS, img)
├── scripts/           — Scripts de administración y backup
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

---

## Formatos de torneo

| Formato | Descripción |
|---------|-------------|
| **Liga** | Round-robin todos contra todos. Tabla de posiciones con criterios de desempate. |
| **Eliminación Directa** | Bracket con avance de ganadores. Soporta byes para N no potencia de 2. |
| **Sistema Suizo** | Rondas por rendimiento acumulado. Emparejamiento sin repetición de rivales. |

---

## Roles del sistema

- **Administrador**: acceso total
- **Organizador**: gestiona torneos asignados
- **Participante**: consulta sus torneos y resultados
- **Público**: vista pública sin autenticación

---

## Documentación

Ver carpeta `/docs/`:
- `documentacion_funcional.md`
- `documentacion_tecnica.md`
- `documentacion_seguridad.md`
- `manual_usuario.md`
- `manual_administrador.md`
- `plan_testing.md`
- `owasp.md`
