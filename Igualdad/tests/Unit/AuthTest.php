<?php
/**
 * Test de autenticación básica
 * Verifica que la sesión se inicializa correctamente
 */

namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    /**
     * Prueba que la función h() sanitiza HTML correctamente
     */
    public function testHtmlSanitization(): void
    {
        $input = '<script>alert("XSS")</script>';
        $expected = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;';
        
        // h() debe estar disponible del bootstrap
        $this->assertSame($expected, h($input));
    }

    /**
     * Prueba que el ambiente de testing está configurado
     */
    public function testTestEnvironmentSetup(): void
    {
        $this->assertSame('testing', $_ENV['APP_ENV']);
    }

    /**
     * Prueba que la configuración de base de datos está disponible
     */
    public function testDatabaseConfigurationAvailable(): void
    {
        // Verificar que las constantes de BD están definidas
        $this->assertTrue(defined('DB_HOST'));
        $this->assertTrue(defined('DB_NAME'));
        $this->assertTrue(defined('DB_USER'));
        // DB_PASS puede estar vacío en desarrollo
    }
}
