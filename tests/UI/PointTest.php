<?php

namespace Tests\UI;

use GL\Math\Vec2;
use TowerDefense\UI\Point;
use TowerDefense\UI\Rect;

class PointTest extends \PHPUnit\Framework\TestCase
{
    public function testStringCreation(): void
    {
        $pointA = new Vec2(1, 2);
        $string = Point::toString($pointA);
        $pointB = Point::fromString($string);
        $this->assertTrue(Point::isEqualTo($pointA, $pointB));
    }

    public function testInsideRect(): void
    {
        $pointA = new Vec2(1, 2);
        $pointB = new Vec2(10, 10);
        $pointC = new Vec2(4, 4);
        $pointD = new Vec2(-1, -1);
        $rect = new Rect(new Vec2(0, 0), new Vec2(4, 4));
        $this->assertTrue(Point::insideRect($pointA, $rect));
        $this->assertFalse(Point::insideRect($pointB, $rect));
        $this->assertTrue(Point::insideRect($pointC, $rect));
        $this->assertFalse(Point::insideRect($pointD, $rect));
    }
}
