<?php

namespace TowerDefense\Animation;

/**
 * A sequence of animations, played one after the other.
 */
class AnimationSequence extends BaseAnimationContainer implements AnimationContainerInterface
{
    public function getAnimationContainerType(): AnimationContainerType
    {
        return AnimationContainerType::SEQUENCE_OF_ANIMATIONS;
    }
}
