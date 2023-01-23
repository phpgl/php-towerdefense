<?php

namespace TowerDefense\System;

use GL\Math\Vec3;
use VISU\D3D;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\Heightmap\GPUHeightmapRenderer;
use VISU\Graphics\Heightmap\Heightmap;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;

class HeightmapSystem implements SystemInterface
{
    /**
     * GPU Heightmap renderer
     */
    private GPUHeightmapRenderer $heightmapRenderer;

    public ?Heightmap $heightmap = null;

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
        $cameradData = $context->data->get(CameraData::class);

        $x = $cameradData->renderCamera->transform->position->x;
        $z = $cameradData->renderCamera->transform->position->z;

        if ($this->heightmap !== null) {
            for ($xoff=0; $xoff<100; $xoff+=10) {
                for ($zoff=0; $zoff<100; $zoff+=10) {
                    D3D::cross(new Vec3($x + $xoff, $this->heightmap->getHeightAt($x + $xoff, $z + $zoff), $z + $zoff), D3D::$colorCyan, 10);
                }
            }

            // D3D::cross(new Vec3($x, $this->heightmap->getHeightAt($x, $z), $z), D3D::$colorCyan, 30);
        }
    }

    /**
     * Captures the heightmap from the given height geometry producers
     * 
     * @param EntitiesInterface $entities
     * @param array<GPUHeightmapGeometryPassInterface> $heightGeometryProducers 
     */
    public function caputreHeightmap(EntitiesInterface $entities, array $heightGeometryProducers) : void
    {
        $this->heightmap = $this->heightmapRenderer->caputreHeightmap($entities, $heightGeometryProducers);
    }
}
