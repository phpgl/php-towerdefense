<?php

namespace TowerDefense\Component;

use GL\Math\Vec3;
use VISU\Graphics\Heightmap\Heightmap;

class HeightmapComponent
{
    /**
     * If true the heightmap system will perform ray casts 
     * based on the current cursor position and update 
     */
    public bool $enableRaycasting = true;

    /**
     * Cursor world position
     */
    public Vec3 $cursorWorldPosition;

    /**
     * If true the heightmap system will capture a new heightmap
     */
    public bool $requiresCapture = true;

    /**
     * Heightmap instance
     */
    public Heightmap $heightmap;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cursorWorldPosition = new Vec3(0.0);
    }
}
