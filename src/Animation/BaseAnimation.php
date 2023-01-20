<?php

namespace TowerDefense\Animation;

class BaseAnimation implements AnimationContainerInterface
{
    public int $runCount = 0;
    public int $repeatedCount = 0;
    public int $reversedCount = 0;

    public float $progress = 0.0;

    public bool $finished = false;

    public bool $running = false;

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

    public function getAnimationContainerType(): AnimationContainerType
    {
        return AnimationContainerType::SINGLE_ANIMATION;
    }
}
