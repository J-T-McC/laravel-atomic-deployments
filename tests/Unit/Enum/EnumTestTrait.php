<?php

namespace Tests\Unit\Enum;

trait EnumTestTrait
{
    public function test_it_checks_property_names_and_values_have_not_changed()
    {
        // Collect
        $class = self::model;
        $enum = new $class;

        // Assert
        $this->assertTrue(empty(array_diff_assoc(static::expected, $enum->getConstants())));
    }

    public function test_it_can_get_property_from_value()
    {
        // Collect
        $class = self::model;
        $enum = new $class;
        $props = $enum->getConstants();
        $category = array_keys($props)[0];
        $value = array_values($props)[0];

        // Assert
        $this->assertTrue($enum->getNameFromValue($value) === $category);
    }
}
