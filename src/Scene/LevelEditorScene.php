<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\Vec3;
use TowerDefense\Component\HeightmapComponent;
use TowerDefense\Component\LevelSceneryComponent;
use TowerDefense\Debug\DebugTextOverlay;
use VISU\Component\Dev\GizmoComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\D3D;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\OS\Logger;
use VISU\Runtime\DebugConsole;
use VISU\Signals\Input\DropSignal;
use VISU\Signals\Runtime\ConsoleCommandSignal;
use VISU\System\Dev\GizmoEditorSystem;

class LevelEditorScene extends LevelScene
{
    /**
     * The currently selected entity
     */
    private int $selectedEntity = 0;

    /**
     * Enables / disables snapping
     */
    private bool $snappingEnabled = false;

    /**
     * The current snapping grid, 0.0 = no snapping
     */
    private float $snappingGrid = 0.0;

    /**
     * Systems
     */
    private GizmoEditorSystem $gizmoEditorSystem;

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

        // enable the dev picker
        $this->devPicker->enabled = true;

        // switch to the editor input context
        $this->container->resolveInputContext()->switchTo('level_editor');

        // add a dev gizmo system
        $this->gizmoEditorSystem = new GizmoEditorSystem(
            $container->resolveGL(),
            $container->resolveShaders(),
            $container->resolveVisuDispatcher(),
            $container->resolveInput()
        );

        // bind additional systems
        $this->bindSystems([
            $this->gizmoEditorSystem,
        ]);


        // listen for editor signals
        $this->container->resolveVisuDispatcher()->register(DebugConsole::EVENT_CONSOLE_COMMAND, function(ConsoleCommandSignal $signal) 
        {
            if (!$signal->isAction('editor.spawn')) {
                return;
            }

            if (!$objName = $signal->commandParts[1] ?? null) {
                $signal->console->writeLine('Usage: editor.spawn <object_name>');
                return;
            }

            if (!isset($this->loadedObjects[$objName])) {
                $signal->console->writeLine('Object not found: ' . $objName);
                return;
            }

            $newObj = $this->entities->create();
            $renderable = $this->entities->attach($newObj, new DynamicRenderableModel);
            $renderable->model = $this->loadedObjects[$objName]; // <- render the turret
            $transform = $this->entities->attach($newObj, new Transform);
            $transform->scale = $transform->scale * 10;
           
            $this->selectedEntity = $newObj;
        });

        // listen for file drop events
        $this->container->resolveVisuDispatcher()->register(Input::EVENT_DROP, function(DropSignal $signal) 
        {
            if (count($signal->paths) !== 1) {
                Logger::error('Only one file can be dropped at a time');
                return;
            }

            // get the basename of the file, as our model files are currently all in one directory
            $objName = basename($signal->paths[0]);

            // check if its registered model file
            $modelCollection = $this->container->resolveModels();
            if (!$modelCollection->has($objName)) {
                Logger::error('Model not found in current collection: ' . $objName . ' (did you forget to add it to the collection before dropping it?)');
                return;
            }

            $droppedObj = $this->entities->create();

            // attach the model component
            $renderable = $this->entities->attach($droppedObj, new DynamicRenderableModel);
            $renderable->modelIdentifier = $objName;
            
            // attach a transform component and move it to the cursor position
            $transform = $this->entities->attach($droppedObj, new Transform);
            $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
            $transform->position = $heightmapComponent->cursorWorldPosition->copy();

            // make the object part of the scenery
            $this->entities->attach($droppedObj, new LevelSceneryComponent);

            // select the dropped object
            $this->devEntityPickerDidSelectEntity($droppedObj);
        });
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

        // create some random transform to move around
        // $entity = $this->entities->firstWith(DynamicRenderableModel::class);
        // $this->entities->attach($entity, new GizmoComponent);
    }

    /**
     * Updates the scene state
     * This is the place where you should update your game state.
     * @return void 
     */
    public function update() : void
    {
        parent::update();

        $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);

        // get input context
        $inputContext = $this->container->resolveInputContext();
        
        // always enable raycasting
        $heightmapComponent->enableRaycasting = true;

        // should we rebuild the heightmap?
        if ($inputContext->actions->didButtonPress('rebuild_heightmap')) {
            $heightmapComponent->requiresCapture = true;
        }

        // should we enable / disable snapping?
        if ($inputContext->actions->didButtonPress('toggle_snapping')) {
            $this->snappingEnabled = !$this->snappingEnabled;
        }
        
        // adjust the snapping grid
        if ($this->snappingEnabled) {
            if ($inputContext->actions->isButtonDown('fine_change')) {
                $this->snappingGrid = 0.1;
            } else {
                $this->snappingGrid = 1.0;
            }
        } else {
            $this->snappingGrid = 0.0;
        }

        // if an entity is selected and has a transform component
        // we update the position until the user clicks the mouse button again
        if ($this->entities->valid($this->selectedEntity) && $this->entities->has($this->selectedEntity, Transform::class)) 
        {
            // if an entity is selected ensure it has a gizmo attached to it 
            if (!$this->entities->has($this->selectedEntity, GizmoComponent::class)) {
                $this->entities->attach($this->selectedEntity, new GizmoComponent);
            }

            // be sure to copy the snapping state 
            // to the gizmo component
            $gizmoComponent = $this->entities->get($this->selectedEntity, GizmoComponent::class);
            $gizmoComponent->snapGrid = $this->snappingGrid;

            $transform = $this->entities->get($this->selectedEntity, Transform::class);

            // update the position to heightmap cursor position
            // when height placements are requested
            if ($inputContext->actions->isButtonDown('place_height')) {
                $transform->position = $heightmapComponent->cursorWorldPosition->copy();
            }

            // rotate 
            if ($inputContext->actions->isButtonDown('rotate_left')) {
                if ($inputContext->actions->isButtonDown('fine_change')) {
                    $transform->orientation->rotate(0.01, new Vec3(0, 1, 0));
                } else {
                    $transform->orientation->rotate(0.1, new Vec3(0, 1, 0));
                }
            } else if ($inputContext->actions->isButtonDown('rotate_right')) {
                if ($inputContext->actions->isButtonDown('fine_change')) {
                    $transform->orientation->rotate(-0.01, new Vec3(0, 1, 0));
                } else {
                    $transform->orientation->rotate(-0.1, new Vec3(0, 1, 0));
                }
            }

            // scale
            if ($inputContext->actions->isButtonDown('scale_up')) {
                if ($inputContext->actions->isButtonDown('fine_change')) {
                    $transform->scale += 0.01;
                } else {
                    $transform->scale += 0.1;
                }
            } else if ($inputContext->actions->isButtonDown('scale_down')) {
                if ($inputContext->actions->isButtonDown('fine_change')) {
                    $transform->scale -= 0.01;
                } else {
                    $transform->scale -= 0.1;
                }
            }

            $transform->markDirty();

            // if snapping is enabled we snap the position to the grid
            if ($this->snappingEnabled) {
                $transform->position->x = round($transform->position->x / $this->snappingGrid) * $this->snappingGrid;
                $transform->position->y = round($transform->position->y / $this->snappingGrid) * $this->snappingGrid;
                $transform->position->z = round($transform->position->z / $this->snappingGrid) * $this->snappingGrid;
            }

            // copy
            if ($inputContext->actions->didButtonRelease('copy')) {
                $copy = $this->entities->create();
                foreach($this->entities->components($this->selectedEntity) as $component) {
                    $this->entities->attach($copy, clone $component);
                }
                $this->devEntityPickerDidSelectEntity($copy);
            }

            // delete 
            if ($inputContext->actions->didButtonRelease('delete')) {
                $this->entities->destroy($this->selectedEntity);
                $this->deselectEntity();
            }

            // place down aka realease
            if ($inputContext->actions->didButtonRelease('place')) {
                $this->deselectEntity();
            }
        }
        else {
            $this->deselectEntity();
        }

        // update systems
        $this->gizmoEditorSystem->update($this->entities);
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

        // display level editor help
        DebugTextOverlay::debugString("Level Editor commands:");
        DebugTextOverlay::debugString(" - Rebuild Heightmap: press 'm'");
        DebugTextOverlay::debugString(" - Enable/Disable Grid Snapping: press 'b'" . ($this->snappingEnabled ? " (enabled)" : " (disabled)"));

        // draw systems
        $this->gizmoEditorSystem->render($this->entities, $context);

        static $origin = new Vec3(50, 00, 50);
        static $p0 = new Vec3(0, 0, 0);
        static $dest = new Vec3(200, 50, 50);

        if ($this->container->resolveInput()->isKeyPressed(Key::O)) {
            $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
            $origin = $heightmapComponent->cursorWorldPosition->copy();
        }

        if ($this->container->resolveInput()->isKeyPressed(Key::P)) {
            $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
            $dest = $heightmapComponent->cursorWorldPosition->copy();
        }

        if ($this->container->resolveInput()->isKeyPressed(Key::I)) {
            $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
            $p0 = $heightmapComponent->cursorWorldPosition->copy();
        }


        D3D::bezier(
            $origin,
            $p0,
            $dest,
            D3D::$colorRed
        );

        D3D::cross($origin, D3D::$colorGreen, 5);
        D3D::cross($dest, D3D::$colorGreen, 5);

        $this->roadRenderer->tmpOrigin = $origin;
        $this->roadRenderer->tmpP0 = $p0;
        $this->roadRenderer->tmpDest = $dest;

        // get the model collection 
        $modelCollection = $this->container->resolveModels();

        // hightlight the selected entity
        // by rendering an AABB around it
        if ($this->entities->valid($this->selectedEntity)) 
        {
            DebugTextOverlay::debugString("Entity {$this->selectedEntity} selected");
            DebugTextOverlay::debugString(" - Rotate: press '.' & ','");
            DebugTextOverlay::debugString(" - Follow Terrain: hold 'f'");
            DebugTextOverlay::debugString(" - Scale: press 'k' & 'l'");
            DebugTextOverlay::debugString(" - Copy: press 'c'");
            DebugTextOverlay::debugString(" - Delete: press 'backspace'");

            if (
                $this->entities->has($this->selectedEntity, DynamicRenderableModel::class) && 
                $this->entities->has($this->selectedEntity, Transform::class)
            ) {
                $renderable = $this->entities->get($this->selectedEntity, DynamicRenderableModel::class);
                $transform = $this->entities->get($this->selectedEntity, Transform::class);

                $model = $modelCollection->get($renderable->modelIdentifier);

                DebugTextOverlay::debugString(
                    sprintf(
                        'Entity Dimensions: ' .
                        'width: %.2fm ' .
                        'height: %.2fm ' .
                        'depth: %.2fm',
                        $model->aabb->width() * $transform->scale->x,
                        $model->aabb->height() * $transform->scale->y,
                        $model->aabb->depth() * $transform->scale->z
                    )
                );

                D3D::aabb($transform->position, $model->aabb->min * $transform->scale, $model->aabb->max * $transform->scale, D3D::$colorGreen);
            }
        }
    }

    private function deselectEntity()
    {
        // remove gizmo components from the selected one
        $this->entities->detach($this->selectedEntity, GizmoComponent::class);
        
        $this->selectedEntity = 0;
        $this->devPicker->enabled = true;
        $this->container->resolveInputContext()->switchToNext('level_editor');
    }

    private function selectEntity(int $entityId)
    {
        // remove any gizmo components on the last entity
        $this->entities->detach($this->selectedEntity, GizmoComponent::class);

        $this->selectedEntity = $entityId;
        $this->devPicker->enabled = false;
        $this->container->resolveInputContext()->switchToNext('level_editor.selected');
    }

    /**
     * Called when the dev entity picker has selected an entity
     * 
     * @param int $entityId 
     * @return void 
     */
    public function devEntityPickerDidSelectEntity(int $entityId): void
    {
        if (!$this->entities->valid($entityId)) {
            $this->deselectEntity();
            return;
        }
        
        Logger::info("Entity selected: {$entityId}");
        $this->selectEntity($entityId);
    }
}