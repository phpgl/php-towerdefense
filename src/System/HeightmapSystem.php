<?php

namespace TowerDefense\System;

use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\Heightmap\GPUHeightmapGeometryPassInterface;
use VISU\Graphics\Heightmap\GPUHeightmapRenderer;
use VISU\Graphics\Rendering\RenderContext;

class HeightmapSystem implements SystemInterface
{
    /**
     * GPU Heightmap renderer
     */
    private GPUHeightmapRenderer $heightmapRenderer;

    /**
     * Constructor
     */
    public function __construct(
        private GLState $gl,
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
       
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities) : void
    {
    }

    /**
     * Handles rendering of the scene, here you can attach additional render passes,
     * modify the render pipeline or customize rendering related data.
     * 
     * @param RenderContext $context
     */
    public function render(EntitiesInterface $entities, RenderContext $context) : void
    {
    }

    /**
     * Captures the heightmap from the given height geometry producers
     * 
     * @param EntitiesInterface $entities
     * @param array<GPUHeightmapGeometryPassInterface> $heightGeometryProducers
     */
    public function caputreHeightmap(EntitiesInterface $entities, array $heightGeometryProducers) : void
    {
        $this->heightmapRenderer->caputreHeightmap($entities, $heightGeometryProducers);
    }
}
