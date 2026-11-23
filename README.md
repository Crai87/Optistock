# OptiStock: inventario para una clínica optométrica

Aplicación web para controlar el inventario de una clínica optométrica: armazones, equipos clínicos y mobiliario. Incluye usuarios con permisos, garantías y reportes en PDF.

> Proyecto académico desarrollado en la Universidad Autónoma de la Laguna (UAL). El repositorio no contiene datos reales de la clínica.

<!-- Agrega aquí una captura de pantalla (con datos de ejemplo):
![Inventario](docs/inventario.png)
-->

## Funcionalidades

- **Inventario de armazones, equipos clínicos y mobiliario**, con altas, ediciones y marcas.
- **Inventario muerto automático:** los armazones con 365 días o más en el sistema se reclasifican solos.
- **Garantías:** pantalla de gestión con folios y estados, y envío de reportes a proveedores.
- **Proveedores y contactos** asociados a cada producto.
- **Reportes en PDF** según los filtros aplicados: general, por marca, por proveedor, por antigüedad, críticos, materiales y garantías.
- **Usuarios y permisos:** rol administrador y rol invitado. El administrador crea usuarios y asigna 12 permisos (ver, agregar, editar y generar reporte, por módulo).
- **Dashboard** con resumen del inventario.
- **Historial de movimientos** del inventario.

## Tecnologías

| Área | Herramientas |
|---|---|
| Backend | PHP 8 (PDO con consultas preparadas) |
| Base de datos | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript, Bootstrap 5, Bootstrap Icons |
| Reportes | FPDF |
| Entorno local | XAMPP |

## Estructura

```
optometria-inventario/
├── config/      # Conexión a la base de datos y configuración general
├── database/
│   ├── migrations/   # Script de instalación (tablas y datos iniciales)
│   └── seeds/        # Scripts de datos y permisos
├── libs/        # FPDF
├── public/      # Punto de entrada, estilos, scripts y reportes
└── src/views/   # Pantallas de la aplicación
```

## Instalación local

**Requisitos:** XAMPP (Apache, PHP 8 y MySQL) o un entorno equivalente.

1. Clona el repositorio dentro de `htdocs` y **nombra la carpeta `optometria-inventario`**, porque la aplicación usa esa ruta:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/Crai87/Optistock.git optometria-inventario
   ```
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. En phpMyAdmin crea una base de datos llamada `optometria_bd` con cotejamiento `utf8mb4`.
4. Importa en esa base el script `database/migrations/optistock_instalacion`.
5. Revisa los datos de conexión en `config/database.php` (por defecto: `localhost`, usuario `root`, sin contraseña) y la URL en `config/app.php`.
6. Abre en el navegador: `http://localhost/optometria-inventario/public/index.php`

## Acceso de prueba

| Usuario | Contraseña |
|---|---|
| `admin` | `12345678` |

Estas credenciales son solo para pruebas locales. Cámbialas si vas a usar el sistema en un entorno real.

## Notas de seguridad

- Contraseñas guardadas con `password_hash()` y verificadas con `password_verify()`.
- Consultas con sentencias preparadas (PDO).
- En producción, cambia `DB_USER` y `DB_PASS` en `config/database.php` y no uses `root` sin contraseña.
- Las carpetas `public/respaldos/` y `public/assets/img/inventario/` están excluidas del repositorio para no subir datos reales.

## Pendientes

- Editar y borrar armazones desde la pantalla de inventario.
- Eliminar el botón redundante de "Ver reportes".
- Poder volver a ver los reportes generados con "Enviar a proveedor".

## Autor

**José Angel Santoyo Moreno**
Estudiante de Ingeniería en Sistemas Computacionales, UAL, Torreón.
[joseangelsantoyo6@gmail.com](mailto:joseangelsantoyo6@gmail.com)
Inventario de Optometría 
