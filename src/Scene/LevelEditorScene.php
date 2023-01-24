<?php

namespace TowerDefense\Scene;

use GameContainer;
use TowerDefense\Component\HeightmapComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\D3D;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

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
     * 
     * @return void 
     */
    public function update() : void
    {
        parent::update();

        $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
        
        // always enable raycasting
        $heightmapComponent->enableRaycasting = true;

        // if an entity is selected and has a transform component
        // we update the position until the user clicks the mouse button again
        if ($this->entities->valid($this->selectedEntity) && $this->entities->has($this->selectedEntity, Transform::class)) 
        {
            $transform = $this->entities->get($this->selectedEntity, Transform::class);

            // update the position
            $transform->position = $heightmapComponent->cursorWorldPosition->copy();
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

        // hightlight the selected entity
        // by rendering an AABB around it
        if ($this->entities->valid($this->selectedEntity)) 
        {
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
    }
}