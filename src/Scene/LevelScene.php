<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\Vec3;
use TowerDefense\Renderer\TerrainRenderer;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\Picker\DevEntityPicker;
use VISU\Geo\Transform;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Key;
use VISU\Signals\Input\MouseClickSignal;
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\LPObjLoader;
use VISU\System\VISULowPoly\LPRenderingSystem as VISULowPolyRenderingSystem;
use VISU\System\VISULowPoly\LPVertexBuffer;

class LevelScene extends BaseScene
{
    private TerrainRenderer $terrainRenderer;
    private VISULowPolyRenderingSystem $renderingSystem;
    private VISUCameraSystem $cameraSystem;

    private LPVertexBuffer $objectVertexBuffer;
    private LPObjLoader $objectLoader;
    private array $loadedObjects = [];

    /**
     * Dev entity picker, for debug and level editor
     */
    private DevEntityPicker $devPicker;

    /**
     * If enabled click events will trigger the entity picker 
     * and dispatch entity.selected events.
     */
    protected bool $allowDevEntityPicker = true;

    /**
     * Function id for "handleMouseClick" handler
     */
    private int $handleMouseClickId;

    /**
     * Constructor
     */
    public function __construct(
        protected GameContainer $container,
        protected PipelineResources $pipelineResources,
    )
    {
        parent::__construct($container);

        // load all space kit models
        $this->objectVertexBuffer = new LPVertexBuffer($container->resolveGL());
        $this->objectLoader = new LPObjLoader($container->resolveGL());
        $this->loadedObjects = $this->objectLoader->loadAllInDirectory(VISU_PATH_RESOURCES . '/models/spacekit');

        // prepare the rendering systems 
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL());
        $this->renderingSystem = new VISULowPolyRenderingSystem($container->resolveGL());
        $this->renderingSystem->addGeometryRenderer($this->terrainRenderer);

        $this->cameraSystem = new VISUCameraSystem(
            $this->container->resolveInput(), 
            $this->container->resolveVisuDispatcher()
        );

        $this->devPicker = new DevEntityPicker($this->container->resolveGL(), $this->pipelineResources);

        // register some input handlers
        $this->handleMouseClickId = $this->container->resolveVisuDispatcher()->register('input.mouse_click', [$this, 'handleMouseClick']);

        // prepare the scene 
        $this->prepareScene();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->container->resolveVisuDispatcher()->unregister('input.mouse_click', $this->handleMouseClickId);
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
        $this->renderingSystem->register($this->entities);

        // create a camera
        $cameraEntity = $this->entities->create();
        $camera = $this->entities->attach($cameraEntity, new Camera(CameraProjectionMode::perspective));
        $camera->transform->position->y = 100;
        $camera->transform->position->z = 50;
        $this->cameraSystem->setActiveCameraEntity($cameraEntity);

        // create some random renderable objects
        $someObject = $this->entities->create();
        $renderable = $this->entities->attach($someObject, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['turret_double.obj']; // <- render the turret
        $transform = $this->entities->attach($someObject, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position->y = 50;
        $transform->position->z = -50;

        // create a sattelie dish "satelliteDish_large.obj"
        $dishObject = $this->entities->create();
        $renderable = $this->entities->attach($dishObject, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['satelliteDish_large.obj'];
        $transform = $this->entities->attach($dishObject, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position->y = 50;
        $transform->position->x = 100;
        $transform->position->z = -50;
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
     * Handles mouse click events
     */
    public function handleMouseClick(MouseClickSignal $signal)
    {
        if ($this->allowDevEntityPicker) 
        {   
            $windowRenderTarget = $this->container->resolveWindowMain()->getRenderTarget();

            $cameraData = $this->cameraSystem->getCameraData(
                $this->entities,
                $windowRenderTarget,
                0
            );

            $systems = [
                $this->renderingSystem,
            ];
            
            $this->devPicker->pickEntity($this->entities, $windowRenderTarget, $cameraData, $systems, (int) $signal->position->x, (int) $signal->position->y);
        }
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

        \VISU\D3D::ray(new Vec3(0, 0, 0), new Vec3(0, 1, 0), \VISU\D3D::$colorGreen, 100);
        \VISU\D3D::cross(new Vec3(0, 0, 0), \VISU\D3D::$colorRed);
        \VISU\D3D::aabb(new Vec3(0, 0, 0), new Vec3(10, 10, 10), new Vec3(60, 80, 50), \VISU\D3D::$colorMagenta);

    }
}
