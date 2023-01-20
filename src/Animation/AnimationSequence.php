<?php

namespace TowerDefense\Animation;

class AnimationSequence extends BaseAnimationContainer implements AnimationContainerInterface
{
    public function getAnimationContainerType(): AnimationContainerType
    {
        return AnimationContainerType::SEQUENCE_OF_ANIMATIONS;
    }
}
