<?php

namespace TowerDefense;

use GameContainer;

use TowerDefense\Debug\DebugTextOverlay;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Key;
use VISU\OS\Window;
use VISU\Runtime\GameLoopDelegate;

class Game implements GameLoopDelegate
{
    /**
     * The container instance.
     * 
     * As this is the core of the game, we will use the container directly 
     * instead of injecting all dependencies into the constructor.
     */
    protected GameContainer $container;

    /**
     * Games window instnace
     * 
     * @var Window
     */
    private Window $window;

    /**
     * Current game tick
     */
    private int $tick = 0;

    /**
     * Debug font renderer
     */
    private DebugTextOverlay $dbgText;

    /**
     * Pipeline resource manager
     */
    private PipelineResources $pipelineResources;

    /**
     * Construct a new game instance
     */
    public function __construct(GameContainer $container)
    {
        $this->container = $container;
        $this->window = $container->resolveWindowMain();

        // initialize the window
        $this->window->initailize($this->container->resolveGL());

        // make the input the windows event handler
        $this->window->setEventHandler($this->container->resolveInput());

        // initialize the pipeline resources
        $this->pipelineResources = new PipelineResources($container->resolveGL());

        // initialize the debug text renderer
        $this->dbgText = new DebugTextOverlay($container);
    }

    /**
     * Start the game
     * This will begin the game loop
     */
    public function start()
    {
        // start the game loop
        $this->container->resolveGameLoopMain()->start();
    }

    /**
     * Update the games state
     * This method might be called multiple times per frame, or not at all if
     * the frame rate is very high.
     * 
     * The update method should step the game forward in time, this is the place
     * where you would update the position of your game objects, check for collisions
     * and so on. 
     * 
     * @return void 
     */
    public function update() : void
    {
        $this->window->pollEvents();
    }

    /**
     * Render the current game state
     * This method is called once per frame.
     * 
     * The render method should draw the current game state to the screen. You recieve 
     * a delta time value which you can use to interpolate between the current and the
     * previous frame. This is useful for animations and other things that should be
     * smooth with variable frame rates.
     * 
     * @param float $deltaTime
     * @return void 
     */
    public function render(float $deltaTime) : void
    {
        $windowRenderTarget = $this->window->getRenderTarget();
        // $windowRenderTarget->framebuffer()->clearColor = new Vec4(1, 0.2, 0.2, 1.0);

        $data = new PipelineContainer;
        $pipeline = new RenderPipeline($this->pipelineResources, $data, $windowRenderTarget);

        // backbuffer render target
        $backbuffer = $data->get(BackbufferData::class)->target;

        // clear the backbuffer
        $pipeline->addPass(new ClearPass($backbuffer));
        
        // render debug text
        $this->dbgText->attachPass($pipeline, $backbuffer, $deltaTime);

        // execute the pipeline
        $pipeline->execute($this->tick++);

        $this->window->swapBuffers();
    }

    /**
     * Loop should stop
     * This method is called once per frame and should return true if the game loop
     * should stop. This is useful if you want to quit the game after a certain amount
     * of time or if the player has lost all his lives etc..
     * 
     * @return bool 
     */
    public function shouldStop() : bool
    {
        return $this->window->shouldClose();
    }
}
