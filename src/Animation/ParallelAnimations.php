<?php

namespace TowerDefense\Animation;

class ParallelAnimations extends BaseAnimationContainer implements AnimationContainerInterface
{
    public function getAnimationContainerType(): AnimationContainerType
    {
        return AnimationContainerType::PARALLEL_ANIMATIONS;
    }
}
