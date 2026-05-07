# Arquitectura de la Información
## Portal Web — Parque Industrial de Catamarca

**Alumnos:** Francisco Barrionuevo y David Nicolao  
**Materia:** Pasantía  
**Curso:** 3er año  
**Año:** 2025 / 2026  
**Versión:** 2.0  
**Fecha:** Mayo 2026  

---

## Índice

1. [Presentación del Proyecto](#1-presentación-del-proyecto)
2. [Misión](#2-misión)
3. [Visión](#3-visión)
4. [Objetivos](#4-objetivos)
5. [Roles del Sistema](#5-roles-del-sistema)
6. [Funciones por Rol](#6-funciones-por-rol)
7. [Información Técnica](#7-información-técnica)
8. [Base de Datos](#8-base-de-datos)
9. [Mapa de Carpetas del Proyecto](#9-mapa-de-carpetas-del-proyecto)
10. [Mapa de Enlaces del Proyecto](#10-mapa-de-enlaces-del-proyecto)

---

## 1. Presentación del Proyecto

El **Portal Web del Parque Industrial de Catamarca** es una plataforma digital de gestión B2G (Business-to-Government) y B2B (Business-to-Business) desarrollada para el Ministerio de Industria de la Provincia de Catamarca, Argentina.

El sistema nació de la necesidad de digitalizar y centralizar la relación entre el Ministerio de Industria y las empresas radicadas en el Parque Industrial El Pantanillo, eliminando el intercambio manual de información (papel, correo informal, hojas de cálculo) y reemplazándolo por una plataforma única, segura y accesible desde cualquier dispositivo.

El proyecto fue desarrollado íntegramente por estudiantes de pasantía de 3er año como parte de su formación técnica, abarcando desde la definición de requerimientos con el organismo público hasta la puesta en producción en la nube. El sistema cubre tres frentes simultáneos:

- **Sitio público:** vitrina digital del parque industrial para la ciudadanía, inversores y emprendedores.
- **Panel de empresa:** herramienta de autogestión para cada empresa radicada.
- **Panel del ministerio:** consola de administración total para el organismo gubernamental.

El desarrollo insumió aproximadamente 18 meses (septiembre 2024 – febrero 2026), desde los primeros archivos de configuración hasta la entrega final con documentación completa.

---

## 2. Misión

Proveer al Ministerio de Industria de Catamarca y a las empresas del Parque Industrial una plataforma digital integrada que facilite la gestión administrativa, la comunicación institucional y la difusión pública del parque, reduciendo la carga burocrática mediante procesos digitales seguros, trazables y accesibles.

---

## 3. Visión

Ser la plataforma de referencia para la gestión de parques industriales en Argentina, replicable en otros distritos industriales provinciales, que sirva como modelo de modernización del Estado y de articulación digital entre el sector público y el sector productivo.

---

## 4. Objetivos

### 4.1 Objetivo General

Digitalizar y centralizar la gestión del Parque Industrial de Catamarca mediante un portal web que conecte al Ministerio de Industria con las empresas radicadas, permitiendo la administración de datos, la comunicación institucional y la difusión pública en un único sistema.

### 4.2 Objetivos Específicos

- Proveer una **vitrina digital pública** con directorio de empresas, mapa interactivo, estadísticas y noticias del parque.
- Permitir la **autogestión empresarial** completa: perfil, publicaciones, mensajería y formularios desde un dashboard propio.
- Facilitar al Ministerio la **administración y supervisión** de empresas, contenido y datos estadísticos desde un panel centralizado.
- Implementar un sistema de **Declaraciones Juradas (DDJJ)** con flujo de envío, revisión y aprobación para datos operativos críticos.
- Ofrecer **formularios dinámicos** personalizables que el ministerio puede crear y enviar a empresas específicas.
- Publicar **estadísticas e indicadores** del parque configurables en tiempo real por el ministerio.
- Permitir que emprendedores **presenten proyectos de radicación** desde el sitio público.
- Garantizar la **seguridad y trazabilidad** de todas las operaciones mediante registro de actividad completo.

---

## 5. Roles del Sistema

El sistema contempla tres roles diferenciados con accesos y capacidades distintas:

### 5.1 Visitante (Anónimo)

**Descripción:** Cualquier persona que accede al sitio sin autenticación. No requiere registro. Puede ser un ciudadano común, un periodista, un potencial inversor o un emprendedor interesado en radicarse en el parque.

**Características:**
- Acceso completamente libre a todas las páginas públicas.
- No puede acceder a ningún panel privado.
- Puede presentar un proyecto de radicación sin necesidad de cuenta.
- Sus interacciones (visitas a perfiles, "me gusta") se registran por dirección IP.

---

### 5.2 Empresa (Autenticado)

**Descripción:** Entidad radicada o en proceso de radicación en el Parque Industrial. Requiere registro previo y activación de cuenta por correo electrónico. Cada empresa tiene un único usuario asociado (relación 1:1).

**Características:**
- Accede al panel privado en `/public/empresa/`.
- Gestiona exclusivamente sus propios datos (no puede ver datos de otras empresas).
- Mantiene comunicación directa y privada con el ministerio.
- Sus publicaciones requieren aprobación del ministerio antes de aparecer en el sitio público.
- Los formularios de datos que completa quedan registrados con fecha, hora e IP como declaración jurada.

**Estados posibles de una empresa:**
| Estado | Descripción |
|--------|-------------|
| `pendiente` | Registrada, esperando activación o aprobación del ministerio |
| `activa` | Habilitada con acceso completo al sistema |
| `suspendida` | Temporalmente inhabilitada por el ministerio |
| `inactiva` | Sin actividad o dada de baja del parque |

---

### 5.3 Ministerio (Administrador)

**Descripción:** Agente del Ministerio de Industria de la Provincia de Catamarca con acceso administrativo total al sistema. Puede gestionar todas las empresas, el contenido del sitio público y los datos del parque.

**Características:**
- Accede al panel administrativo en `/public/ministerio/`.
- Tiene visibilidad total sobre todas las empresas y sus datos.
- Puede aprobar, rechazar o suspender empresas y publicaciones.
- Es el único rol que puede crear formularios dinámicos y enviarlos a empresas.
- Controla qué información estadística se muestra públicamente.
- Recibe notificaciones de todas las acciones relevantes del sistema.

---

### 5.4 Admin (Superadministrador)

**Descripción:** Rol técnico equivalente al de ministerio pero con permisos extendidos de sistema. Utilizado para tareas de mantenimiento y configuración avanzada. En el código, la mayoría de las páginas del ministerio aceptan tanto `ministerio` como `admin`.

---

## 6. Funciones por Rol

### 6.1 Como Visitante, yo puedo...

| # | Función |
|---|---------|
| 1 | Explorar el home con carrusel de banners, empresas destacadas y estadísticas resumidas del parque. |
| 2 | Navegar el directorio completo de empresas con filtros por rubro, zona y búsqueda de texto. |
| 3 | Ver el perfil público detallado de cada empresa (datos, logo, descripción, publicaciones, contacto). |
| 4 | Acceder al mapa interactivo del parque con marcadores geolocalizados de cada empresa. |
| 5 | Consultar las estadísticas e indicadores públicos del parque (empleos, producción, rubros, etc.). |
| 6 | Leer noticias y publicaciones de empresas aprobadas y dar "me gusta" desde mi IP. |
| 7 | Ver la página institucional "El Parque" con historia, misión y zonas del parque. |
| 8 | Presentar un proyecto de radicación mediante el formulario público disponible en el sitio. |
| 9 | Iniciar sesión con mis credenciales si tengo cuenta en el sistema. |
| 10 | Recuperar mi contraseña mediante el envío de un token a mi correo electrónico. |

---

### 6.2 Como Empresa, yo puedo...

**Cuenta y Acceso**
| # | Función |
|---|---------|
| 1 | Registrarme en el sistema completando el formulario con mis datos empresariales. |
| 2 | Activar mi cuenta haciendo clic en el enlace enviado a mi correo electrónico. |
| 3 | Iniciar y cerrar sesión de forma segura desde cualquier dispositivo. |
| 4 | Cambiar mi contraseña de acceso desde mi panel privado. |

**Perfil**
| # | Función |
|---|---------|
| 5 | Ver mi dashboard con un resumen del estado de mi cuenta, publicaciones y formularios pendientes. |
| 6 | Editar mi perfil público: nombre, CUIT, rubro, descripción, teléfono y correo de contacto. |
| 7 | Subir y actualizar mi logo y mi imagen de portada (almacenados en Cloudinary o servidor local). |
| 8 | Cargar mis coordenadas GPS (latitud/longitud) para aparecer correctamente en el mapa. |
| 9 | Agregar y actualizar mis links de redes sociales (Facebook, Instagram, LinkedIn). |

**Publicaciones**
| # | Función |
|---|---------|
| 10 | Crear publicaciones/noticias con título, contenido e imagen principal. |
| 11 | Guardar publicaciones como borrador antes de enviarlas al ministerio para su revisión. |
| 12 | Subir archivos adjuntos a mis publicaciones (imágenes, PDFs). |
| 13 | Editar o eliminar mis publicaciones en estado borrador. |
| 14 | Ver el estado actual de mis publicaciones (borrador / pendiente / aprobado / rechazado). |

**Mensajería**
| # | Función |
|---|---------|
| 15 | Enviar mensajes directos al ministerio con asunto, contenido y archivos adjuntos. |
| 16 | Recibir y leer respuestas del ministerio en mi bandeja de entrada. |
| 17 | Ver el historial completo de mi hilo de mensajes con el ministerio. |

**Formularios y DDJJ**
| # | Función |
|---|---------|
| 18 | Ver la lista de períodos de declaración jurada disponibles con su estado actual. |
| 19 | Completar la Declaración Jurada de Datos Críticos con información laboral, productiva, ambiental y comercial. |
| 20 | Guardar una DDJJ en borrador para completarla en otro momento antes del vencimiento. |
| 21 | Enviar la DDJJ firmando la declaración jurada (se registra IP y timestamp). |
| 22 | Ver el historial de mis DDJJ enviadas con su estado (borrador/enviado/aprobado/rechazado). |
| 23 | Ver y responder formularios dinámicos enviados por el ministerio. |
| 24 | Adjuntar archivos como respuesta a preguntas de formularios dinámicos que lo requieran. |

**Notificaciones**
| # | Función |
|---|---------|
| 25 | Recibir notificaciones cuando una publicación mía es aprobada o rechazada. |
| 26 | Recibir notificaciones cuando el ministerio envía un nuevo formulario dinámico. |
| 27 | Recibir notificaciones cuando una DDJJ es aprobada o rechazada con observaciones. |
| 28 | Ver el centro de notificaciones con todas mis alertas y marcarlas como leídas. |

---

### 6.3 Como Ministerio, yo puedo...

**Dashboard y Visión Global**
| # | Función |
|---|---------|
| 1 | Ver el dashboard administrativo con métricas clave: total de empresas, empleos, formularios pendientes y actividad reciente. |

**Gestión de Empresas**
| # | Función |
|---|---------|
| 2 | Ver el listado completo de empresas con filtros por estado, rubro y zona. |
| 3 | Ver el detalle completo de cada empresa: datos generales, DDJJ históricas, publicaciones, mensajes y log de actividad. |
| 4 | Editar cualquier dato de una empresa registrada en el sistema. |
| 5 | Registrar una nueva empresa directamente desde el ministerio, con ubicación en mapa. |
| 6 | Activar o suspender empresas según su situación en el parque. |
| 7 | Ver el log de auditoría de cada empresa con cada acción realizada, datos anteriores y nuevos. |

**Gestión de Contenido**
| # | Función |
|---|---------|
| 8 | Moderar publicaciones de empresas: aprobar, rechazar indicando el motivo, o destacar. |
| 9 | Publicar comunicados oficiales del ministerio hacia las empresas. |
| 10 | Gestionar el carrusel de banners del home: agregar, editar, ordenar y activar/desactivar con fechas de vigencia. |
| 11 | Editar el contenido de la página pública "El Parque / Nosotros". |
| 12 | Ver y gestionar las solicitudes recibidas del formulario público "Presentar Proyecto". |

**Formularios Dinámicos**
| # | Función |
|---|---------|
| 13 | Crear formularios dinámicos con campos personalizados (texto, número, fecha, selección, archivo). |
| 14 | Editar la estructura de formularios existentes. |
| 15 | Seleccionar empresas destinatarias y enviar un formulario, generando notificaciones automáticas. |
| 16 | Ver las respuestas de cada empresa a un formulario específico, incluyendo archivos adjuntos. |
| 17 | Hacer seguimiento global del estado de respuestas: cuántas completaron, cuántas están pendientes. |
| 18 | Imprimir o exportar respuestas de formularios en vista optimizada para PDF. |
| 19 | Revisar, aprobar o rechazar con observaciones las DDJJ enviadas por empresas. |

**Análisis y Exportación**
| # | Función |
|---|---------|
| 20 | Ver gráficos estadísticos del parque: distribución por rubro, empleo por género, CO2, producción y facturación. |
| 21 | Configurar qué indicadores estadísticos se muestran públicamente en el sitio. |
| 22 | Exportar datos de empresas en formato CSV o Excel con filtros por rubro, zona y estado. |
| 23 | Consultar el log general de actividad y auditoría del sistema. |

**Mensajería y Notificaciones**
| # | Función |
|---|---------|
| 24 | Leer y responder mensajes de las empresas desde la bandeja de entrada. |
| 25 | Enviar mensajes directos a empresas específicas. |
| 26 | Ver el centro de notificaciones con alertas de nuevas empresas, mensajes y formularios. |
| 27 | Gestionar plantillas de respuesta para agilizar la mensajería recurrente. |

---

## 7. Información Técnica

### 7.1 Stack Tecnológico

| Componente | Tecnología | Versión / Detalle |
|------------|-----------|-------------------|
| Lenguaje Backend | PHP | 8.2+ — Procedural, sin framework MVC |
| Acceso a Base de Datos | PDO | Prepared statements exclusivamente. Singleton `getDB()` |
| Base de Datos | MySQL / MariaDB | 10.4+ — Charset `utf8mb4`, ~26 tablas |
| Servidor Local | Apache (XAMPP) | Windows 11 — `localhost/parque_industrial` |
| Servidor Producción | Render (PaaS) | Docker + variables de entorno |
| Frontend CSS | Bootstrap 5.3.2 | + Bootstrap Icons — sin compilación |
| Mapas | Leaflet.js 1.9.4 | Tiles Esri Satellite — `public/js/parque-leaflet.js` |
| Gráficos | Chart.js (CDN) | Estadísticas y panel ministerio |
| JavaScript | Vanilla JS ES6+ | `public/js/main.js` — AJAX, validaciones, UI |
| Almacenamiento de Archivos | Cloudinary + local | Cloudinary en producción, `public/uploads/` en local |
| Email | Resend API / Gmail SMTP | Resend como primario, Gmail como fallback |
| Zona Horaria | America/Argentina/Catamarca | UTC-3 |
| Contenedorización | Docker | `Dockerfile` en raíz del proyecto |

### 7.2 Arquitectura de la Aplicación

El sistema sigue una arquitectura **MPA (Multi-Page Application) procedural** sin frameworks. Cada página PHP es autocontenida y sigue este patrón:

```
1. require '../config/config.php'    → constantes, sesión, CSRF
2. require '../config/database.php'  → PDO singleton
3. require '../includes/auth.php'    → instancia $auth
4. $auth->requireRole([...])         → guardia de rol
5. lógica PHP (consultas, procesamiento POST)
6. require '../includes/header.php'  → HTML head + navbar
7. HTML de la página
8. require '../includes/footer.php'  → cierre HTML + scripts JS
```

### 7.3 Autenticación y Sesiones

El sistema de autenticación está centralizado en `includes/auth.php` (clase `Auth`).

**Variables de sesión activas tras login:**

| Variable | Valor | Descripción |
|----------|-------|-------------|
| `$_SESSION['user_id']` | int | ID del usuario |
| `$_SESSION['user_email']` | string | Email del usuario |
| `$_SESSION['user_rol']` | string | `empresa` / `ministerio` / `admin` |
| `$_SESSION['empresa_id']` | int\|null | ID de empresa (solo rol empresa) |
| `$_SESSION['empresa_nombre']` | string\|null | Nombre de empresa |
| `$_SESSION['logged_in']` | bool | `true` |
| `$_SESSION['login_time']` | int | Timestamp Unix del login |
| `$_SESSION[CSRF_TOKEN_NAME]` | string | Token hex 64 chars |

**Rate Limiting de login:**
- Máximo 5 intentos fallidos por IP en 15 minutos.
- Los intentos se almacenan en la tabla `login_attempts`.
- Después del bloqueo, el sistema espera hasta que expire `bloqueado_hasta`.

### 7.4 Medidas de Seguridad

| Mecanismo | Implementación | Protege contra |
|-----------|---------------|----------------|
| CSRF Tokens | Token hex 64 chars en `$_SESSION`. Validado con `verify_csrf()` en cada POST. | Cross-Site Request Forgery |
| Hashing de contraseñas | `password_hash(PASSWORD_BCRYPT)` al guardar. `password_verify()` al autenticar. | Exposición de contraseñas en BD |
| Prepared Statements | Todas las consultas SQL usan `prepare()` + `execute()`. Sin concatenación de variables. | SQL Injection |
| Escape XSS | `e($str)` = `htmlspecialchars()` en todo output de datos de usuario. | Cross-Site Scripting |
| Rate Limiting | Tabla `login_attempts`: bloqueo tras 5 intentos / 15 min por IP. | Fuerza bruta |
| Guardias de rol | `$auth->requireRole(['empresa'])` al inicio de cada página protegida. | Escalada de privilegios |
| Activación por email | Token único enviado al correo. Sin activar, el acceso está bloqueado. | Registros con emails falsos |
| Cookies de sesión | `httponly=1`, `use_only_cookies=1`, `secure` en producción. | Session hijacking, XSS de cookies |
| Validación de archivos | Lista blanca de tipos MIME. Validación por contenido real, no solo extensión. | Upload de archivos maliciosos |
| Protección de cron | `cron_guard.php` valida header `CRON_SECRET` en scripts de tarea programada. | Ejecución remota no autorizada |
| HTTPS forzado | Variable `FORCE_HTTPS` redirige con soporte `X-Forwarded-Proto` (Render/proxies). | Intercepción MITM |

### 7.5 Sistema de Archivos

Estrategia de almacenamiento dual:

- **Producción (Render):** Cloudinary obligatorio — Render borra el disco en cada redeploy.
- **Desarrollo (local):** Archivos en `public/uploads/[subdirectorio]/`.

La función `upload_image_storage()` detecta automáticamente si Cloudinary está configurado y enruta el upload al destino correcto, devolviendo en ambos casos una URL accesible.

### 7.6 Sistema de Email

| Proveedor | Condición de uso | Configuración |
|-----------|-----------------|---------------|
| Resend API | Prioritario si `RESEND_API_KEY` está definido | Variable `.env` |
| Gmail SMTP | Fallback si Resend no está configurado | Variables `.env` Gmail |

Emails transaccionales implementados:
- Activación de cuenta nueva.
- Recuperación de contraseña (token con expiración).
- Confirmación de cambio de contraseña.
- Notificación de nuevo formulario asignado.
- Envío de credenciales a empresa registrada por ministerio.
- Recordatorios de formularios próximos a vencer (cron).

### 7.7 Tareas Programadas (Cron)

Los scripts de cron se invocan vía HTTP y validan el header `X-Cron-Secret: [CRON_SECRET]`.

| Script | Función |
|--------|---------|
| `cron/recordatorios-formularios.php` | Envía emails a empresas con formularios pendientes próximos a vencer. |
| `cron/limpiar-tokens.php` | Elimina tokens de activación y recuperación expirados de la BD. |
| `cron/limpiar-login-attempts.php` | Elimina intentos de login antiguos de la tabla `login_attempts`. |

---

## 8. Base de Datos

**Nombre de la base:** `parque_industrial`  
**Motor:** MySQL / MariaDB  
**Charset:** `utf8mb4` — `utf8mb4_unicode_ci`  
**Zona horaria:** UTC-3  
**Total de tablas:** 26  

### 8.1 Tablas del Sistema

#### Usuarios y Autenticación

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `usuarios` | Todos los usuarios del sistema | `id`, `email`, `password` (bcrypt), `rol` (empresa/ministerio/admin), `activo`, `email_verificado`, `token_activacion`, `token_recuperacion`, `token_expira` |
| `login_attempts` | Registro de intentos fallidos de login | `id`, `ip`, `email`, `intentos`, `ultimo_intento`, `bloqueado_hasta` |
| `password_reset_requests` | Solicitudes de recuperación de contraseña | `id`, `email`, `token` (hash), `expires_at`, `used`, `created_at` |

#### Empresas y Datos

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `empresas` | Perfil completo de cada empresa | `id`, `usuario_id` (FK), `nombre`, `razon_social`, `cuit` (único), `rubro`, `descripcion`, `ubicacion`, `latitud`, `longitud`, `logo`, `imagen_portada`, `estado`, `verificada`, `visitas` |
| `datos_empresa` | DDJJ periódicas de datos operativos | `id`, `empresa_id` (FK), `periodo` (ej: 2025-Q1), `dotacion_total`, `empleados_*`, `produccion_mensual`, `consumo_energia/agua/gas`, `exporta`, `importa`, `emisiones_co2`, `inversion_anual`, `rango_facturacion`, `estado`, `declaracion_jurada`, `ip_declaracion` |
| `visitas_empresa` | Registro de visitas al perfil público | `id`, `empresa_id` (FK), `ip`, `created_at` |
| `rubros` | Catálogo de rubros industriales | `id`, `nombre`, `color` (hex para gráficos), `activo`, `orden` |
| `ubicaciones` | Zonas y áreas del parque | `id`, `nombre`, `latitud_centro`, `longitud_centro`, `poligono_geojson` |

#### Contenido y Publicaciones

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `publicaciones` | Noticias de empresas y ministerio | `id`, `empresa_id` (FK, null si es del ministerio), `usuario_id` (FK), `tipo`, `titulo`, `slug`, `contenido`, `imagen`, `estado` (borrador/pendiente/aprobado/rechazado), `destacado`, `aprobado_por`, `motivo_rechazo` |
| `archivos_publicacion` | Archivos adjuntos a publicaciones | `id`, `publicacion_id` (FK), `nombre_original`, `nombre_archivo`, `tipo_mime`, `tamano` |
| `publicacion_likes` | "Me gusta" por IP | `id`, `publicacion_id` (FK), `ip`, `created_at` |
| `banners` | Banners del carrusel del home | `id`, `titulo`, `subtitulo`, `imagen`, `url`, `orden`, `activo`, `fecha_inicio`, `fecha_fin` |
| `banners_home` | Banners adicionales del home | `id`, `titulo`, `imagen`, `url`, `activo`, `orden` |
| `configuracion_sitio` | Parámetros globales del sitio | `clave` (PK), `valor` — Almacena: nombre sitio, email, teléfono, dirección, redes sociales, coordenadas mapa, config estadísticas |

#### Comunicación

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `mensajes` | Mensajería bidireccional empresa ↔ ministerio | `id`, `remitente_id` (FK), `destinatario_id` (FK), `empresa_id` (FK), `asunto`, `categoria`, `contenido`, `adjuntos` (JSON), `leido`, `mensaje_padre_id` (FK, para hilos) |
| `notificaciones` | Centro de notificaciones por usuario | `id`, `usuario_id` (FK), `tipo`, `titulo`, `mensaje`, `url`, `datos` (JSON), `leido`, `created_at` |

#### Formularios

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `formularios_config` | Configuración de períodos de DDJJ | `id`, `nombre`, `descripcion`, `campos` (JSON), `activo`, `obligatorio`, `fecha_limite` |
| `respuestas_formulario` | Respuestas al formulario clásico de datos | `id`, `empresa_id` (FK), `formulario_id` (FK), `respuestas` (JSON), `estado` |
| `formularios_dinamicos` | Formularios personalizados del ministerio | `id`, `titulo`, `descripcion`, `activo`, `creado_por` (FK usuarios), `created_at` |
| `formulario_preguntas` | Campos/preguntas de cada formulario dinámico | `id`, `formulario_id` (FK), `pregunta`, `tipo` (texto/número/fecha/archivo/selección), `requerido`, `orden`, `opciones` (JSON para selects) |
| `formulario_envios` | Envíos de formulario a empresas específicas | `id`, `formulario_id` (FK), `empresa_id` (FK), `estado` (pendiente/completado/vencido), `fecha_limite`, `fecha_completado` |
| `formulario_respuestas` | Respuestas individuales por pregunta | `id`, `envio_id` (FK), `pregunta_id` (FK), `respuesta`, `archivo_nombre` |
| `formulario_destinatarios` | Registro histórico de destinatarios | `id`, `formulario_id` (FK), `empresa_id` (FK), `enviado_at` |

#### Sistema

| Tabla | Descripción | Campos clave |
|-------|-------------|--------------|
| `log_actividad` | Auditoría completa de acciones | `id`, `usuario_id` (FK), `empresa_id` (FK), `accion`, `tabla_afectada`, `registro_id`, `datos_anteriores` (JSON), `datos_nuevos` (JSON), `ip`, `user_agent`, `created_at` |
| `empresa_imagenes` | Galería de imágenes por empresa | `id`, `empresa_id` (FK), `url`, `nombre`, `orden`, `created_at` |
| `solicitudes_proyecto` | Solicitudes del formulario público | `id`, `nombre`, `email`, `empresa_proyecto`, `descripcion`, `estado`, `created_at` |

### 8.2 Relaciones entre Entidades

```
usuarios (1) ──────── (1) empresas
empresas (1) ──────── (N) datos_empresa
empresas (1) ──────── (N) publicaciones
empresas (1) ──────── (N) mensajes
empresas (1) ──────── (N) notificaciones
empresas (1) ──────── (N) empresa_imagenes
empresas (1) ──────── (N) visitas_empresa
publicaciones (1) ─── (N) archivos_publicacion
publicaciones (1) ─── (N) publicacion_likes
formularios_dinamicos (1) ── (N) formulario_preguntas
formularios_dinamicos (1) ── (N) formulario_envios
formularios_dinamicos (1) ── (N) formulario_destinatarios
formulario_envios (1) ──────── (N) formulario_respuestas
```

### 8.3 Vistas (Views)

| Vista | Descripción |
|-------|-------------|
| `v_empresas_completas` | Perfil de empresa con % de completitud del perfil calculado. |
| `v_estadisticas_generales` | Estadísticas agregadas del parque: total empresas, empleos, rubros. |

---

## 9. Mapa de Carpetas del Proyecto

```
parque_industrial/
│
├── config/                          # Configuración global del sistema
│   ├── config.php                   # Constantes, sesión, CSRF, timezone, URLs
│   ├── database.php                 # PDO singleton — función getDB()
│   └── ca.pem                       # Certificado SSL para conexión a BD en la nube
│
├── includes/                        # Módulos PHP compartidos por todas las páginas
│   ├── auth.php                     # Clase Auth — login, roles, tokens, rate limiting
│   ├── funciones.php                # Helpers: e(), csrf, flash, redirect, upload, email, formato
│   ├── comunicaciones.php           # Lógica del centro de comunicaciones
│   ├── cron_guard.php               # Validación de CRON_SECRET para tareas programadas
│   ├── header.php                   # HTML head, meta tags, OG tags, navbar pública
│   ├── footer.php                   # Cierre HTML, scripts JS, Bootstrap
│   ├── empresa_layout_header.php    # Header del panel empresa (navbar + sidebar empresa)
│   ├── empresa_layout_footer.php    # Footer del panel empresa
│   ├── ministerio_layout_header.php # Header del panel ministerio (navbar + sidebar admin)
│   ├── ministerio_layout_footer.php # Footer del panel ministerio
│   └── partials/                    # Componentes HTML reutilizables
│       ├── card_empresa.php         # Tarjeta de empresa para listados
│       └── comunicaciones_panel.php # Panel de mensajería embebible
│
├── public/                          # Raíz del servidor web (document root)
│   │
│   ├── index.php                    # Home — carrusel, destacados, estadísticas, noticias
│   ├── empresas.php                 # Directorio de empresas con filtros y paginación
│   ├── empresa.php                  # Perfil público de empresa (?id=N)
│   ├── mapa.php                     # Mapa interactivo Leaflet con marcadores
│   ├── estadisticas.php             # Estadísticas públicas configurables
│   ├── noticias.php                 # Feed de publicaciones aprobadas
│   ├── publicacion.php              # Detalle de publicación (?id=N)
│   ├── nosotros.php                 # Página institucional "El Parque / Nosotros"
│   ├── el-parque.php                # Información complementaria del parque
│   ├── parque.php                   # Página de presentación del parque
│   ├── presentar-proyecto.php       # Formulario público para emprendedores
│   ├── login.php                    # Login + registro de empresa
│   ├── logout.php                   # Cierre de sesión
│   ├── recuperar.php                # Recuperación de contraseña por email
│   ├── activar-cuenta.php           # Activación de cuenta (?token=...)
│   └── sitemap.php                  # Sitemap XML dinámico para SEO
│   │
│   ├── empresa/                     # Panel privado — rol: empresa
│   │   ├── dashboard.php            # Dashboard con resumen y estado de cuenta
│   │   ├── perfil.php               # Edición completa del perfil de empresa
│   │   ├── publicaciones.php        # CRUD de publicaciones propias
│   │   ├── mensajes.php             # Bandeja de mensajes con ministerio
│   │   ├── mensajes_api.php         # API para operaciones de mensajería
│   │   ├── comunicaciones.php       # Centro de comunicaciones (vista hilos)
│   │   ├── formularios.php          # Historial de DDJJ por período
│   │   ├── formulario_presentacion.php # Formulario extenso de datos críticos
│   │   ├── formulario_dinamico.php  # Responder formulario dinámico (?id=N)
│   │   ├── notificaciones.php       # Centro de notificaciones
│   │   ├── galeria_api.php          # API para gestión de galería de imágenes
│   │   └── cambiar-contrasena.php   # Cambio de contraseña
│   │
│   ├── ministerio/                  # Panel administrativo — rol: ministerio / admin
│   │   ├── dashboard.php            # Dashboard con métricas del parque
│   │   │
│   │   ├── empresas.php             # Listado de empresas con filtros
│   │   ├── empresa-detalle.php      # Vista completa de empresa (?id=N)
│   │   ├── empresa-editar.php       # Edición de datos de empresa (?id=N)
│   │   ├── nueva-empresa.php        # Alta de empresa desde el ministerio
│   │   │
│   │   ├── publicaciones.php        # Moderación de publicaciones
│   │   ├── comunicados.php          # Comunicados oficiales del ministerio
│   │   ├── comunicaciones.php       # Centro de comunicaciones ministerio
│   │   ├── mensajes-entrada.php     # Bandeja de mensajes entrantes
│   │   ├── plantillas.php           # Plantillas de respuesta
│   │   ├── banners.php              # Gestión del carrusel del home
│   │   ├── nosotros-editar.php      # Editor de la página "El Parque"
│   │   ├── solicitudes-proyecto.php # Gestión de solicitudes de radicación
│   │   │
│   │   ├── formularios.php          # Revisión de DDJJ de empresas
│   │   ├── formularios-dinamicos.php # Listado de formularios dinámicos
│   │   ├── formulario-nuevo.php     # Crear nuevo formulario dinámico
│   │   ├── formulario-editar.php    # Editar formulario (?id=N)
│   │   ├── formulario-gestion.php   # Gestión de respuestas de formulario
│   │   ├── formulario-imprimir.php  # Vista de impresión/PDF de respuestas
│   │   │
│   │   ├── graficos.php             # Gráficos estadísticos con Chart.js
│   │   ├── estadisticas-config.php  # Configurar indicadores públicos
│   │   ├── exportar.php             # Exportar datos CSV/Excel
│   │   ├── reporte.php              # Generación de reportes
│   │   ├── notificaciones.php       # Centro de notificaciones del ministerio
│   │   │
│   │   └── cron/                    # Tareas programadas (HTTP + CRON_SECRET)
│   │       ├── recordatorios-formularios.php  # Emails recordatorio de formularios
│   │       ├── limpiar-tokens.php             # Limpia tokens expirados
│   │       └── limpiar-login-attempts.php     # Limpia intentos de login viejos
│   │
│   ├── api/                         # Endpoints REST del sistema
│   │   └── comunicaciones/          # API de mensajería
│   │       ├── _base.php            # Bootstrap común: auth, headers JSON
│   │       ├── listar.php           # GET — listar conversaciones
│   │       ├── conversacion.php     # GET — hilo de mensajes (?id=N)
│   │       ├── enviar.php           # POST — enviar mensaje
│   │       ├── borrador.php         # POST — guardar borrador
│   │       ├── marcar.php           # POST — marcar como leído/no leído
│   │       ├── adjuntar.php         # POST — subir archivo adjunto
│   │       ├── badge.php            # GET — conteo de no leídos (badge)
│   │       └── plantillas.php       # GET — listar plantillas de respuesta
│   │
│   ├── css/                         # Hojas de estilo
│   │   ├── styles.css               # Estilos del sitio público
│   │   ├── empresa-app.css          # Estilos del panel empresa
│   │   └── empresa-inbox.css        # Estilos de la bandeja de mensajes
│   │
│   ├── js/                          # Scripts JavaScript
│   │   ├── main.js                  # Animaciones, alerts, tooltips, confirmaciones, preview imagen
│   │   └── parque-leaflet.js        # Lógica del mapa Leaflet — marcadores, filtros, popups
│   │
│   ├── img/                         # Imágenes estáticas del sistema
│   └── uploads/                     # Archivos subidos por usuarios (desarrollo local)
│
├── database/                        # Esquema y migraciones SQL
│   ├── schema_completo.sql          # Esquema completo de instalación única
│   ├── 015_mensajes_categoria.sql   # Migración: categorías de mensajes
│   ├── 016_centro_comunicaciones.sql # Migración: centro de comunicaciones
│   ├── 017_migrar_mensajes_a_v2.sql # Migración: reestructura de mensajería
│   └── 018_plantillas_respuesta.sql  # Migración: plantillas de respuesta
│
├── assets/
│   └── css/
│       └── estilos.css              # Estilos globales alternativos
│
├── logs/                            # Logs de errores PHP (generados en runtime)
│   └── error.log
│
├── .env                             # Variables de entorno (NO subir a Git)
├── .env.example                     # Plantilla de variables de entorno
├── .gitignore                       # Exclusiones de Git
├── Dockerfile                       # Imagen Docker para producción
├── CLAUDE.md                        # Guía para asistentes de IA
├── README.md                        # Documentación del proyecto
└── parque_industrial.sql            # Dump SQL completo de la BD (backup)
```

---

## 10. Mapa de Enlaces del Proyecto

### 10.1 Sitio Público (Sin Autenticación)

| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Home** | `/public/index.php` | Empresas, Mapa, Estadísticas, Noticias, El Parque, Login, perfil de empresas destacadas, detalle de publicaciones |
| **Empresas** | `/public/empresas.php` | Perfil de empresa (`?id=N`), Home |
| **Perfil Empresa** | `/public/empresa.php?id=N` | Noticias de esa empresa, Mapa, Empresas, publicaciones vinculadas |
| **Mapa** | `/public/mapa.php` | Perfil de empresa (popup con link), Home |
| **Estadísticas** | `/public/estadisticas.php` | Home, Empresas |
| **Noticias** | `/public/noticias.php` | Detalle publicación (`?id=N`), perfil de empresa autora |
| **Detalle Publicación** | `/public/publicacion.php?id=N` | Noticias, perfil de empresa autora |
| **El Parque / Nosotros** | `/public/nosotros.php` | Home, Mapa, Presentar Proyecto |
| **Parque** | `/public/parque.php` | Home, El Parque |
| **Presentar Proyecto** | `/public/presentar-proyecto.php` | Home (tras envío) |
| **Login** | `/public/login.php` | Dashboard Empresa (tras login empresa), Dashboard Ministerio (tras login ministerio), Recuperar, Activar |
| **Recuperar Contraseña** | `/public/recuperar.php` | Login (tras envío) |
| **Activar Cuenta** | `/public/activar-cuenta.php?token=...` | Login (tras activación exitosa) |
| **Logout** | `/public/logout.php` | Login (siempre) |
| **Sitemap** | `/public/sitemap.php` | — (consumido por buscadores) |

---

### 10.2 Panel Empresa (Requiere rol: empresa)

| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Dashboard** | `/public/empresa/dashboard.php` | Perfil, Publicaciones, Mensajes, Formularios, Notificaciones, Formulario Dinámico pendiente |
| **Mi Perfil** | `/public/empresa/perfil.php` | Dashboard (tras guardar) |
| **Publicaciones** | `/public/empresa/publicaciones.php` | Dashboard, detalle de publicación pública |
| **Mensajes** | `/public/empresa/mensajes.php` | Dashboard, Comunicaciones |
| **Comunicaciones** | `/public/empresa/comunicaciones.php` | Mensajes, Dashboard |
| **Formularios DDJJ** | `/public/empresa/formularios.php` | Declaración de Datos (`formulario_presentacion.php`), Dashboard |
| **Declaración de Datos** | `/public/empresa/formulario_presentacion.php` | Formularios (tras envío) |
| **Formulario Dinámico** | `/public/empresa/formulario_dinamico.php?id=N` | Dashboard (tras completar) |
| **Notificaciones** | `/public/empresa/notificaciones.php` | Página vinculada en cada notificación |
| **Cambiar Contraseña** | `/public/empresa/cambiar-contrasena.php` | Dashboard |

**APIs consumidas por el panel empresa:**
- `GET /public/empresa/mensajes_api.php` — Operaciones de mensajes
- `POST /public/empresa/galeria_api.php` — Upload y listado de galería
- `GET/POST /public/api/comunicaciones/*` — Centro de comunicaciones

---

### 10.3 Panel Ministerio (Requiere rol: ministerio o admin)

#### Empresas
| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Dashboard** | `/public/ministerio/dashboard.php` | Todas las secciones del ministerio |
| **Listado Empresas** | `/public/ministerio/empresas.php` | Detalle, Editar, Nueva empresa, Dashboard |
| **Detalle Empresa** | `/public/ministerio/empresa-detalle.php?id=N` | Editar empresa, Formularios de esa empresa, Mensajes, Listado empresas |
| **Editar Empresa** | `/public/ministerio/empresa-editar.php?id=N` | Detalle empresa (tras guardar) |
| **Nueva Empresa** | `/public/ministerio/nueva-empresa.php` | Listado empresas (tras crear) |

#### Contenido
| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Publicaciones** | `/public/ministerio/publicaciones.php` | Detalle publicación pública, Dashboard |
| **Comunicados** | `/public/ministerio/comunicados.php` | Dashboard |
| **Comunicaciones** | `/public/ministerio/comunicaciones.php` | Mensajes entrada, Plantillas, Dashboard |
| **Mensajes Entrada** | `/public/ministerio/mensajes-entrada.php` | Comunicaciones, Dashboard |
| **Plantillas** | `/public/ministerio/plantillas.php` | Comunicaciones |
| **Banners** | `/public/ministerio/banners.php` | Dashboard, Home público |
| **Editor El Parque** | `/public/ministerio/nosotros-editar.php` | Dashboard, nosotros.php público |
| **Solicitudes Proyecto** | `/public/ministerio/solicitudes-proyecto.php` | Dashboard |

#### Formularios
| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Formularios DDJJ** | `/public/ministerio/formularios.php` | Detalle de empresa, Dashboard |
| **Formularios Dinámicos** | `/public/ministerio/formularios-dinamicos.php` | Crear, Editar, Gestión, Dashboard |
| **Crear Formulario** | `/public/ministerio/formulario-nuevo.php` | Listado formularios (tras crear) |
| **Editar Formulario** | `/public/ministerio/formulario-editar.php?id=N` | Listado formularios (tras guardar) |
| **Gestión Formulario** | `/public/ministerio/formulario-gestion.php?id=N` | Respuestas, Seguimiento, Imprimir |
| **Imprimir Formulario** | `/public/ministerio/formulario-imprimir.php?id=N&empresa_id=M` | — (vista standalone para imprimir) |

#### Análisis
| Página | URL | Enlaza hacia |
|--------|-----|--------------|
| **Gráficos** | `/public/ministerio/graficos.php` | Dashboard |
| **Config Estadísticas** | `/public/ministerio/estadisticas-config.php` | Estadísticas pública, Dashboard |
| **Exportar Datos** | `/public/ministerio/exportar.php` | Dashboard |
| **Reporte** | `/public/ministerio/reporte.php` | Dashboard |
| **Notificaciones** | `/public/ministerio/notificaciones.php` | Página vinculada en cada notificación |

---

### 10.4 API REST de Comunicaciones

Base URL: `/public/api/comunicaciones/`  
Autenticación: sesión PHP activa (cualquier rol autenticado).

| Método | Endpoint | Función |
|--------|----------|---------|
| `GET` | `listar.php` | Retorna lista de conversaciones del usuario activo |
| `GET` | `conversacion.php?id=N` | Retorna hilo completo de mensajes de una conversación |
| `POST` | `enviar.php` | Envía un nuevo mensaje |
| `POST` | `borrador.php` | Guarda un mensaje como borrador |
| `POST` | `marcar.php` | Marca mensaje como leído o no leído |
| `POST` | `adjuntar.php` | Sube un archivo adjunto a un mensaje |
| `GET` | `badge.php` | Retorna el conteo de mensajes no leídos (para el badge del menú) |
| `GET` | `plantillas.php` | Retorna las plantillas de respuesta disponibles |

---

### 10.5 Diagrama de Flujo de Navegación por Rol

```
VISITANTE
    └── index.php
        ├── empresas.php ──► empresa.php?id=N
        ├── mapa.php ──────► empresa.php?id=N
        ├── estadisticas.php
        ├── noticias.php ──► publicacion.php?id=N
        ├── nosotros.php
        ├── presentar-proyecto.php
        └── login.php
            ├── (empresa) ──► empresa/dashboard.php
            └── (ministerio) ──► ministerio/dashboard.php

EMPRESA (autenticado)
    └── empresa/dashboard.php
        ├── empresa/perfil.php
        ├── empresa/publicaciones.php
        ├── empresa/mensajes.php ◄──► empresa/comunicaciones.php
        ├── empresa/formularios.php ──► empresa/formulario_presentacion.php
        ├── empresa/formulario_dinamico.php?id=N
        ├── empresa/notificaciones.php
        └── empresa/cambiar-contrasena.php

MINISTERIO (autenticado)
    └── ministerio/dashboard.php
        ├── ministerio/empresas.php
        │   ├── ministerio/empresa-detalle.php?id=N
        │   ├── ministerio/empresa-editar.php?id=N
        │   └── ministerio/nueva-empresa.php
        ├── ministerio/publicaciones.php
        ├── ministerio/comunicaciones.php ◄──► ministerio/mensajes-entrada.php
        ├── ministerio/formularios.php
        ├── ministerio/formularios-dinamicos.php
        │   ├── ministerio/formulario-nuevo.php
        │   ├── ministerio/formulario-editar.php?id=N
        │   └── ministerio/formulario-gestion.php?id=N
        │       └── ministerio/formulario-imprimir.php
        ├── ministerio/graficos.php
        ├── ministerio/exportar.php
        ├── ministerio/banners.php
        ├── ministerio/solicitudes-proyecto.php
        └── ministerio/notificaciones.php
```

---

*Documento elaborado por Francisco Barrionuevo y David Nicolao*  
*Pasantía — 3er año — 2025/2026 | Versión 2.0 | Mayo 2026*
