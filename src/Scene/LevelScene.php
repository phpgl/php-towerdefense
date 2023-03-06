<?php

namespace TowerDefense\Scene;

use GameContainer;
use TowerDefense\Debug\DebugTextOverlay;
use TowerDefense\Renderer\BarBillboardRenderer;
use TowerDefense\Renderer\RoadRenderer;
use TowerDefense\Renderer\TerrainRenderer;
use TowerDefense\System\CameraSystem;
use TowerDefense\System\HeightmapSystem;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\Picker\DevEntityPicker;
use VISU\ECS\Picker\DevEntityPickerDelegate;
use VISU\Geo\Transform;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\OS\Key;
use VISU\Signals\Input\KeySignal;
use VISU\System\VISULowPoly\LPModel;
use VISU\System\VISULowPoly\LPObjLoader;
use VISU\System\VISULowPoly\LPRenderingSystem as VISULowPolyRenderingSystem;

abstract class LevelScene extends BaseScene implements DevEntityPickerDelegate
{
    /**
     * Terrain renderer
     */
    private TerrainRenderer $terrainRenderer;

    /**
     * Road renderer
     */
    protected RoadRenderer $roadRenderer;

    /**
     * Billboard renderer
     */
    protected BarBillboardRenderer $barBillboardRenderer;

    /**
     * Systems
     * 
     * ------------------------------------------------------------------------
     */
    private VISULowPolyRenderingSystem $renderingSystem;
    private CameraSystem $cameraSystem;
    private HeightmapSystem $heightmapSystem;

    /**
     * Object loader (3D models)
     */
    private LPObjLoader $objectLoader;

    /**
     * Loaded objects
     * 
     * @var array<string, LPModel>
     */
    protected array $loadedObjects = [];

    /**
     * Dev entity picker, for debug and level editor
     */
    protected DevEntityPicker $devPicker;
    private RenderTarget $devPickerRenderTarget;

    /**
     * Function ID for keyboard handler
     */
    private int $keyboardHandlerId = 0;

    /**
     * Constructor
     */
    public function __construct(
        protected GameContainer $container,
    ) {
        parent::__construct($container);

        // register key handler for debugging 
        // usally a system should handle this but this is temporary
        $this->keyboardHandlerId = $this->container->resolveVisuDispatcher()->register('input.key', [$this, 'handleKeyboardEvent']);

        // load all space kit models
        $this->objectLoader = new LPObjLoader($container->resolveGL());
        $this->loadedObjects = $this->objectLoader->loadAllInDirectory(VISU_PATH_RESOURCES . '/models/spacekit');

        // prepare the rendering systems 
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL(), $container->resolveShaders());
        $this->roadRenderer = new RoadRenderer($container->resolveGL(), $container->resolveShaders());
        $this->barBillboardRenderer = new BarBillboardRenderer($container->resolveGL());
        $this->renderingSystem = new VISULowPolyRenderingSystem($container->resolveGL(), $container->resolveShaders());
        $this->renderingSystem->addGeometryRenderer($this->terrainRenderer);
        $this->renderingSystem->addGeometryRenderer($this->roadRenderer);

        // basic camera system
        $this->cameraSystem = new CameraSystem(
            $this->container->resolveInput(),
            $this->container->resolveInputContext(),
            $this->container->resolveVisuDispatcher()
        );

        // heightmap system (this is not the terrain)
        $this->heightmapSystem = new HeightmapSystem(
            $this->container->resolveGL(),
            $this->container->resolveInput(),
            [
                $this->terrainRenderer
            ]
        );

        // bind all systems to the scene itself
        $this->bindSystems([
            $this->cameraSystem,
            $this->renderingSystem,
            $this->heightmapSystem,
        ]);

        // construct the dev entity picker
        $this->constructDevEntityPicker();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // unregister systems
        $this->unregisterSystems();

        // unbind event handlers
        $this->container->resolveVisuDispatcher()->unregister('input.key', $this->keyboardHandlerId);
    }

    /**
     * Constrcuts the DEV entity picker
     */
    public function constructDevEntityPicker(): void
    {
        // right now using the window framebuffer as render target
        // for entity picking, we need to test if is more efficient
        // to use a separate framebuffer for this, that could have a much 
        // lower resolution
        $this->devPickerRenderTarget = $this->container->resolveWindowMain()->getRenderTarget();

        // not to happy with the dependency on the rendering system here
        // but I tried a few architectures now and did not want to waste 
        // more time on this...
        $this->devPicker = new DevEntityPicker(
            $this,
            $this->entities,
            $this->container->resolveVisuDispatcher(),
            $this->devPickerRenderTarget,
            [
                $this->renderingSystem,
            ]
        );

        // we initate a dev entity picker by default but keep it disabled
        // unitl explicity requetsed otherwise
        $this->devPicker->enabled = false;
    }

    /**
     * Loads resources required for the scene, prepere base entiteis 
     * basically prepare anything that is required to be ready before the scene is rendered.
     * 
     * @return void 
     */
    public function load(): void
    {
        // load the terrain
        $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RESOURCES . '/terrain/alien_planet/alien_planet_terrain.obj');

        // load basic road
        $this->roadRenderer->loadRoad(VISU_PATH_RESOURCES . '/models/road/basic.obj');

        // register systems
        $this->registerSystems();

        // prepare the scene 
        $this->prepareScene();
    }

    /**
     * Create required entites for the scene
     */
    private function prepareScene()
    {
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
        $transform->scale = $transform->scale * 10;
        $transform->position->y = 55;
        $transform->position->z = -50;

        // create a sattelie dish "satelliteDish_large.obj"
        $dishObject = $this->entities->create();
        $renderable = $this->entities->attach($dishObject, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['satelliteDish_large.obj'];
        $transform = $this->entities->attach($dishObject, new Transform);
        $transform->scale = $transform->scale * 10;
        $transform->position->y = 0;
        $transform->position->x = 10;
        $transform->position->z = 10;

        $transform->setParent($this->entities, $someObject);

        // create a racer craft
        $newObj = $this->entities->create();
        $renderable = $this->entities->attach($newObj, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_racer.obj']; // <- render the turret
        $transform = $this->entities->attach($newObj, new Transform);
        $transform->scale = $transform->scale * 5.0;
        $transform->position->z = -50.0;
        $transform->position->y = 10.0;
    }

    /**
     * Updates the scene state
     * This is the place where you should update your game state.
     * 
     * @return void 
     */
    public function update(): void
    {
        $this->cameraSystem->update($this->entities);
        $this->heightmapSystem->update($this->entities);
    }

    /**
     * Renders the scene
     * This is the place where you should render your game state.
     * 
     * @param RenderContext $context
     */
    public function render(RenderContext $context): void
    {
        DebugTextOverlay::debugString("Press 'SHIFT' + NUM for render debug: NONE=0, POS=1, VPOS=2, NORM=3, DEPTH=4, ALBEDO=5");

        $this->cameraSystem->render($this->entities, $context);
        $this->heightmapSystem->render($this->entities, $context);

        // let the rendering system handle the rest
        $backbuffer = $context->data->get(BackbufferData::class);
        $this->renderingSystem->setRenderTarget($backbuffer->target);
        $this->renderingSystem->render($this->entities, $context);

        // render bar billboards (they are some kind of ui element)
        $this->barBillboardRenderer->render($this->entities, $context);
    }

    /**
     * Keyboard event handler
     */
    public function handleKeyboardEvent(KeySignal $signal): void
    {
        if ($signal->mods !== Key::MOD_SHIFT && $signal->action == GLFW_PRESS) {
            return;
        }

        if ($signal->key === KEY::NUM_0) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_NONE;
        } elseif ($signal->key === KEY::NUM_1) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_POSITION;
        } elseif ($signal->key === KEY::NUM_2) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_VIEW_POSITION;
        } elseif ($signal->key === KEY::NUM_3) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_NORMALS;
        } elseif ($signal->key === KEY::NUM_4) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_DEPTH;
        } elseif ($signal->key === KEY::NUM_5) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_ALBEDO;
        } elseif ($signal->key === KEY::NUM_6) {
            $this->renderingSystem->debugMode = VISULowPolyRenderingSystem::DEBUG_MODE_SSAO;
        }
    }

    /**
     * Called when the dev entity picker has selected an entity
     * 
     * @param int $entityId 
     * @return void 
     */
    public function devEntityPickerDidSelectEntity(int $entityId): void
    {
    }

    /**
     * Called when the dev entity picker is about to initate a selection and requires 
     * the delegate to return the current camera data
     * 
     * @return CameraData
     */
    public function devEntityPickerRequestsCameraData(): CameraData
    {
        return $this->cameraSystem->getCameraData(
            $this->entities,
            $this->devPickerRenderTarget,
            0
        );
    }
}
