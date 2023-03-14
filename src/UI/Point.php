<?php

namespace TowerDefense\UI;

use GL\Math\Vec2;

class Point
{
    public static function toString(Vec2 $point): string
    {
        return sprintf('%f,%f', $point->x, $point->y);
    }

    public static function fromString(string $string): Vec2
    {
        [$x, $y] = explode(',', $string);

        return new Vec2((float) $x, (float) $y);
    }

    public static function isEqualTo(Vec2 $point1, Vec2 $point2): bool
    {
        return $point1->x == $point2->x && $point1->y == $point2->y;
    }

    public static function insideRect(Vec2 $point, Rect $rect): bool
    {
        return $point->x >= $rect->position->x &&
            $point->y >= $rect->position->y &&
            $point->x <= ($rect->position->x + $rect->size->x) &&
            $point->y <= ($rect->position->y + $rect->size->y);
    }
}
