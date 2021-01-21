<?php

namespace JTMcC\AtomicDeployments\Models\Enums;

use ReflectionClass;

class Enum
{
    private static ?array $constCacheArray = null;

    /**
     * @throws \ReflectionException
     *
     * @return array|mixed
     */
    public static function getConstants()
    {
        if (self::$constCacheArray == null) {
            self::$constCacheArray = [];
        }
        $calledClass = get_called_class();
        if (!array_key_exists($calledClass, self::$constCacheArray)) {
            $reflect = new ReflectionClass($calledClass);
            self::$constCacheArray[$calledClass] = $reflect->getConstants();
        }

        return self::$constCacheArray[$calledClass];
    }

    /**
     * @param int $value
     *
     * @throws \ReflectionException
     *
     * @return false|int|string|null
     */
    public static function getNameFromValue(int $value)
    {
        return array_search($value, self::getConstants(), true) ?? null;
    }
}
