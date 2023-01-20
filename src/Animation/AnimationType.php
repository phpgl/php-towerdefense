<?php

namespace TowerDefense\Animation;

/**
 * Animation types
 */
enum AnimationType
{
    /**
     * Orientation animation (rotation)
     */
    case TRANSFORM_ORIENTATION;

    /**
     * Scale animation
     */
    case TRANSFORM_SCALE;

    /**
     * Position animation (translation)
     */
    case TRANSFORM_POSITION;

    /**
     * Unknown animation type -> that should not happen
     */
    case UNKNOWN;
}
