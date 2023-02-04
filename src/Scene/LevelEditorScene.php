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

        // $this->inputIntends->bind('')

        $this->container->resolveVisuDispatcher()->register('input.mouse_click', function(MouseClickSignal $signal) 
        {
            if ($this->selectedEntity !== 0) {
                $signal->stopPropagation();
                $this->selectedEntity = 0;   
                $this->devPicker->enabled = true;
            }
        }, -1);

        $this->container->resolveVisuDispatcher()->register('input.key', function(KeySignal $signal) 
        {
            if ($this->selectedEntity !== 0 && $signal->action == GLFW_RELEASE) {

                if ($signal->key === Key::ESCAPE) {
                    $signal->stopPropagation();
                    $this->selectedEntity = 0;
                    $this->devPicker->enabled = true;
                }

                if ($signal->key === Key::DELETE || $signal->key === Key::BACKSPACE) {
                    $signal->stopPropagation();
                    $this->entities->destroy($this->selectedEntity);
                    $this->selectedEntity = 0;
                    $this->devPicker->enabled = true;
                }

                // copy
                if ($signal->key === Key::C) {
                    $signal->stopPropagation();

                    $copy = $this->entities->create();
                    foreach($this->entities->components($this->selectedEntity) as $component) {
                        $this->entities->attach($copy, clone $component);
                    }

                    $this->selectedEntity = $copy;
                }
            }
        }, -1);
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

        $input = $this->container->resolveInput();
        
        // always enable raycasting
        $heightmapComponent->enableRaycasting = true;

        // if an entity is selected and has a transform component
        // we update the position until the user clicks the mouse button again
        if ($this->entities->valid($this->selectedEntity) && $this->entities->has($this->selectedEntity, Transform::class)) 
        {
            $transform = $this->entities->get($this->selectedEntity, Transform::class);

            // update the position
            $transform->position = $heightmapComponent->cursorWorldPosition->copy();

            if ($input->isKeyPressed(Key::K)) {
                $transform->scale += 0.1;
            } else if ($input->isKeyPressed(Key::L)) {
                $transform->scale -= 0.1;
            }

            if ($input->isKeyPressed(Key::COMMA)) {
                $transform->orientation->rotate(0.01, new Vec3(0, 1, 0));
            } else if ($input->isKeyPressed(Key::PERIOD)) {
                $transform->orientation->rotate(-0.01, new Vec3(0, 1, 0));
            }

            $transform->markDirty();
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

        static $currentX = 0;
        static $currentY = 100;
        if ($this->container->resolveInput()->isKeyPressed(Key::LEFT)) {
            $currentX -= 0.8;
        } else if ($this->container->resolveInput()->isKeyPressed(Key::RIGHT)) {
            $currentX += 0.8;
        }

        if ($this->container->resolveInput()->isKeyPressed(Key::UP)) {
            $currentY -= 0.8;
        } else if ($this->container->resolveInput()->isKeyPressed(Key::DOWN)) {
            $currentY += 0.8;
        }

        $origin = new Vec3(0, -50, 0);
        $p0 = new Vec3($currentY, 0, $currentX);
        $dest = new Vec3(200, 50, 0);

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

    /**
     * Called when the dev entity picker has selected an entity
     * 
     * @param int $entityId 
     * @return void 
     */
    public function devEntityPickerDidSelectEntity(int $entityId): void
    {
        if (!$this->entities->valid($entityId)) {
            $this->selectedEntity = 0;
            return;
        }
        
        echo "Entity selected: $entityId\n";

        $this->selectedEntity = $entityId;
        $this->devPicker->enabled = false;
    }
}