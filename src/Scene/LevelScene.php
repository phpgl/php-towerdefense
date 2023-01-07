<?php

namespace TowerDefense\Scene;

use GameContainer;
use GL\Math\GLM;
use GL\Math\Mat4;
use GL\Math\Quat;
use GL\Math\Vec2;
use GL\Math\Vec3;
use TowerDefense\Pass\GameFrameData;
use TowerDefense\Renderer\TerrainRenderer;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\OS\Key;
use VISU\OS\MouseButton;
use VISU\Signals\Input\CursorPosSignal;

class LevelScene extends BaseScene
{
    private TerrainRenderer $terrainRenderer;

    private Transform $cameraTransform;
    private Vec2 $cameraEuler;
    private Vec3 $lfCameraPos;
    private Quat $lfCameraRot;

    public function __construct(
        protected GameContainer $container,
    )
    {
        $this->terrainRenderer = new TerrainRenderer($container->resolveGL());
        $this->cameraEuler = new Vec2;
        $this->cameraTransform = new Transform;
        $this->cameraTransform->setPosition(new Vec3(20, 20, 20));
        $this->lfCameraPos = $this->cameraTransform->position->copy();
        $this->lfCameraRot = $this->cameraTransform->orientation->copy();
        // $this->cameraTransform->orientation->rotate(0.5, new Vec3(1, 0, 0));
        // $this->cameraTransform->orientation->rotate(0.5, new Vec3(0, 1, 0));


        $input = $this->container->resolveInput();
        $lastCurserPos = $input->getLastCursorPosition();

        $this->container->resolveVisuDispatcher()->register('input.cursor', function(CursorPosSignal $signal) use($lastCurserPos, $input) {

            $cursorOffset = new Vec2($signal->x - $lastCurserPos->x, $signal->y - $lastCurserPos->y);
            
            if ($input->isMouseButtonPressed(MouseButton::LEFT)) {
                $this->cameraEuler->x = $this->cameraEuler->x + $cursorOffset->x * 0.1;
                $this->cameraEuler->y = $this->cameraEuler->y + $cursorOffset->y * 0.1;

                $quatX = new Quat;
                $quatX->rotate(GLM::radians($this->cameraEuler->x), new Vec3(0.0, 1.0, 0.0));
                
                $quatY = new Quat;
                $quatY->rotate(GLM::radians($this->cameraEuler->y), new Vec3(1.0, 0.0, 0.0));

                $this->cameraTransform->setOrientation($quatX * $quatY);

                // var_dump($this->cameraEuler, $this->cameraTransform->orientation->eulerAngles());

                $this->cameraTransform->markDirty();
            }
        });
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
        $input = $this->container->resolveInput();

        if ($input->isKeyPressed(Key::W)) {
            $this->cameraTransform->moveForward(0.5);
        }
        if ($input->isKeyPressed(Key::S)) {
            $this->cameraTransform->moveBackward(0.5);
        }
        if ($input->isKeyPressed(Key::A)) {
            $this->cameraTransform->moveLeft(0.5);
        }
        if ($input->isKeyPressed(Key::D)) {
            $this->cameraTransform->moveRight(0.5);
        }
        
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

        // $view = new Mat4;
        // $view->translate(new Vec3(0.0, (sin(glfwGetTime()) * 50) + 150, 0.0));
        // // $view->rotate(sin(glfwGetTime()) * 0.3, new Vec3(0.0, 0.0, 1.0));
        // // $view->rotate(glfwGetTime() * 0.5, new Vec3(0.0, 1.0, 0.0));
        // $view->inverse();

        $tempTransform = new Transform;
        $tempTransform->position = Vec3::mix($this->lfCameraPos, $this->cameraTransform->position, $deltaTime);
        $tempTransform->orientation = Quat::slerp($this->lfCameraRot, $this->cameraTransform->orientation, $deltaTime);

        $view = Mat4::inverted($tempTransform->getLocalMatrix());

        $this->lfCameraPos = $this->cameraTransform->position->copy();
        $this->lfCameraRot = $this->cameraTransform->orientation->copy();

        if ($this->container->resolveInput()->isKeyPressed(Key::SPACE)) {
            $view = Mat4::inverted($this->cameraTransform->getLocalMatrix());
        }

        // store game frame data
        $data->set(GameFrameData::class, new GameFrameData(
            deltaTime: $deltaTime,
            projection: $projection,
            view: $view,
        ));

        $this->terrainRenderer->attachPass($pipeline);
    }
}
