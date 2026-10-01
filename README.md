# Simple Stock (Inventario Simple)

Simple Stock (Inventario Simple) es un sistema web desarrollado con PHP, MySQL y Bootstrap, que cubre una serie de requerimientos básicos para llevar el control del inventario de una empresa o negocio. Esta es una solución sencilla para que los propietarios de pequeñas empresas gestionen sus existencias de manera sistemática, y de esa manera poder reemplazar el uso de hojas de cálculo para gestionar su inventario.

*Nota: Este repositorio incluye una suite de pruebas automatizadas (PHPUnit) y configuración para análisis de calidad de código (SonarQube).*

---

##  Instalación en Windows (Servidor Local)

1. **Descargar** los archivos fuentes del sistema.
2. **Copiar y descomprimir** el archivo en la carpeta `C:\xampp\htdocs`. Al final tendrás la carpeta del proyecto (por ejemplo `Control` o `simple_stock`), a la cual podrás acceder desde el navegador.
3. **Crear una base de datos** usando phpMyAdmin accediendo a la URL: `http://localhost/phpmyadmin/`. Crea una base de datos llamada `simple_stock`.
4. **Importar las tablas** de la base de datos. Para ello busca el archivo `simple_stock.sql` (en la raíz o en la carpeta `BD/`) y procede a hacer la importación desde phpMyAdmin.
5. **Configurar la conexión** editando el archivo de configuración que se encuentra en la ruta: `config/db.php`.
6. **Vista web:** Accede desde el navegador (ej: `http://localhost/Control/` o `http://localhost/simple_stock/`).
7. **Datos de acceso por defecto:** 
   * Usuario: `admin` 
   * Contraseña: `admin`

*Para más información sobre el proyecto original visita: http://obedalvarado.pw/blog/sistema-inventario-simple-php/*

---

##  Pruebas Automatizadas (PHPUnit)

Para ejecutar los casos de prueba y asegurar el correcto funcionamiento del sistema y la base de datos, necesitas configurar el entorno CLI:

### 1. Requisitos y Dependencias (Composer)
Este proyecto utiliza Composer para manejar las dependencias, Abre tu terminal en la carpeta principal del proyecto y ejecuta el siguiente comando para descargar PHPUnit:
```bash
composer install
```

NOTA: En caso de que ocurran errores sobre las pruebas, quedaran datos basura en la base de datos, en este caso Formatear la Base de datos de phpmyadmin para volver a ejecutar (borrar la base de datos e importar el script de sql).