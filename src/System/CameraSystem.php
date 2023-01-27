<?php

namespace TowerDefense\System;

use VISU\Graphics\Rendering\RenderContext;
use VISU\System\VISUCameraSystem;

class CameraSystem extends VISUCameraSystem
{
    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update() : void
    {
        
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(RenderContext $context) : void
    {
    }
}
