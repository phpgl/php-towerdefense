<?php

namespace TowerDefense;

use GameContainer;

use TowerDefense\Debug\DebugTextOverlay;
use TowerDefense\Scene\BaseScene;
use TowerDefense\Scene\LevelScene;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\ClearPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\Debug3DRenderer;
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
     * Debug 3D renderer
     */
    private Debug3DRenderer $dbg3D;

    /**
     * Pipeline resource manager
     */
    private PipelineResources $pipelineResources;

    /**
     * The currently active scene
     */
    private BaseScene $currentScene;

    /**
     * Construct a new game instance
     */
    public function __construct(GameContainer $container)
    {
        $this->container = $container;
        $this->window = $container->resolveWindowMain();

        // initialize the window
        $this->window->initailize($this->container->resolveGL());

        // enable vsync by default
        $this->window->setSwapInterval(1);

        // make the input the windows event handler
        $this->window->setEventHandler($this->container->resolveInput());

        // initialize the pipeline resources
        $this->pipelineResources = new PipelineResources($container->resolveGL());

        // initialize the debug renderers
        $this->dbgText = new DebugTextOverlay($container);
        $this->dbg3D = new Debug3DRenderer($container->resolveGL());
        Debug3DRenderer::setGlobalInstance($this->dbg3D);

        // load an inital scene
        $this->currentScene = new LevelScene($container);
    }

    /**
     * Returns the current scene
     */
    public function getCurrentScene() : BaseScene
    {
        return $this->currentScene;
    }

    /**
     * Start the game
     * This will begin the game loop
     */
    public function start()
    {
        // initialize the current scene
        $this->currentScene->load();

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

        // update the current scene
        $this->currentScene->update();
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
        $context = new RenderContext($pipeline, $data, $this->pipelineResources, $deltaTime);

        // backbuffer render target
        $backbuffer = $data->get(BackbufferData::class)->target;

        // clear the backbuffer
        $pipeline->addPass(new ClearPass($backbuffer));

        // render the current scene
        $this->currentScene->render($context);
        
        // render debug text
        $this->dbg3D->attachPass($pipeline, $backbuffer);
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

    /**
     * Custom debug info.
     * When because of the way dependencies form in this came the debugging output can become very
     * large. This method is used to limit the amount of information that is displayed.
     * 
     * I know, I know, there should't be references to game in the first place..
     */
    public function __debugInfo() {
		return ['currentScene' => $this->currentScene->getName()];
	}
}
