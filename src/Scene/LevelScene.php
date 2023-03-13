<?php

namespace TowerDefense\Scene;

use GameContainer;
use TowerDefense\Component\HealthComponent;
use TowerDefense\Component\HeightmapComponent;
use TowerDefense\Component\LevelSceneryComponent;
use TowerDefense\Debug\DebugTextOverlay;
use TowerDefense\Renderer\BarBillboardRenderer;
use TowerDefense\Renderer\RoadRenderer;
use TowerDefense\Renderer\TerrainRenderer;
use TowerDefense\System\BarBillboardSystem;
use TowerDefense\System\CameraSystem;
use TowerDefense\System\HeightmapSystem;
use VISU\ECS\EntityRegisty;
use VISU\ECS\Picker\DevEntityPicker;
use VISU\ECS\Picker\DevEntityPickerDelegate;
use VISU\Graphics\Camera;
use VISU\Graphics\CameraProjectionMode;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\OS\FileSystem;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\Logger;
use VISU\Runtime\DebugConsole;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Runtime\ConsoleCommandSignal;
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
     * A level loader, serializes and deserializes levels
     */
    protected LevelLoader $levelLoader;

    /**
     * Current level data, this is serialized container for all* level data.
     * The entites are serialized separately!
     */
    protected LevelData $level;

    /**
     * Level initialition flag
     * To avoid unregistering the systems before they are registered and handling 
     * duplicate events etc.
     */
    protected bool $levelInitialized = false;

    /**
     * Systems
     * 
     * ------------------------------------------------------------------------
     */
    private VISULowPolyRenderingSystem $renderingSystem;
    private CameraSystem $cameraSystem;
    private HeightmapSystem $heightmapSystem;
    private BarBillboardSystem $barBillboardSystem;

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
        protected string $levelName = 'test'
    )
    {
        parent::__construct($container);

        // register key handler for debugging 
        // usally a system should handle this but this is temporary
        $this->keyboardHandlerId = $this->container->resolveVisuDispatcher()->register('input.key', [$this, 'handleKeyboardEvent']);

        // construct the level loader
        $this->levelLoader = new LevelLoader(VISU_PATH_LEVELS);

        // load all space kit models
        $objectCollection = $container->resolveModels();
        $objectLoader = $container->resolveModelsLoader();

        // load the space kit models
        // these are smaller then our unit space of 1 unit = 1 meter
        // so we scale them up by a factor of 4.0 to ~ match our environment scale
        $objectLoader->loadAllInDirectory(VISU_PATH_RESOURCES . '/models/spacekit', $objectCollection, 4.0);

        // prepare the rendering systems 
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL(), $container->resolveShaders());
        $this->roadRenderer = new RoadRenderer($container->resolveGL(), $container->resolveShaders());
        $this->renderingSystem = new VISULowPolyRenderingSystem(
            $container->resolveGL(), 
            $container->resolveShaders(),
            $objectCollection
        );
        $this->renderingSystem->addGeometryRenderer($this->terrainRenderer);
        $this->renderingSystem->addGeometryRenderer($this->roadRenderer);

        // billboard system
        $this->barBillboardSystem = new BarBillboardSystem($container->resolveGL(), $objectCollection);

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
            $this->barBillboardSystem,
        ]);

        // construct the dev entity picker
        $this->constructDevEntityPicker();

        // register console command
        $this->registerConsoleCommands();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // unregister systems
        if ($this->levelInitialized) $this->unregisterSystems();

        // unbind event handlers
        $this->container->resolveVisuDispatcher()->unregister('input.key', $this->keyboardHandlerId);
    }

    /**
     * Constrcuts the DEV entity picker
     */
    private function constructDevEntityPicker() : void
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
     * Registers the console commmand handler, for level scene specific commands
     */
    private function registerConsoleCommands()
    {
        $this->container->resolveVisuDispatcher()->register(DebugConsole::EVENT_CONSOLE_COMMAND, function(ConsoleCommandSignal $signal) 
        {
            // print the help dialog if no command is given
            if ($signal->isAction('level')) {
                $signal->console->writeLine('Level commands: [level.save] [level.switch <level_name>] [level.terrain <terrain_path>]');
            } elseif ($signal->isAction('level.save')) {
                $this->handleConsoleCommandLevelSave($signal);
            } elseif ($signal->isAction('level.switch')) {
                $this->handleConsoleCommandLevelSwitch($signal);
            } elseif ($signal->isAction('level.terrain')) {
                $this->handleConsoleCommandLevelTerrain($signal);
            }
        });
    }

    /**
     * "level.save" handler
     */
    private function handleConsoleCommandLevelSave(ConsoleCommandSignal $signal) : void
    {
        $signal->console->writeLine('Saving level "' . $this->levelName . '"');
        $this->saveLevel();
    }

    /**
     * "level.switch" handler
     */
    private function handleConsoleCommandLevelSwitch(ConsoleCommandSignal $signal) : void
    {
        $levelName = $signal->commandParts[1] ?? null;

        if (!$levelName) {
            $signal->console->writeLine('Missing level name! Usage: level.switch <level_name>');
            return;
        }

        $signal->console->writeLine('Switching to level "' . $levelName . '"');

        // write a warning if the level does not exist
        if (!$this->levelLoader->exists($levelName)) {
            $signal->console->writeLine('Level "' . $levelName . '" does not exist, creating it now...');
        }

        $this->changeLevel($levelName);
    }

    /**
     * "level.terrain" handler
     */
    private function handleConsoleCommandLevelTerrain(ConsoleCommandSignal $signal) : void
    {
        $terrainFile = $signal->commandParts[1] ?? null;

        if (!$terrainFile) {
            $signal->console->writeLine('Missing terrain file! Usage: level.terrain <terrain_path>');
            return;
        }

        if (!file_exists(VISU_PATH_RES_TERRAIN . $terrainFile) || !is_file(VISU_PATH_RES_TERRAIN . $terrainFile)) {
            $signal->console->writeLine('Terrain file "' . $terrainFile . '" does not exist!');
            return;
        }

        // load the actual terrain file and store it in the level data
        $signal->console->writeLine('Loading terrain "' . $terrainFile . '"...');
        $this->level->terrainFileName = $terrainFile;
        $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RES_TERRAIN . $this->level->terrainFileName);

        // ensure that we capture a new heightmap
        $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
        $heightmapComponent->requiresCapture = true;
    }

    /**
     * Loads resources required for the scene, prepere base entiteis 
     * basically prepare anything that is required to be ready before the scene is rendered.
     * 
     * @return void 
     */
    public function load(): void
    {
        // if the inital level exists load it from disk and user the 
        // load level initializtion
        if ($this->levelLoader->exists($this->levelName)) {
            $this->loadLevel($this->levelName);
        }
        // if the level does not exist we have to initalize our level manually
        else {
            $this->level = new LevelData;
            $this->level->name = $this->levelName;
            $this->initalizeLevel();
        }
    }

    /**
     * Create the level load options specifically for this scenes implementation
     */
    private function createLevelLoadOptions(?string $levelName = null) : LevelLoaderOptions
    {
        $options = $this->levelLoader->createOptions($levelName ?? $this->levelName);

        // we want to filter out all entities that are not part of the level scenery
        $options->serialisationFilterComponent = LevelSceneryComponent::class;

        return $options;
    }

    /**
     * Save the scene state into the current level file 
     */
    public function saveLevel() : void
    {
        $this->levelLoader->serialize($this->entities, $this->level, $this->createLevelLoadOptions());
    }

    /**
     * Loads the current level
     */
    public function loadLevel() : void
    {
        // unregister all systems before loading the level 
        // its important that all system properly implement the register/unregister callback
        // so that resources are properly freed / allocated when switching levels or scenes.
        // Its important the unregisterSystems can be called in any situation, even multiple times
        // in a row, so the implementation of unregisterSystems must be idempotent.
        if ($this->levelInitialized) $this->unregisterSystems();

        // then we use the level load to deserialize the level into the entity registry and 
        // the required level data
        $this->level = $this->levelLoader->deserialize($this->entities, $this->createLevelLoadOptions());

        // then we continue with the normal level initalization
        // which will alos register the systems again.
        $this->initalizeLevel();
    }

    /**
     * Changes the level to the level with the given name.
     * If the level does not exists it will be created first.
     */
    public function changeLevel(string $levelName) : void
    {
        // check if the level can be loaded 
        // if that is not the case we simply serialize an empty level with that name 
        if (!$this->levelLoader->exists($levelName)) 
        {
            $data = new LevelData;
            $data->name = ucfirst($levelName);
            $this->levelLoader->serialize(new EntityRegisty, $data, $this->createLevelLoadOptions($levelName));
        }

        // change the name to the requested level and load it
        $this->levelName = $levelName;
        $this->loadLevel();
    }

    /**
     * Initalizes all resources required for the level
     * This is the place where you should create non serializable entities,
     * level specific stuff etc.
     */
    public function initalizeLevel() : void
    {
        // register scene components
        $this->entities->registerComponent(LevelSceneryComponent::class);

        // load the terrain from the level data
        if ($this->level->terrainFileName === null || !file_exists(VISU_PATH_RES_TERRAIN . $this->level->terrainFileName)) {
            Logger::warn('Level "' . $this->level->name . '" does not have a terrain file, using flat terrain.');
            $this->terrainRenderer->createFlatTerrain(128, 128, 16); // 2048x2048
        } else {
            if (!FileSystem::isPathInDirectory(VISU_PATH_RES_TERRAIN . $this->level->terrainFileName, VISU_PATH_RES_TERRAIN)) {
                throw new \RuntimeException('Terrain file path is not allowed!');
            }

            $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RES_TERRAIN . $this->level->terrainFileName);
        }

        // load basic road
        $this->roadRenderer->loadRoad(VISU_PATH_RESOURCES . '/models/road/basic.obj');
        
        // register the systems
        $this->registerSystems();

        // create a camera
        $cameraEntity = $this->entities->create();
        $camera = $this->entities->attach($cameraEntity, new Camera(CameraProjectionMode::perspective));
        $camera->transform->position->y = 100;
        $camera->transform->position->z = 50;
        $this->cameraSystem->setActiveCameraEntity($cameraEntity);

        // mark the level as initialized
        $this->levelInitialized = true;
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
        $this->barBillboardSystem->render($this->entities, $context);
    }

    /**
     * Keyboard event handler
     */
    public function handleKeyboardEvent(KeySignal $signal): void
    {
        // if shift modifier is active
        if ($signal->isShiftDown() === false || $signal->action !== Input::PRESS) {
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
