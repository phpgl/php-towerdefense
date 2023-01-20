<?php

namespace TowerDefense\Animation;

/**
 * Parallel animations, played at the same time.
 */
class ParallelAnimations extends BaseAnimationContainer implements AnimationContainerInterface
{
    public function getAnimationContainerType(): AnimationContainerType
    {
        return AnimationContainerType::PARALLEL_ANIMATIONS;
    }
}
