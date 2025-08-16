<?php

namespace Tests;

class TestMockState
{
    public static $mockBehavior = null;
    
    public static function setMockBehavior(string $behavior): void
    {
        self::$mockBehavior = $behavior;
    }
    
    public static function clearMockBehavior(): void
    {
        self::$mockBehavior = null;
    }
}