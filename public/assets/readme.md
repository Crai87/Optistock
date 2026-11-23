PHP (ejecutado en entorno local con XAMPP)

Bootstrap 5 (instalado de forma local, sin CDN)

Bootstrap Icons (instalado de forma local)

HTML5

Git (control de versiones)

Cambios Implementados
1. Migración de Tailwind a Bootstrap

Se reemplazó completamente Tailwind por Bootstrap 5, debido a que el equipo de desarrollo trabajará con este framework.

Se utilizaron componentes oficiales de Bootstrap:

Navbar

Cards

Table

Dropdown

Buttons

Responsive utilities

2. Instalación Local de Bootstrap

Se eliminó el uso de CDN.

Bootstrap y Bootstrap Icons fueron descargados en versión compilada y almacenados dentro del proyecto para garantizar:

Funcionamiento sin conexión a internet

Estabilidad en entorno local

Cumplimiento de buenas prácticas académicas
3. Implementación de Logo Oficial

Se reemplazó el texto “UAL” por la imagen oficial proporcionada.

El logo:

Está almacenado localmente en la carpeta /img

Es responsive

Mantiene proporciones mediante max-height

4. Implementación de Dropdown de Usuario

Se reemplazó el texto simple "Usuario" por un componente dropdown oficial de Bootstrap.

Incluye:

Icono de perfil

Perfil

Configuración

Cerrar sesión

El componente utiliza bootstrap.bundle.min.js para su funcionamiento correcto.

5. Cambio de “Acciones” a “Stock”

La columna “Acciones” fue reemplazada por “Stock”, ya que el sistema manejará cantidades totales disponibles por armazón.

La lógica aún no se implementa debido a que el proyecto se encuentra en fase estructural.

6. Cambio de Botón “Exportar” a “Generar Reporte”

El botón fue renombrado a “Generar Reporte” con el objetivo de:

Generar reportes por rango de fecha

Este mes

Mes anterior

Esta semana

La funcionalidad será implementada cuando el cliente entregue el formato actual de sus reportes manuales.

Estado Actual del Proyecto

Interfaz completa en Bootstrap

Componentes funcionales visualmente

Funcionamiento completamente local (sin dependencia de internet)

A la espera de definición formal del modelo de reporte