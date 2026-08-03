<?php

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Service\ReplayRequestSignature;

class ReplayRequestSignatureTest extends AbstractTests
{
    public function testFallbackSignatureIsStableAndValidated()
    {
        $signature = new ReplayRequestSignature('test-secret');

        $token = $signature->generate('abc123', 4);

        $this->assertNotSame('', $token);
        $this->assertTrue($signature->isValid('abc123', 4, $token));
        $this->assertFalse($signature->isValid('abc123', 5, $token));
        $this->assertFalse($signature->isValid('abc123', 4, 'invalid'));
    }
}
