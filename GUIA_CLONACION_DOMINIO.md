# 🚀 Guía Maestra de Clonación y Despliegue en un Nuevo Dominio

Esta guía te explica paso a paso cómo clonar **el 100% de esta plataforma** (código, funcionalidades, catálogo, base de datos y paneles de administración/barbero) en un nuevo dominio en **Hostinger**, **cPanel** o cualquier servidor web.

---

## 📦 Paso 1: Descargar / Clonar el Código Fuente

Tienes el proyecto completo listo en tu repositorio de GitHub:
- `https://github.com/J03l-code/kortzen.git` o `https://github.com/J03l-code/mausbarber.git`

Puedes:
1. **Opción A (Git en Hostinger):** En tu hPanel ve a **Avanzado > Git**, coloca la URL del repositorio y despliega en la carpeta `public_html` del nuevo dominio.
2. **Opción B (ZIP/File Manager):** Descarga el ZIP del repositorio y extrae todo su contenido dentro de la carpeta `public_html` de tu nuevo dominio.

---

## 🗄️ Paso 2: Crear la Base de Datos en tu Nuevo Hosting

1. Ingresa al panel de control de tu nuevo hosting (ej: **Hostinger hPanel**).
2. Ve a **Bases de Datos > Bases de datos MySQL**.
3. Crea una nueva base de datos y un usuario:
   - **Nombre de base de datos:** ej. `u123456789_barberia`
   - **Usuario MySQL:** ej. `u123456789_admin`
   - **Contraseña:** ej. `MiPasswordSeguro2026!`
4. Anota estos 3 datos.

---

## ⚙️ Paso 3: Conectar la Base de Datos

Tienes dos formas sencillas de configurar las credenciales:

### Opción A (Recomendada con archivo `.env`):
Crea un archivo llamado `.env` en la raíz de tu proyecto (o renombra el `.env.example`) y coloca:
```env
DB_HOST=localhost
DB_NAME=u123456789_barberia
DB_USER=u123456789_admin
DB_PASS=MiPasswordSeguro2026!
```

### Opción B (Directo en `config.php`):
Abre `config.php` y edita las líneas 27-31:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_barberia');
define('DB_USER', 'u123456789_admin');
define('DB_PASS', 'MiPasswordSeguro2026!');
```

> **Nota:** El sistema detecta automáticamente el nuevo dominio (`https://tu-nuevo-dominio.com`) sin que tengas que cambiar rutas fijas en el código.

---

## ⚡ Paso 4: Instalar y Poblar la Base de Datos (1-Clic)

El proyecto incluye dos métodos automáticos para crear las 24 tablas y todos los datos iniciales:

### Método 1 (El más fácil - Instalador Web en 1 segundo):
1. Abre tu navegador y visita:  
   `https://tu-nuevo-dominio.com/setup_database.php`
2. El instalador creará automáticamente todas las tablas, relaciones, catálogo de servicios, categorías, inventario, horarios y cuentas de acceso.
3. ¡Listo! Verás el mensaje verde de éxito.

### Método 2 (Importación manual desde phpMyAdmin):
1. Entra a **phpMyAdmin** en tu hosting.
2. Selecciona tu base de datos nueva.
3. Haz clic en la pestaña **Importar**.
4. Selecciona el archivo: `sql/master_clone_database.sql`.
5. Haz clic en **Importar / Continuar**.

---

## 🔑 Paso 5: Credenciales de Acceso por Defecto

Una vez instalada la base de datos, las credenciales maestras listas para usar son:

| Rol | Correo Electrónico | Contraseña | URL de Acceso |
|---|---|---|---|
| **Administrador General** | `admin@kortzen.com` | `Admin2026!` | `https://tu-dominio.com/login.php` |
| **Barbero Master** | `mateo@kortzen.com` | `Barbero2026!` | `https://tu-dominio.com/login.php` |

*(Una vez que ingreses al panel, puedes cambiar los nombres, correos y contraseñas desde el módulo de **Usuarios**).*

---

## 📱 Paso 6: PWA y Funcionalidades Móviles

La PWA (Aplicación Web Progresiva) funciona de forma 100% nativa en el nuevo dominio:
- **Panel PWA Administrador:** `https://tu-dominio.com/pwa-admin.php`
- **Panel PWA Barbero:** `https://tu-dominio.com/barber-dashboard.php`
- **Panel PWA Cliente:** `https://tu-dominio.com/cliente-dashboard.php`

---

## ⏱️ Paso 7: Tareas Programadas / Cron Jobs (Opcional pero recomendado)

Para activar el envío de recordatorios automáticos de citas por WebPush o email:
En Hostinger ve a **Avanzado > Tareas Programadas (Cron Jobs)** y añade:
- **Frecuencia:** Cada 15 minutos (`*/15 * * * *`)
- **Comando:**
  ```bash
  curl -s "https://tu-dominio.com/api/cron_recordatorios_2h.php?secret=KortzenCron2026_9b8a7c6e5f4d" >/dev/null 2>&1
  ```
