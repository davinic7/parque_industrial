# Parque Industrial — Catamarca

Aplicación PHP (sitio público, panel empresa y panel ministerio).

## Requisitos

- PHP 8.x con extensiones `pdo_mysql`, `curl`, `json`
- MySQL o MariaDB

## Instalación local

1. Clonar el repositorio.
2. Copiar entorno: `cp .env.example .env` (en Windows: copiar y renombrar a `.env`).
3. Ajustar `.env`: base de datos, `SITE_URL`, y si usás MySQL con SSL (Aiven, etc.) `DB_SSL_CA=config/ca.pem`.
4. Crear la base `parque_industrial` e importar los SQL en `database/` (empezar por `parque_industrial.sql` y luego los parches numerados si aplica).
5. Servir la carpeta **`public`** como raíz web (DocumentRoot). En desarrollo, con PHP:

   ```bash
   php -S localhost:8080 -t public
   ```

   Abrir `http://localhost:8080` y ajustar `SITE_URL` en `.env` si usás otro puerto o ruta.

## Cloudinary

Los banners del inicio usan Cloudinary. Definí `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY` y `CLOUDINARY_API_SECRET` en `.env`.

## Despliegue (p. ej. Render)

- **Root directory**: raíz del repo (donde está `config/`).
- **Document root / public**: `public`.
- Variables de entorno: las mismas que en `.env`, con `APP_ENV=production`, `APP_DEBUG=0` y `SITE_URL` con la URL pública del servicio.
