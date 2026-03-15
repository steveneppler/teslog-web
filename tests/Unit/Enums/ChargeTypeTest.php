<?php

namespace Tests\Unit\Enums;

use App\Enums\ChargeType;
use PHPUnit\Framework\TestCase;

class ChargeTypeTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertEquals('ac', ChargeType::Ac->value);
        $this->assertEquals('dc', ChargeType::Dc->value);
        $this->assertEquals('supercharger', ChargeType::Supercharger->value);
    }

    public function test_label_returns_correct_display_names(): void
    {
        $this->assertEquals('AC', ChargeType::Ac->label());
        $this->assertEquals('DC', ChargeType::Dc->label());
        $this->assertEquals('Supercharger', ChargeType::Supercharger->label());
    }

    public function test_is_dc_like_returns_true_for_dc(): void
    {
        $this->assertTrue(ChargeType::Dc->isDcLike());
    }

    public function test_is_dc_like_returns_true_for_supercharger(): void
    {
        $this->assertTrue(ChargeType::Supercharger->isDcLike());
    }

    public function test_is_dc_like_returns_false_for_ac(): void
    {
        $this->assertFalse(ChargeType::Ac->isDcLike());
    }
}
