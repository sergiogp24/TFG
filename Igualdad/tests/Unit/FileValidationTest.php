<?php
/**
 * Test de validación de archivos
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FileValidationTest extends TestCase
{
    /**
     * Prueba que se detecta correctamente un tipo MIME válido
     */
    public function testValidExcelMimeType(): void
    {
        $mimeTypes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => true, // .xlsx
            'application/vnd.ms-excel' => true, // .xls
            'text/plain' => false, // Inválido
        ];

        foreach ($mimeTypes as $mime => $expected) {
            $isValid = in_array($mime, [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel'
            ], true);
            $this->assertEquals($expected, $isValid, "MIME type {$mime} validation failed");
        }
    }

    /**
     * Prueba que se validan correctamente las extensiones de archivo
     */
    public function testValidFileExtensions(): void
    {
        $validExtensions = ['xlsx', 'xls', 'csv'];
        $testFiles = [
            'datos.xlsx' => true,
            'datos.xls' => true,
            'datos.csv' => true,
            'datos.txt' => false,
            'datos.exe' => false,
        ];

        foreach ($testFiles as $filename => $expected) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $isValid = in_array($ext, $validExtensions, true);
            $this->assertEquals($expected, $isValid, "File extension validation failed for {$filename}");
        }
    }
}
