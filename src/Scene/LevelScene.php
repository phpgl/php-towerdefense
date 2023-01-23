<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Debug\DebugTextOverlay;
use TowerDefense\Renderer\TerrainRenderer;
use TowerDefense\System\AircraftSystem;
use TowerDefense\System\AnimationSystem;
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
use VISU\System\VISUCameraSystem;
use VISU\System\VISULowPoly\LPObjLoader;
use VISU\System\VISULowPoly\LPRenderingSystem as VISULowPolyRenderingSystem;

class LevelScene extends BaseScene implements DevEntityPickerDelegate
{
    private TerrainRenderer $terrainRenderer;
    private VISULowPolyRenderingSystem $renderingSystem;
    private VISUCameraSystem $cameraSystem;
    private HeightmapSystem $heightmapSystem;

    private AircraftSystem $aircraftSystem;
    private AnimationSystem $animationSystem;

    private LPObjLoader $objectLoader;
    private array $loadedObjects = [];

    /**
     * Dev entity picker, for debug and level editor
     */
    private DevEntityPicker $devPicker;
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
    )
    {
        parent::__construct($container);

        // register key handler for debugging
        // usally a system should handle this but this is temporary
        $this->keyboardHandlerId = $this->container->resolveVisuDispatcher()->register('input.key', [$this, 'handleKeyboardEvent']);

        // load all space kit models
        $this->objectLoader = new LPObjLoader($container->resolveGL());
        $this->loadedObjects = $this->objectLoader->loadAllInDirectory(VISU_PATH_RESOURCES . '/models/spacekit');

        // prepare the rendering systems
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL(), $container->resolveShaders());
        $this->renderingSystem = new VISULowPolyRenderingSystem($container->resolveGL(), $container->resolveShaders());
        $this->renderingSystem->addGeometryRenderer($this->terrainRenderer);

        $this->cameraSystem = new VISUCameraSystem(
            $this->container->resolveInput(),
            $this->container->resolveVisuDispatcher()
        );

        $this->animationSystem = new AnimationSystem(60); // @TODO get from game loop
        $this->aircraftSystem = new AircraftSystem($this->loadedObjects);
        $this->heightmapSystem = new HeightmapSystem($this->container->resolveGL());

        $this->constructDevEntityPicker();
    }

    /**
     * Constrcuts the DEV entity picker
     */
    public function constructDevEntityPicker() : void
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
        $this->devPicker->enabled = true;
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        // unregister systems
        $this->aircraftSystem->unregister($this->entities);
        $this->animationSystem->unregister($this->entities);
        $this->cameraSystem->unregister($this->entities);
        $this->renderingSystem->unregister($this->entities);

        // unbind event handlers
        $this->container->resolveVisuDispatcher()->unregister('input.key', $this->keyboardHandlerId);
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
        $this->animationSystem->register($this->entities);
        $this->aircraftSystem->register($this->entities);

        // create a camera
        $cameraEntity = $this->entities->create();
        $camera = $this->entities->attach($cameraEntity, new Camera(CameraProjectionMode::perspective));
        $camera->transform->position->y = 250;
        $camera->transform->position->z = 500;
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

        // add aircraft
        $initialPosition = new Vec3(0.0, 250.0, -250.0);
        $initialOrientation = new Quat();
        $initialOrientation->rotate(GLM::radians(-90.0), new Vec3(0.0, 1.0, 0.0));
        $this->aircraftSystem->spawnAircraft($this->entities, $initialPosition->copy(), $initialOrientation->copy());
    }

    /**
     * Loads resources required for the scene
     *
     * @return void
     */
    public function load() : void
    {
        // load the terrain
        $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RESOURCES . '/terrain/alien_planet/alien_planet_terrain.obj');
        
        // prepare the scene 
        $this->prepareScene();

        // create a heightmap from the scene
        $this->heightmapSystem->caputreHeightmap(
            $this->entities,
            [
                $this->terrainRenderer
            ]
        );
    }

    /**
     * Updates the scene state
     *
     * @return void
     */
    public function update() : void
    {
        $this->cameraSystem->update($this->entities);
        $this->aircraftSystem->update($this->entities);
        $this->animationSystem->update($this->entities);
    }

    /**
     * Renders the scene
     *
     * @param RenderContext $context
     */
    public function render(RenderContext $context) : void
    {
        DebugTextOverlay::debugString("Press 'SHIFT' + NUM for render debug: NONE=0, POS=1, VPOS=2, NORM=3, DEPTH=4, ALBEDO=5");

        $this->aircraftSystem->render($this->entities, $context);

        $this->animationSystem->render($this->entities, $context);

        $this->cameraSystem->render($this->entities, $context);

        $backbuffer = $context->data->get(BackbufferData::class);

        $this->renderingSystem->setRenderTarget($backbuffer->target);
        $this->renderingSystem->render($this->entities, $context);
        $this->heightmapSystem->render($this->entities, $context);
    }

    /**
     * Keyboard event handler
     */
    public function handleKeyboardEvent(KeySignal $signal) : void
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
        var_dump($entityId);
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
