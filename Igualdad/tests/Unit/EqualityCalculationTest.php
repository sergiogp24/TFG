<?php
/**
 * Test de cálculos de igualdad
 */

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EqualityCalculationTest extends TestCase
{
    /**
     * Prueba cálculo básico de brecha salarial
     */
    public function testBasicSalaryGapCalculation(): void
    {
        // Salario promedio hombres: 30,000
        // Salario promedio mujeres: 27,000
        // Brecha: -10%
        
        $salarioMujeres = 27000;
        $salarioHombres = 30000;
        
        $brecha = (($salarioMujeres - $salarioHombres) / $salarioHombres) * 100;
        
        $this->assertEqualsWithDelta(-10, $brecha, 0.0001, "Salary gap calculation failed");
    }

    /**
     * Prueba que la brecha es negativa cuando hay discriminación
     */
    public function testNegativeGapIndicatesDiscrimination(): void
    {
        $salarioMujeres = 20000;
        $salarioHombres = 25000;
        
        $brecha = (($salarioMujeres - $salarioHombres) / $salarioHombres) * 100;
        
        $this->assertLessThan(0, $brecha, "Gap should be negative when women earn less");
    }

    /**
     * Prueba cálculo con casos límite
     */
    public function testEdgeCasesInCalculation(): void
    {
        // Sin datos de uno de los géneros
        $salarioMujeres = 0;
        $salarioHombres = 30000;
        
        if ($salarioHombres > 0) {
            $brecha = (($salarioMujeres - $salarioHombres) / $salarioHombres) * 100;
            $this->assertEquals(-100, $brecha, "Gap should be -100% when no women salary data");
        } else {
            $this->assertTrue(true, "No valid calculation possible");
        }
    }
}
