<?php

namespace TowerDefense\System;

use TowerDefense\Component\HeightmapComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Camera;
use VISU\Graphics\GLState;
use VISU\Graphics\Heightmap\GPUHeightmapRenderer;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\OS\Input;

class HeightmapSystem implements SystemInterface
{
    /**
     * GPU Heightmap renderer
     */
    private GPUHeightmapRenderer $heightmapRenderer;

    /**
     * Last frame render target
     */
    private RenderTarget $lastFrameRenderTarget;

    /**
     * Constructor
     * 
     * @param array<GPUHeightmapGeometryPassInterface> $heightGeometryProducers 
     */
    public function __construct(
        private GLState $gl,
        private Input $input,
        private array $heightGeometryProducers
    )
    {
        $this->heightmapRenderer = new GPUHeightmapRenderer($gl, 4096, 4096);
        $this->heightmapRenderer->ppu = 2.0;
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        $entities->setSingleton(new HeightmapComponent);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        $entities->removeSingleton(HeightmapComponent::class);
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
        $heightmapComponent = $entities->getSingleton(HeightmapComponent::class);

        if ($heightmapComponent->requiresCapture) {
            $this->caputreHeightmap($entities, $heightmapComponent);
            $heightmapComponent->requiresCapture = false;
        }

        if ($heightmapComponent->enableRaycasting) {
            // fetch the current camera (first.. @todo support multiple cameras) 
            $camera = $entities->first(Camera::class);

            // create a ray from the camera to the cursor position
            $cursorPos = $this->input->getNormalizedCursorPosition();
            $ray = $camera->getSSRay($this->lastFrameRenderTarget, $cursorPos);

            // cast the ray and update the cursor world position
            $heightmapComponent->cursorWorldPosition = $heightmapComponent->heightmap->castRay($ray);
        }
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
        $backbuffer = $context->data->get(BackbufferData::class);
        $this->lastFrameRenderTarget = $context->resources->getRenderTarget($backbuffer->target);   
    }

    /**
     * Captures the heightmap from the given height geometry producers
     * 
     * @param EntitiesInterface $entities
     */
    public function caputreHeightmap(EntitiesInterface $entities, HeightmapComponent $component) : void
    {
        $component->heightmap = $this->heightmapRenderer->caputreHeightmap($entities, $this->heightGeometryProducers);
        // var_dump($component->heightmap->getHeightAt(1000, 1000)); die;
    }
}
