<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\Mat4;
use GL\Math\Vec3;
use TowerDefense\Pass\GameFrameData;
use TowerDefense\Renderer\TerrainRenderer;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;

class LevelScene extends BaseScene
{
    private TerrainRenderer $terrainRenderer;

    public function __construct(
        protected GameContainer $container,
    )
    {
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL());
    }

    /**
     * Returns the scenes name
     */
    public function getName() : string
    {
        return 'TowerDefense Level';
    }

    /**
     * Loads resources required for the scene
     * 
     * @return void 
     */
    public function load() : void
    {
        $this->terrainRenderer->loadTerrainFromObj(VISU_PATH_RESOURCES . '/terrain/alien_planet/alien_planet_terrain.obj');
    }

    /**
     * Updates the scene state
     * 
     * @return void 
     */
    public function update() : void
    {

    }

    /**
     * Renders the scene
     * 
     * @param RenderPipeline $pipeline The render pipeline to use
     * @param float $deltaTime
     */
    public function render(RenderPipeline $pipeline, PipelineContainer $data, PipelineResources $resources, float $deltaTime) : void
    {
        $target = $resources->getActiveRenderTarget();

        $projection = new Mat4;
        $projection->perspective(45.0, $target->width() / $target->height(), 0.1, 4096.0);

        $view = new Mat4;
        $view->translate(new Vec3(0.0, (sin(glfwGetTime()) * 50) + 150, 0.0));
        $view->rotate(sin(glfwGetTime()) * 0.3, new Vec3(0.0, 0.0, 1.0));
        $view->rotate(glfwGetTime() * 0.5, new Vec3(0.0, 1.0, 0.0));
        $view->inverse();

        // store game frame data
        $data->set(GameFrameData::class, new GameFrameData(
            deltaTime: $deltaTime,
            projection: $projection,
            view: $view,
        ));

        $this->terrainRenderer->attachPass($pipeline);
    }
}
