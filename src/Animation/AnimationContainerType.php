<?php

namespace TowerDefense\Animation;

enum AnimationContainerType
{
    case SINGLE_ANIMATION;
    case SEQUENCE_OF_ANIMATIONS;
    case PARALLEL_ANIMATIONS;
}
