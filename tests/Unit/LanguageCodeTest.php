<?php

namespace Tests\Unit;

use App\Support\LanguageCode;
use Tests\TestCase;

class LanguageCodeTest extends TestCase
{
    public function test_normalizes_iso_and_english_names(): void
    {
        $this->assertSame('en', LanguageCode::normalize('en'));
        $this->assertSame('en', LanguageCode::normalize('ENG'));
        $this->assertSame('en', LanguageCode::normalize('en-US'));
        $this->assertSame('en', LanguageCode::normalize('en-GB'));
        $this->assertSame('en', LanguageCode::normalize('english'));
        $this->assertSame('ru', LanguageCode::normalize('ru'));
        $this->assertSame('ru', LanguageCode::normalize('rus'));
        $this->assertSame('ru', LanguageCode::normalize('ru-RU'));
        $this->assertSame('ru', LanguageCode::normalize('russian'));
        $this->assertSame('uk', LanguageCode::normalize('uk'));
        $this->assertSame('uk', LanguageCode::normalize('ukr'));
        $this->assertSame('uk', LanguageCode::normalize('uk-UA'));
        $this->assertSame('uk', LanguageCode::normalize('UK-UA'));
        $this->assertSame('uk', LanguageCode::normalize('ua-UA'));
        $this->assertSame('uk', LanguageCode::normalize('ukrainian'));
    }

    public function test_unknown_code_stays_unsupported(): void
    {
        $this->assertSame('de', LanguageCode::normalize('de'));
        $this->assertSame('spa', LanguageCode::normalize('spa'));
        $this->assertSame('pol', LanguageCode::normalize('pol-PL'));
        $this->assertFalse(LanguageCode::isSupported('de'));
        $this->assertFalse(LanguageCode::isSupported('spa'));
        $this->assertFalse(LanguageCode::isSupported('pol'));
        $this->assertFalse(LanguageCode::isSupported(null));
        $this->assertNull(LanguageCode::normalize(null));
        $this->assertNull(LanguageCode::normalize(''));
    }

    public function test_supported_allowlist(): void
    {
        $this->assertTrue(LanguageCode::isSupported('en'));
        $this->assertTrue(LanguageCode::isSupported('ukr'));
        $this->assertTrue(LanguageCode::isSupported('uk-UA'));
        $this->assertTrue(LanguageCode::isSupported('ru-RU'));
        $this->assertTrue(LanguageCode::isSupported('en-US'));
        $this->assertFalse(LanguageCode::isSupported('de'));
        $this->assertFalse(LanguageCode::isSupported(null));
    }

    public function test_provider_codes(): void
    {
        $this->assertSame('eng', LanguageCode::toProviderCode('en-US'));
        $this->assertSame('rus', LanguageCode::toProviderCode('ru'));
        $this->assertSame('ukr', LanguageCode::toProviderCode('uk-UA'));
        $this->assertSame('spa', LanguageCode::toProviderCode('spa'));
    }
}
