<?php

namespace TowerDefense\Animation;

use GL\Math\Quat;

/**
 * Animation that rotates an object with a given orientation
 */
class TransformOrientationAnimation extends BaseAnimation
{
    /**
     * @param Quat $modifier The orientation to apply
     * @param int $duration The duration of the animation in milliseconds
     * @param AnimationEasingType $easingType The easing type
     * @param int $initialDelay The initial delay in milliseconds
     * @param bool $repeat Whether the animation should repeat
     * @param int $repeatCount The number of times the animation should repeat
     * @param int $repeatDelay The delay between each repeat in milliseconds
     * @param bool $reverse Whether the animation should reverse
     * @param int $reverseCount The number of times the animation should reverse
     * @param int $reverseDelay The delay between each reverse in milliseconds
     */
    public function __construct(
        public Quat         $modifier,
        int                 $duration,
        AnimationEasingType $easingType = AnimationEasingType::LINEAR,
        int                 $initialDelay = 0,
        bool                $repeat = false,
        int                 $repeatCount = 0,
        int                 $repeatDelay = 0,
        bool                $reverse = false,
        int                 $reverseCount = 0,
        int                 $reverseDelay = 0,
    )
    {
        parent::__construct(
            $duration,
            $easingType,
            $initialDelay,
            $repeat,
            $repeatCount,
            $repeatDelay,
            $reverse,
            $reverseCount,
            $reverseDelay,
        );
    }
}
