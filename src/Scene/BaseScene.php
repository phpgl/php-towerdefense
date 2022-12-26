<?php

namespace TowerDefense\Scene;

use GameContainer;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;

abstract class BaseScene
{
    public function __construct(
        protected GameContainer $container,
    )
    {
    }

    /**
     * Returns the scenes name
     */
    abstract public function getName() : string;

    /**
     * Loads resources required for the scene
     * 
     * @return void 
     */
    abstract public function load() : void;

    /**
     * Updates the scene state
     * 
     * @return void 
     */
    abstract public function update() : void;

    /**
     * Renders the scene
     * 
     * @param RenderPipeline $pipeline The render pipeline to use
     * @param float $deltaTime
     */
    abstract public function render(RenderPipeline $pipeline, PipelineContainer $data, PipelineResources $resources, float $deltaTime) : void;
}
