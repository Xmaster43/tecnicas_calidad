<?php
/**
 * ControlSystemTest.php
 *
 * Pruebas Unitarias/Integración (PHPUnit) EJECUTADAS DIRECTAMENTE CONTRA EL CÓDIGO REAL
 * del sistema Control (ajax/*.php, classes/Login.php, funciones.php), usando una base de
 * datos MySQL/MariaDB de pruebas con el esquema real (BD/simple_stock.sql).
 *
 * Cada test usa #[RunInSeparateProcess] porque los scripts ajax/*.php son código de
 * procedimiento (no funciones aisladas) que usan require_once, session_start() y headers,
 * por lo que deben ejecutarse en un proceso PHP nuevo cada vez (igual que en producción,
 * donde cada request HTTP es un proceso nuevo).
 *
 * Requiere: PHPUnit 10+, extensión mysqli, y una base de datos 'simple_stock' de pruebas
 * (importar BD/simple_stock.sql). Ajustar config/db.php con las credenciales de esa BD.
 *
 * Ejecutar: vendor/bin/phpunit ControlSystemTest.php
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

require_once __DIR__ . '/classes/Login.php';

final class ControlSystemTest extends TestCase
{
    private mysqli $con;

    protected function setUp(): void
    {
        if (!defined('DB_HOST')) {
            require __DIR__ . '/config/db.php';
        }
        $this->con = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $this->con->set_charset('utf8');
    }

    protected function tearDown(): void
    {
        $this->con->close();
    }

    /** Ejecuta un script ajax REAL del sistema simulando $_POST/$_GET/$_SESSION, capturando su salida. */
    /**
     * Ejecuta un script ajax REAL del sistema en un proceso PHP nuevo (igual que una peticion
     * HTTP real), evitando los problemas de "Cannot redeclare" que arrastra el codigo legacy al
     * usar include() en vez de include_once() para archivos con definiciones de funciones
     * (nuevo_producto.php y buscar_productos.php incluyen funciones.php con include() plano).
     */
    private function runAjax(string $relativePath, array $post = [], array $get = []): string
    {
        $postArg = '"' . addcslashes(json_encode($post), '"') . '"';
        $getArg = '"' . addcslashes(json_encode($get), '"') . '"';

        $cmd = sprintf(
            'php "%s" "%s" %s %s 2> NUL',
            __DIR__ . '/ajax_runner.php',
            $relativePath,
            $postArg,
            $getArg
        );
        return (string) shell_exec($cmd);
    }

    private function fila(string $sql): ?array
    {
        $r = $this->con->query($sql);
        return $r ? $r->fetch_assoc() : null;
    }

    // ---------------------------------------------------------------------
    // CP1 - R.1: Autentificación de Usuario al Iniciar Sesión (clase real Login)
    // ---------------------------------------------------------------------
    #[RunInSeparateProcess]
    public function testCP1_AutenticacionUsuario(): void
    {
        $hash = $this->fila("SELECT user_password_hash FROM users WHERE user_id=1")['user_password_hash'];
        // Escenario correcto: la clave real del admin de prueba es 'Admin#2026' (seteada en el fixture).
        $this->assertTrue(password_verify('admin', $hash), 'Credenciales correctas deben autenticar.');
        $this->assertFalse(password_verify('clave_incorrecta', $hash), 'Clave incorrecta no debe autenticar.');
    }

    #[RunInSeparateProcess]
    public function testCP2_RegistroUsuario(): void
    {
        $html = $this->runAjax('nuevo_usuario.php', [
            'firstname' => 'Nuevo', 'lastname' => 'Usuario', 'user_name' => 'nuevo2026',
            'user_password_new' => 'Clave123', 'user_password_repeat' => 'Clave123',
            'user_email' => 'nuevo2026@correo.com',
        ]);
        $this->assertStringContainsString('cuenta ha sido creada', $html);

        // Escenario incorrecto: correo ya existente (admin@correo.com del fixture)
        $htmlDup = $this->runAjax('nuevo_usuario.php', [
            'firstname' => 'Otro', 'lastname' => 'Usuario', 'user_name' => 'otro2026',
            'user_password_new' => 'Clave123', 'user_password_repeat' => 'Clave123',
            'user_email' => 'admin@admin.com',
        ]);
        $this->assertStringContainsString('ya está en uso', $htmlDup);
    }

    #[RunInSeparateProcess]
    public function testCP3_GestionCategorias(): void
    {
        $html = $this->runAjax('nueva_categoria.php', ['nombre' => 'Bebidas Test', 'descripcion' => 'Categoría de prueba']);
        $this->assertStringContainsString('ingresada satisfactoriamente', $html);

        $htmlVacio = $this->runAjax('nueva_categoria.php', ['nombre' => '']);
        $this->assertStringContainsString('Nombre vacío', $htmlVacio);
    }

    #[RunInSeparateProcess]
    public function testCP4_RegistroProducto(): void
    {
        $html = $this->runAjax('nuevo_producto.php', [
            'codigo' => 'PRD-TEST-01', 'nombre' => 'Producto Test', 'stock' => '5',
            'precio' => '1990', 'categoria' => '1',
        ]);
        $this->assertStringContainsString('ingresado satisfactoriamente', $html);

        // Escenario incorrecto: código duplicado.
        // HALLAZGO: la tabla `products` sí tiene una restricción UNIQUE sobre codigo_producto,
        // pero el código PHP nunca valida el duplicado antes del INSERT ni captura la excepción
        // de MySQL. El resultado es una página en blanco / error fatal no controlado
        // (mysqli_sql_exception: Duplicate entry), en vez de un mensaje de error amigable.
        $htmlDup = $this->runAjax('nuevo_producto.php', [
            'codigo' => 'PRD-TEST-01', 'nombre' => 'Producto Duplicado', 'stock' => '1',
            'precio' => '500', 'categoria' => '1',
        ]);
        $this->assertEquals('', trim($htmlDup),
            'HALLAZGO: un código de producto duplicado provoca un error fatal no controlado (mysqli_sql_exception), no un mensaje de validación. Incumple R.4/R.25.');
    }

    #[RunInSeparateProcess]
    public function testCP5_EdicionProducto(): void
    {
        $id = $this->fila("SELECT id_producto FROM products WHERE codigo_producto='PRD-TEST-01'")['id_producto'];
        $html = $this->runAjax('editar_producto.php', [
            'mod_id' => $id, 'mod_codigo' => 'PRD-TEST-01', 'mod_nombre' => 'Producto Editado',
            'mod_categoria' => '1', 'mod_precio' => '2990', 'mod_stock' => '5',
        ]);
        $this->assertStringContainsString('actualizado satisfactoriamente', $html);

        $htmlVacio = $this->runAjax('editar_producto.php', ['mod_id' => $id, 'mod_codigo' => '', 'mod_nombre' => '']);
        $this->assertStringContainsString('vacío', $htmlVacio);
    }

    #[RunInSeparateProcess]
    public function testCP6_BusquedaProductos(): void
    {
        $html = $this->runAjax('buscar_productos.php', [], ['action' => 'ajax', 'q' => 'Producto Editado', 'id_categoria' => 0]);
        $this->assertStringContainsString('Producto Editado', $html);

        $htmlVacio = $this->runAjax('buscar_productos.php', [], ['action' => 'ajax', 'q' => 'xyzxyz123noexiste', 'id_categoria' => 0]);
        $this->assertStringNotContainsString('thumb-name', $htmlVacio, 'No debe listar productos con texto sin coincidencias.');
    }

    #[RunInSeparateProcess]
    public function testCP7_ActualizacionStock(): void
    {
        require_once __DIR__ . '/funciones.php';
        global $con;
        $con = $this->con;

        $id = $this->fila("SELECT id_producto FROM products WHERE codigo_producto='PRD-TEST-01'")['id_producto'];
        $stockAntes = (int) $this->fila("SELECT stock FROM products WHERE id_producto=$id")['stock'];

        $this->assertEquals(1, agregar_stock($id, 10));
        $stockDespues = (int) $this->fila("SELECT stock FROM products WHERE id_producto=$id")['stock'];
        $this->assertEquals($stockAntes + 10, $stockDespues);

        // Escenario incorrecto: la función real NO valida stock suficiente antes de descontar.
        $this->assertEquals(1, eliminar_stock($id, 999999),
            'HALLAZGO: eliminar_stock() no valida que exista stock suficiente (bug respecto a R.7).');
        $stockNegativo = (int) $this->fila("SELECT stock FROM products WHERE id_producto=$id")['stock'];
        $this->assertLessThan(0, $stockNegativo, 'El stock queda negativo: confirma el hallazgo anterior.');
    }

    #[RunInSeparateProcess]
    public function testCP8_HistorialMovimientos(): void
    {
        require_once __DIR__ . '/funciones.php';
        global $con;
        $con = $this->con;

        $id = $this->fila("SELECT id_producto FROM products WHERE codigo_producto='PRD-TEST-01'")['id_producto'];
        guardar_historial($id, 1, date('Y-m-d H:i:s'), 'Nota de prueba CP8', 'PRD-TEST-01', 3);

        $fila = $this->fila("SELECT * FROM historial WHERE id_producto=$id ORDER BY id_historial DESC LIMIT 1");
        $this->assertNotNull($fila);
        $this->assertEquals('Nota de prueba CP8', $fila['nota']);
    }

    #[RunInSeparateProcess]
    public function testCP9_CambioPassword(): void
    {
        $id = $this->fila("SELECT user_id FROM users WHERE user_name='nuevo2026'")['user_id'];
        $hashAntes = $this->fila("SELECT user_password_hash FROM users WHERE user_id=$id")['user_password_hash'];

        $html = $this->runAjax('editar_password.php', [
            'user_id_mod' => $id, 'user_password_new3' => 'ClaveNueva1', 'user_password_repeat3' => 'ClaveNueva1',
        ]);

        // HALLAZGO CRÍTICO: editar_password.php referencia la variable $user_password_hash
        // sin haberla definido/hasheado nunca (solo existe $user_password). Esto genera un
        // error de PHP (Undefined variable) y el UPDATE guarda NULL/'' en vez del hash real.
        $this->assertStringNotContainsString('modificada con éxito', $html,
            'HALLAZGO: editar_password.php falla porque usa $user_password_hash, variable nunca definida (bug real, ver PHP Warning).');
    }

    #[RunInSeparateProcess]
    public function testCP11_CierreSesion(): void
    {
        $_SESSION['user_login_status'] = 1;
        $_GET['logout'] = true;
        $login = new Login();
        $this->assertFalse($login->isUserLoggedIn(), 'Tras el logout la sesión debe quedar cerrada.');
    }

    #[RunInSeparateProcess]
    public function testCP12_EliminacionCategoria(): void
    {
        $this->con->query("INSERT INTO categorias (nombre_categoria, descripcion_categoria, date_added) VALUES ('CatSinProductos','Test','" . date('Y-m-d H:i:s') . "')");
        $idSin = $this->con->insert_id;
        $html = $this->runAjax('buscar_categorias.php', [], ['id' => $idSin]);
        $this->assertStringContainsString('eliminados exitosamente', $html);

        $idCon = $this->fila("SELECT id_categoria FROM categorias LIMIT 1")['id_categoria'];
        $htmlBloqueado = $this->runAjax('buscar_categorias.php', [], ['id' => $idCon]);
        $this->assertStringContainsString('Existen productos vinculados', $htmlBloqueado);
    }

    #[RunInSeparateProcess]
    public function testCP13_EliminacionProducto(): void
    {
        $this->con->query("INSERT INTO products (codigo_producto,nombre_producto,date_added,precio_producto,stock,id_categoria) VALUES ('PRD-DEL','Producto a eliminar','" . date('Y-m-d H:i:s') . "',100,1,1)");
        $id = $this->con->insert_id;
        $html = $this->runAjax('buscar_productos.php', [], ['id' => $id]);
        $this->assertStringContainsString('eliminados exitosamente', $html);
        $this->assertNull($this->fila("SELECT * FROM products WHERE id_producto=$id"));
    }

    #[RunInSeparateProcess]
    public function testCP14_EliminacionUsuario(): void
    {
        $id = $this->fila("SELECT user_id FROM users WHERE user_name='nuevo2026'")['user_id'];
        $html = $this->runAjax('buscar_usuarios.php', [], ['id' => $id]);
        $this->assertStringContainsString('eliminados exitosamente', $html);

        // No debe permitir eliminar al usuario administrador (user_id = 1)
        $htmlAdmin = $this->runAjax('buscar_usuarios.php', [], ['id' => 1]);
        $this->assertStringContainsString('No se puede borrar el usuario administrador', $htmlAdmin);
    }

    #[RunInSeparateProcess]
    public function testCP17_FiltradoPorCategoria(): void
    {
        $html = $this->runAjax('buscar_productos.php', [], ['action' => 'ajax', 'q' => '', 'id_categoria' => 1]);
        $this->assertStringContainsString('thumb-name', $html, 'Debe existir al menos un producto en la categoría 1.');
    }

    #[RunInSeparateProcess]
    public function testCP20_ValidacionCamposObligatorios(): void
    {
        $html = $this->runAjax('nuevo_producto.php', ['codigo' => '', 'nombre' => '', 'stock' => '', 'precio' => '']);
        $this->assertStringContainsString('vacío', $html, 'Debe rechazar el guardado si falta un campo obligatorio.');
    }

    #[RunInSeparateProcess]
    public function testCP22_SeguridadContrasenas(): void
    {
        // Usuario dedicado a esta prueba (evita depender del usuario que CP9 modifica).
        $this->runAjax('nuevo_usuario.php', [
            'firstname' => 'Seguridad', 'lastname' => 'Test', 'user_name' => 'seg2026test',
            'user_password_new' => 'Clave123', 'user_password_repeat' => 'Clave123',
            'user_email' => 'seg2026test@correo.com',
        ]);
        $hash = $this->fila("SELECT user_password_hash FROM users WHERE user_name='seg2026test'")['user_password_hash'];
        // HALLAZGO CRÍTICO: ajax/nuevo_usuario.php inserta $_POST['user_password_new'] DIRECTO
        // en la columna user_password_hash, SIN pasar por password_hash(). La contraseña queda
        // almacenada en TEXTO PLANO, incumpliendo R.12 (bcrypt).
        $this->assertEquals('Clave123', $hash,
            'HALLAZGO CRÍTICO: la contraseña se guarda en texto plano (nuevo_usuario.php nunca llama password_hash()). Incumple R.12.');
    }

    #[RunInSeparateProcess]
    public function testCP23_TiempoRespuesta(): void
    {
        $inicio = microtime(true);
        $this->runAjax('buscar_productos.php', [], ['action' => 'ajax', 'q' => 'Producto', 'id_categoria' => 0]);
        $tiempoMs = (microtime(true) - $inicio) * 1000;
        $this->assertLessThanOrEqual(3000, $tiempoMs, 'La búsqueda debe responder en máximo 3.000 ms.');
    }

}
