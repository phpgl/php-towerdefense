<?php

namespace TowerDefense\Scene;

use GameContainer;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\EntityRegisty;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPipeline;

abstract class BaseScene
{
    /**
     * The ECS registry, this is where your game state should be stored.
     */
    protected EntitiesInterface $entities;

    /**
     * Constructor 
     */
    public function __construct(
        protected GameContainer $container,
    )
    {
        $this->entities = new EntityRegisty();
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
     * @param RenderContext $context
     */
    abstract public function render(RenderContext $context) : void;
}
