<?php

namespace Tests\Unit;

use App\Support\LanguageCode;
use Tests\TestCase;

class LanguageCodeTest extends TestCase
{
    public function test_normalizes_iso_and_english_names(): void
    {
        $this->assertSame('en', LanguageCode::normalize('ENG'));
        $this->assertSame('ru', LanguageCode::normalize('rus'));
        $this->assertSame('uk', LanguageCode::normalize('ukrainian'));
    }

    public function test_supported_allowlist(): void
    {
        $this->assertTrue(LanguageCode::isSupported('en'));
        $this->assertTrue(LanguageCode::isSupported('ukr'));
        $this->assertFalse(LanguageCode::isSupported('de'));
        $this->assertFalse(LanguageCode::isSupported(null));
    }
}
