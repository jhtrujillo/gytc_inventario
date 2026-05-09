# G&TC - Control e Inventario de Grúas y Equipos

Una aplicación web moderna, ágil y de alta fidelidad diseñada específicamente para la gestión de inventario, inspecciones preoperacionales y mantenimiento de maquinaria pesada y grúas de G&TC.

## 🚀 Características Clave

- **Dashboard de Estadísticas en Tiempo Real:** Visualización compacta e inteligente de grúas registradas, reportes preoperacionales y estado operacional del inventario en una sola fila horizontal responsiva.
- **Gestión Integral de Grúas (Fichas Técnicas):**
  - Registro de marca, línea, modelo, capacidades, números de serie y tipos de combustible.
  - Subida dinámica y previsualización de imágenes en tiempo real para las hojas de vida de los equipos.
  - Configuración flexible y personalizada de actividades y eslingas de inspección por grúa.
- **Llenado de Preoperacionales Inteligente:** Formulario interactivo, optimizado y auto-guardado para reportes de condición diaria.
- **Hojas de Vida e Impresión Profesional:** Renderizado en alta definición tipo PDF (Pixel-Perfect) listo para imprimir en papel u hojas de vida oficiales del cliente.
- **Administración de Operadores y Personal:**
  - Panel centralizado de gestión para crear, editar y supervisar operadores.
  - Control proactivo y alertas visuales sobre el vencimiento de licencias de conducción/operación.
  - Control de estatus activo/inactivo integrado directamente con la seguridad de inicio de sesión.

## 🛠️ Stack Tecnológico

- **Backend:** PHP 8.1+ con arquitectura limpia, modular y segura.
- **Base de Datos:** PDO MySQL con sistema integrado de autocuración de esquemas (auto-instalación de tablas y columnas faltantes de forma dinámica).
- **Frontend:** HTML5, CSS3 nativo (sin dependencias pesadas de Tailwind para máxima velocidad y adaptabilidad) con tipografías premium (`Outfit` de Google Fonts).
- **Interactividad:** Vanilla Javascript moderno y reactivo para previsualización e interacciones fluidas.

## 📦 Instalación y Configuración Local

1. Clona este repositorio dentro de tu directorio de servidores locales (MAMP, XAMPP, Laragon, etc.):
   ```bash
   git clone https://github.com/jhtrujillo/gytc_inventario.git
   ```
2. Asegúrate de tener activo tu servidor de base de datos MySQL.
3. Configura tus credenciales de acceso a base de datos en el archivo [config.php](config.php).
4. El sistema creará de forma automática tanto la base de datos `gytc_inventario` como todas las tablas y columnas requeridas en tu primer inicio.
5. Inicia sesión con las credenciales demo predeterminadas:
   - **Administrador:** `admin` / `admin123`
   - **Operador:** `operador` / `operador123`

---
Diseñado y desarrollado para optimizar los procesos operativos y de seguridad industrial en **G&TC**.
