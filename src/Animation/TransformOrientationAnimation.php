<?php

namespace TowerDefense\Animation;

use GL\Math\Quat;

class TransformOrientationAnimation extends BaseAnimation implements AnimationInterface
{
    public function __construct(
        public Quat         $modifier,
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
        return AnimationType::TRANSFORM_ORIENTATION;
    }
}
