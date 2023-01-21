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

    /**
     * @param AnimationEasingType $easingType The easing type of the animation
     * @param int $initialDelay The initial delay of the animation
     * @param bool $repeat Whether the animation should repeat
     * @param int $repeatCount The number of times the animation should repeat
     * @param int $repeatDelay The delay between repeats
     * @param bool $reverse Whether the animation should reverse
     * @param int $reverseCount The number of times the animation should reverse
     * @param int $reverseDelay The delay between reverses
     */
    public function __construct(
        public AnimationEasingType $easingType = AnimationEasingType::LINEAR,
        public int                 $initialDelay = 0,
        public bool                $repeat = false,
        public int                 $repeatCount = 0,
        public int                 $repeatDelay = 0,
        public bool                $reverse = false,
        public int                 $reverseCount = 0,
        public int                 $reverseDelay = 0,
    )
    {
    }
}
