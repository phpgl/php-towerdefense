<?php

namespace TowerDefense\Scene;

use GameContainer;
use TowerDefense\Renderer\TerrainRenderer;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\System\VISUCameraSystem;

class LevelScene extends BaseScene
{
    private TerrainRenderer $terrainRenderer;
    private VISUCameraSystem $cameraSystem;

    /**
     * Constructor
     */
    public function __construct(
        protected GameContainer $container,
    )
    {
        parent::__construct($container);

        $this->terrainRenderer = new TerrainRenderer($container->resolveGL());
        $this->cameraSystem = new VISUCameraSystem(
            $this->container->resolveInput(), 
            $this->container->resolveVisuDispatcher()
        );

        // prepare the scene 
        $this->prepareScene();
    }

    /**
     * Returns the scenes name
     */
    public function getName() : string
    {
        return 'TowerDefense Level';
    }

    /**
     * Create required entites for the scene
     */
    private function prepareScene()
    {
        $this->cameraSystem->register($this->entities);

        // create a camera
        $cameraEntity = $this->entities->create();
        $this->entities->attach($cameraEntity, new Camera(CameraProjectionMode::perspective));
        $this->cameraSystem->setActiveCameraEntity($cameraEntity);
    }

    /**
     * Loads resources required for the scene
     * 
     * @return void 
     */
    public function load() : void
    {
        $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RESOURCES . '/terrain/alien_planet/alien_planet_terrain.obj');
    }

    /**
     * Updates the scene state
     * 
     * @return void 
     */
    public function update() : void
    {
        $this->cameraSystem->update($this->entities);
    }

    /**
     * Renders the scene
     * 
     * @param RenderPipeline $pipeline The render pipeline to use
     * @param float $deltaTime
     */
    public function render(RenderPipeline $pipeline, PipelineContainer $data, PipelineResources $resources, float $deltaTime) : void
    {
        $context = new RenderContext($pipeline, $data, $resources, $deltaTime);

        $this->cameraSystem->render($this->entities, $context);

        $this->terrainRenderer->attachPass($pipeline);
    }
}
