<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\Vec3;
use TowerDefense\Component\HeightmapComponent;
use TowerDefense\Debug\DebugTextOverlay;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\D3D;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Key;
use VISU\OS\MouseButton;
use VISU\Signals\Input\KeySignal;
use VISU\Signals\Input\MouseClickSignal;

class LevelEditorScene extends LevelScene
{
    /**
     * The currently selected entity
     */
    private int $selectedEntity = 0;

    /**
     * Returns the scenes name
     */
    public function getName() : string
    {
        return 'TowerDefense Level [EDITOR]';
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

        // if an entity is selected and has a transform component
        // we update the position until the user clicks the mouse button again
        if ($this->entities->valid($this->selectedEntity) && $this->entities->has($this->selectedEntity, Transform::class)) 
        {
            $transform = $this->entities->get($this->selectedEntity, Transform::class);

            // update the position to heightmap cursor position
            $transform->position = $heightmapComponent->cursorWorldPosition->copy();

            if ($inputContext->actions->isButtonDown('rotate_left')) {
                $transform->orientation->rotate(0.01, new Vec3(0, 1, 0));
            } else if ($inputContext->actions->isButtonDown('rotate_right')) {
                $transform->orientation->rotate(-0.01, new Vec3(0, 1, 0));
            }

            if ($inputContext->actions->isButtonDown('scale_up')) {
                $transform->scale += 0.1;
            } else if ($inputContext->actions->isButtonDown('scale_down')) {
                $transform->scale -= 0.1;
            }

            $transform->markDirty();

            // copy
            if ($inputContext->actions->didButtonRelease('copy')) {
                $copy = $this->entities->create();
                foreach($this->entities->components($this->selectedEntity) as $component) {
                    $this->entities->attach($copy, clone $component);
                }
                $this->selectedEntity = $copy;
            }

            // delete 
            if ($inputContext->actions->didButtonRelease('delete')) {
                $this->entities->destroy($this->selectedEntity);
                $this->deselectEntity();
            }


            if ($inputContext->actions->didButtonRelease('place')) {
                $this->deselectEntity();
            }
        }
        else {
            $this->deselectEntity();
        }
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

        // hightlight the selected entity
        // by rendering an AABB around it
        if ($this->entities->valid($this->selectedEntity)) 
        {
            DebugTextOverlay::debugString("Entity {$this->selectedEntity} selected, press '.' & ',' to rotate, scale with 'k' & 'l', copy with 'c', delete with 'backspace'");

            if (
                $this->entities->has($this->selectedEntity, DynamicRenderableModel::class) && 
                $this->entities->has($this->selectedEntity, Transform::class)
            ) {
                $renderable = $this->entities->get($this->selectedEntity, DynamicRenderableModel::class);
                $transform = $this->entities->get($this->selectedEntity, Transform::class);

                foreach($renderable->model->meshes as $mesh) {
                    D3D::aabb($transform->position, $mesh->aabb->min * $transform->scale, $mesh->aabb->max * $transform->scale, D3D::$colorGreen);
                }
            }
        }
    }

    private function deselectEntity()
    {
        $this->selectedEntity = 0;
        $this->devPicker->enabled = true;
        $this->container->resolveInputContext()->switchToNext('level_editor');
    }

    private function selectEntity(int $entityId)
    {
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
        
        echo "Entity selected: $entityId\n";
        $this->selectEntity($entityId);
    }
}