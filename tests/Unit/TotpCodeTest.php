<?php

namespace Tests\Unit;

use App\Support\TotpCode;
use PHPUnit\Framework\TestCase;

class TotpCodeTest extends TestCase
{
    public function test_digits_only_strips_hyphen_and_space(): void
    {
        $this->assertSame('123456', TotpCode::digitsOnly('123-456'));
        $this->assertSame('123456', TotpCode::digitsOnly('123 456'));
        $this->assertSame('123456', TotpCode::digitsOnly('123456'));
        $this->assertSame('', TotpCode::digitsOnly(null));
    }
}
