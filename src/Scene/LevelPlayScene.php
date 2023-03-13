<?php

namespace TowerDefense\Scene;

use GameContainer;
use VISU\Exception\VISUException;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Signal\RegisterHandlerException;
use VISU\OS\Exception\UninitializedWindowException;

class LevelPlayScene extends LevelScene
{
    /**
     * Returns the scenes name
     */
    public function getName() : string
    {
        return sprintf('TowerDefense (%s) [PLAY]', $this->level->name);
    }

    /**
     * Constructor
     */
    public function __construct(
        protected GameContainer $container,
    )
    {
        parent::__construct($container);
    }

    /**
     * Loads resources required for the scene, prepere base entiteis 
     * basically prepare anything that is required to be ready before the scene is rendered.
     * 
     * @return void 
     */
    public function load() : void
    {
        parent::load();
    }

    /**
     * Updates the scene state
     * This is the place where you should update your game state.
     * 
     * @return void 
     */
    public function update() : void
    {
        parent::update();
    }


    /**
     * Renders the scene
     * This is the place where you should render your game state.
     * 
     * @param RenderContext $context
     */
    public function render(RenderContext $context) : void
    {
        parent::render($context);
    }
}