<?php

namespace TowerDefense\Animation;

/**
 * Base for animations
 */
class BaseAnimation
{
    public int $runCount = 0; // Number of times the animation has run
    public int $repeatedCount = 0; // Number of times the animation has repeated
    public int $reversedCount = 0; // Number of times the animation has reversed
    public float $progress = 0.0; // Progress of the current animation run
    public bool $finished = false; // Whether the animation has finished
    public bool $running = false; // Whether the animation is running
    public bool $reversing = false; // Whether the animation is reversing
    public int $requiredTicks = 0; // Number of ticks required to complete the animation
    public int $currentTick = 0; // Current tick of the animation

    /**
     * @param int $duration The duration of the animation in milliseconds
     * @param AnimationEasingType|null $easingType The easing type of the animation
     * @param int|null $initialDelay The initial delay of the animation in milliseconds
     * @param bool $repeat Whether the animation should repeat
     * @param int|null $repeatCount The number of times the animation should repeat
     * @param int|null $repeatDelay The delay between repeats in milliseconds
     * @param bool $reverse Whether the animation should reverse
     * @param int|null $reverseCount The number of times the animation should reverse
     * @param int|null $reverseDelay The delay between reverses in milliseconds
     */
    public function __construct(
        public int                  $duration,
        public ?AnimationEasingType $easingType = AnimationEasingType::LINEAR,
        public ?int                 $initialDelay = 0,
        public ?bool                $repeat = false,
        public ?int                 $repeatCount = 0,
        public ?int                 $repeatDelay = 0,
        public ?bool                $reverse = false,
        public ?int                 $reverseCount = 0,
        public ?int                 $reverseDelay = 0,
    )
    {
    }
}
