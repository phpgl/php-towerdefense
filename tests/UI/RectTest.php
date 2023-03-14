<?php

namespace Tests\UI;

use GL\Math\Vec2;
use TowerDefense\UI\Rect;

class RectTest extends \PHPUnit\Framework\TestCase
{
    public function testStringCreation(): void
    {
        $rect = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $rect = Rect::fromString($rect->toString());

        $this->assertEquals(1, $rect->position->x);
        $this->assertEquals(2, $rect->position->y);
        $this->assertEquals(3, $rect->size->x);
        $this->assertEquals(4, $rect->size->y);
    }

    public function testEqualTo(): void
    {
        $rectA = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $rectB = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $rectC = new Rect(new Vec2(1, 2), new Vec2(3, 5));

        $this->assertTrue($rectA->isEqualTo($rectB));
        $this->assertFalse($rectA->isEqualTo($rectC));
    }

    public function testGetCenter(): void
    {
        $rect = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $center = $rect->getCenter();

        $this->assertEquals(2.5, $center->x);
        $this->assertEquals(4, $center->y);
    }

    public function testContaintsPoint(): void
    {
        $rect = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $pointA = new Vec2(1, 2);
        $pointB = new Vec2(2, 3);
        $pointC = new Vec2(4, 6);
        $pointD = new Vec2(0, 0);
        $pointE = new Vec2(4, 7);

        $this->assertTrue($rect->containsPoint($pointA));
        $this->assertTrue($rect->containsPoint($pointB));
        $this->assertTrue($rect->containsPoint($pointC));
        $this->assertFalse($rect->containsPoint($pointD));
    }

    public function testInsetRect(): void
    {
        $rect = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $insetRect = $rect->insetRect(1, 1);

        $this->assertEquals(2, $insetRect->position->x);
        $this->assertEquals(3, $insetRect->position->y);
        $this->assertEquals(1, $insetRect->size->x);
        $this->assertEquals(2, $insetRect->size->y);
    }

    public function testOffsetRect(): void
    {
        $rect = new Rect(new Vec2(1, 2), new Vec2(3, 4));
        $offsetRect = $rect->offsetRect(1, 1);

        $this->assertEquals(2, $offsetRect->position->x);
        $this->assertEquals(3, $offsetRect->position->y);
        $this->assertEquals(3, $offsetRect->size->x);
        $this->assertEquals(4, $offsetRect->size->y);
    }

    public function testIntegralRect(): void
    {
        $rect = new Rect(new Vec2(1.1, 2.2), new Vec2(3.3, 4.4));
        $integralRect = $rect->integralRect();

        $this->assertEquals(1, $integralRect->position->x);
        $this->assertEquals(2, $integralRect->position->y);
        $this->assertEquals(4, $integralRect->size->x);
        $this->assertEquals(5, $integralRect->size->y);
    }

    public function testIsNullAndEmptyRect(): void
    {
        $rect = new Rect(new Vec2(0, 0), new Vec2(0, 0));

        $this->assertTrue($rect->isNullRect());
        $this->assertTrue($rect->isEmptyRect());
    }

    public function testIntersectionWithRect(): void
    {
        $rectA = new Rect(new Vec2(0, 0), new Vec2(10, 10));
        $rectB = new Rect(new Vec2(5, 5), new Vec2(15, 15));
        $rectC = new Rect(new Vec2(50, 50), new Vec2(10, 10));

        $intersection = $rectA->intersectionWithRect($rectB);
        $this->assertEquals(5, $intersection->position->x);
        $this->assertEquals(5, $intersection->position->y);
        $this->assertEquals(5, $intersection->size->x);
        $this->assertEquals(5, $intersection->size->y);

        $intersection = $rectA->intersectionWithRect($rectC);
        $this->assertTrue($intersection->isNullRect());
    }

    public function testIntersectsRect(): void
    {
        $rectA = new Rect(new Vec2(0, 0), new Vec2(10, 10));
        $rectB = new Rect(new Vec2(5, 5), new Vec2(15, 15));
        $rectC = new Rect(new Vec2(50, 50), new Vec2(10, 10));

        $this->assertTrue($rectA->intersectsRect($rectB));
        $this->assertFalse($rectA->intersectsRect($rectC));
    }

    public function testUnionWithRect(): void
    {
        $rectA = new Rect(new Vec2(0, 0), new Vec2(10, 10));
        $rectB = new Rect(new Vec2(5, 5), new Vec2(15, 15));
        $rectC = new Rect(new Vec2(50, 50), new Vec2(10, 10));
        $rectD = new Rect(new Vec2(100, 100), new Vec2(10, 10));

        $union = $rectA->unionWithRect($rectB);
        $this->assertEquals(0, $union->position->x);
        $this->assertEquals(0, $union->position->y);
        $this->assertEquals(20, $union->size->x);
        $this->assertEquals(20, $union->size->y);

        $union = $rectA->unionWithRect($rectC);
        $this->assertEquals(0, $union->position->x);
        $this->assertEquals(0, $union->position->y);
        $this->assertEquals(60, $union->size->x);
        $this->assertEquals(60, $union->size->y);

        $union = $rectB->unionWithRect($rectD);
        $this->assertEquals(5, $union->position->x);
        $this->assertEquals(5, $union->position->y);
        $this->assertEquals(105, $union->size->x);
        $this->assertEquals(105, $union->size->y);

        $union = $rectA->unionWithRect($rectD);
        $this->assertEquals(0, $union->position->x);
        $this->assertEquals(0, $union->position->y);
        $this->assertEquals(110, $union->size->x);
        $this->assertEquals(110, $union->size->y);
    }

    public function testStandardizedRect(): void
    {
        $rectA = new Rect(new Vec2(0, 0), new Vec2(-10, -10));
        $standardized = $rectA->standardizedRect();
        $this->assertEquals(-10, $standardized->position->x);
        $this->assertEquals(-10, $standardized->position->y);
        $this->assertEquals(10, $standardized->size->x);
        $this->assertEquals(10, $standardized->size->y);
    }

    public function testIsInsideRect(): void
    {
        $rectA = new Rect(new Vec2(0, 0), new Vec2(10, 10));
        $rectB = new Rect(new Vec2(5, 5), new Vec2(15, 15));
        $rectC = new Rect(new Vec2(50, 50), new Vec2(10, 10));
        $rectD = new Rect(new Vec2(0, 0), new Vec2(20, 20));

        $this->assertFalse($rectA->isInsideRect($rectB));
        $this->assertFalse($rectA->isInsideRect($rectC));
        $this->assertTrue($rectA->isInsideRect($rectD));
        $this->assertTrue($rectB->isInsideRect($rectD));
    }
}
