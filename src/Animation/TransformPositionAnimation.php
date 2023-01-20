<?php

namespace TowerDefense\Animation;

use GL\Math\Vec3;

/**
 * Animation that translates an object with a given position modifier
 */
class TransformPositionAnimation extends BaseAnimation implements AnimationInterface
{
    /**
     * @param Vec3 $modifier The position modifier to apply
     * @param AnimationEasingType $easingType The easing type
     * @param int $initialDelay The initial delay
     * @param bool $repeat Whether the animation should repeat
     * @param int $repeatCount The number of times the animation should repeat
     * @param int $repeatDelay The delay between each repeat
     * @param bool $reverse Whether the animation should reverse
     * @param int $reverseCount The number of times the animation should reverse
     * @param int $reverseDelay The delay between each reverse
     */
    public function __construct(
        public Vec3         $modifier,
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

    public function getAnimationType(): AnimationType
    {
        return AnimationType::TRANSFORM_POSITION;
    }
}
