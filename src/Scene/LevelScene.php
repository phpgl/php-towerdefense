<?php

namespace TowerDefense\Scene;

use GameContainer;
use TowerDefense\Debug\DebugTextOverlay;
use TowerDefense\Renderer\TerrainRenderer;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\GBufferPass;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Renderer\FullscreenDebugDepthRenderer;
use VISU\Graphics\Rendering\Renderer\FullscreenTextureRenderer;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Key;
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPolyRenderingSystem;

class LevelScene extends BaseScene
{
    private TerrainRenderer $terrainRenderer;
    private VISULowPolyRenderingSystem $renderingSystem;
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
        $this->renderingSystem = new VISULowPolyRenderingSystem($container->resolveGL());
        $this->renderingSystem->addGeometryRenderer($this->terrainRenderer);

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
     * @param RenderContext $context
     */
    public function render(RenderContext $context) : void
    {
        $this->cameraSystem->render($this->entities, $context);

        $backbuffer = $context->data->get(BackbufferData::class);

        $this->renderingSystem->setRenderTarget($backbuffer->target);
        $this->renderingSystem->render($this->entities, $context);


        // $context->pipeline->addPass(new GBufferPass);
        // $gbuffer = $context->data->get(GBufferPassData::class);

        // $this->terrainRenderer->attachPass($context->pipeline);

        // $this->fullscreenRenderer->attachPass($context->pipeline, $backbuffer->target, $gbuffer->albedoTexture);

        // // $this->fullscreenDebugDepthRenderer->attachPass($context->pipeline, $backbuffer->target, $gbuffer->depthTexture);
    }
}
