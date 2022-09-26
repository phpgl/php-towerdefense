<?php

namespace TowerDefense;

use GameContainer;

use VISU\OS\Window;
use VISU\Runtime\GameLoop;
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
     * Construct a new game instance
     */
    public function __construct(GameContainer $container)
    {
        $this->container = $container;
        $this->window = $container->resolveWindowMain();
    }

    /**
     * Start the game
     * This will begin the game loop
     */
    public function start()
    {
        // initialize the window
        $this->window->initailize($this->container->resolveGL());

        // load the input handler, so it dispatches signals
        $this->container->resolveInput();

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
        glClearColor(0.0, 0.0, 0.0, 1.0);
        glClear(GL_COLOR_BUFFER_BIT);


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
