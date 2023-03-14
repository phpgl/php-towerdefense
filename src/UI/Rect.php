<?php

namespace TowerDefense\UI;

use GL\Math\Vec2;

/**
 * Class Rect
 * 
 * @package TowerDefense\UI
 */
class Rect
{
    public function __construct(
        public Vec2 $position,
        public Vec2 $size
    ) {
    }

    public static function fromXYWidthHeight(float $x, float $y, float $width, float $height): self
    {
        return new self(
            new Vec2($x, $y),
            new Vec2($width, $height)
        );
    }

    public function toString(): string
    {
        return sprintf('%s,%s', Point::toString($this->position), Point::toString($this->size));
    }

    public static function fromString(string $string): self
    {
        [$x, $y, $width, $height] = explode(',', $string);

        return self::fromXYWidthHeight(
            (float) $x,
            (float) $y,
            (float) $width,
            (float) $height
        );
    }

    public function copy(): self
    {
        return new self(
            $this->position->copy(),
            $this->size->copy()
        );
    }

    public function isEqualTo(Rect $rect): bool
    {
        return Point::isEqualTo($this->position, $rect->position) &&
            Point::isEqualTo($this->size, $rect->size);
    }

    public function getMaxX(): float
    {
        return $this->position->x + $this->size->x;
    }

    public function getMaxY(): float
    {
        return $this->position->y + $this->size->y;
    }

    public function getMidX(): float
    {
        return $this->position->x + $this->size->x / 2;
    }

    public function getMidY(): float
    {
        return $this->position->y + $this->size->y / 2;
    }

    public function getMinX(): float
    {
        return $this->position->x;
    }

    public function getMinY(): float
    {
        return $this->position->y;
    }

    public function getCenter(): Vec2
    {
        return new Vec2($this->getMidX(), $this->getMidY());
    }

    public function containsPoint(Vec2 $point): bool
    {
        return Point::insideRect($point, $this);
    }

    public function insetRect(float $dx, float $dy): self
    {
        return new self(
            new Vec2($this->position->x + $dx, $this->position->y + $dy),
            new Vec2($this->size->x - ($dx * 2), $this->size->y - ($dy * 2))
        );
    }

    public function offsetRect(float $dx, float $dy): self
    {
        return new self(
            new Vec2($this->position->x + $dx, $this->position->y + $dy),
            $this->size->copy()
        );
    }

    public function integralRect(): self
    {
        return new self(
            new Vec2(floor($this->position->x), floor($this->position->y)),
            new Vec2(ceil($this->size->x), ceil($this->size->y))
        );
    }

    public function isNullRect(): bool
    {
        return $this->size->x == 0 && $this->size->y == 0;
    }

    public function isEmptyRect(): bool
    {
        return $this->size->x <= 0 || $this->size->y <= 0;
    }

    public function intersectionWithRect(Rect $rect): self
    {
        $intersection = self::fromXYWidthHeight(max($this->getMinX(), $rect->getMinX()), max($this->getMinY(), $rect->getMinY()), 0, 0);

        $intersection->size->x = min($this->getMaxX(), $rect->getMaxX()) - $intersection->getMinX();
        $intersection->size->y = min($this->getMaxY(), $rect->getMaxY()) - $intersection->getMinY();

        return $intersection->isEmptyRect() ? self::fromXYWidthHeight(0.0, 0.0, 0.0, 0.0) : $intersection;
    }

    public function intersectsRect(Rect $rect): bool
    {
        return $this->intersectionWithRect($rect)->isEmptyRect() == false;
    }

    public function unionWithRect(Rect $rect): self
    {
        $union = self::fromXYWidthHeight(min($this->getMinX(), $rect->getMinX()), min($this->getMinY(), $rect->getMinY()), 0, 0);

        $union->size->x = max($this->getMaxX(), $rect->getMaxX()) - $union->getMinX();
        $union->size->y = max($this->getMaxY(), $rect->getMaxY()) - $union->getMinY();

        return $union;
    }

    public function standardizedRect(): self
    {
        $width = $this->size->x;
        $height = $this->size->y;
        $standardized = $this->copy();

        if ($width < 0.0) {
            $standardized->position->x = $standardized->position->x + $width;
            $standardized->size->x = -$width;
        }

        if ($height < 0.0) {
            $standardized->position->y = $standardized->position->y + $height;
            $standardized->size->y = -$height;
        }

        return $standardized;
    }

    public function isInsideRect(Rect $rect): bool
    {
        return $this->unionWithRect($rect)->isEqualTo($rect);
    }
}
