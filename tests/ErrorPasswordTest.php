<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/helpers.php';

final class ErrorPasswordTest extends TestCase
{
    public function test_acepta_password_valida_con_letras_y_numeros(): void
    {
        // Arrange
        $password = 'Segura123';

        // Act
        $resultado = errorPassword($password);

        // Assert
        $this->assertNull($resultado);
    }

    public function test_acepta_password_con_exactamente_8_caracteres(): void
    {
        // Arrange
        $password = 'abcd1234';

        // Act
        $resultado = errorPassword($password);

        // Assert
        $this->assertNull($resultado);
    }

    public function test_rechaza_password_con_7_caracteres(): void
    {
        // Arrange
        $password = 'abc1234';

        // Act
        $resultado = errorPassword($password);

        // Assert
        $this->assertNotNull($resultado);
        $this->assertStringContainsString('8 caracteres', $resultado);
    }

    public function test_rechaza_password_sin_numeros(): void
    {
        // Arrange
        $password = 'soloLetras';

        // Act
        $resultado = errorPassword($password);

        // Assert
        $this->assertNotNull($resultado);
        $this->assertStringContainsString('letras y', $resultado);
    }
}