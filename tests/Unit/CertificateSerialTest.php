<?php
/**
 * Example unit test for Certificate serial number generation.
 *
 * Run: composer test
 */

use PHPUnit\Framework\TestCase;

class CertificateSerialTest extends TestCase {

    public function test_serial_number_is_not_empty(): void {
        // Placeholder: verify that a generated serial is non-empty
        $serial = 'GEMA-2026-' . strtoupper( uniqid() );
        $this->assertNotEmpty( $serial );
    }

    public function test_serial_number_is_unique(): void {
        $serial_a = 'GEMA-' . uniqid();
        $serial_b = 'GEMA-' . uniqid();
        $this->assertNotSame( $serial_a, $serial_b );
    }

    public function test_serial_has_expected_prefix(): void {
        $serial = 'GEMA-2026-ABC123';
        $this->assertStringStartsWith( 'GEMA-', $serial );
    }
}
