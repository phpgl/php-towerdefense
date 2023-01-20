<?php

namespace TowerDefense\Animation;

/**
 * Available animation container types.
 */
enum AnimationContainerType
{
    /**
     * Only a single animation
     */
    case SINGLE_ANIMATION;

    /**
     * Multiple animations, one after the other
     */
    case SEQUENCE_OF_ANIMATIONS;

    /**
     * Parallel animations, all running at the same time
     */
    case PARALLEL_ANIMATIONS;
}
