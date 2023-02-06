<?php

namespace TowerDefense\System;

use GL\Math\Mat4;
use GL\Math\Vec3;
use GL\Math\Vec4;
use TowerDefense\Component\GameCameraComponent;
use VISU\D3D;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Math;
use VISU\Geo\Transform;
use VISU\Graphics\Camera;
use VISU\OS\Input;
use VISU\OS\InputContextMap;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\CursorPosSignal;
use VISU\System\VISUCameraSystem;

class CameraSystem extends VISUCameraSystem
{
    /**
     * Default camera mode is game in the game... 
     */
    protected int $visuCameraMode = self::CAMERA_MODE_FLYING;

    /**
     * Constructor
     */
    public function __construct(
        Input $input,
        protected InputContextMap $inputContext,
        Dispatcher $dispatcher,
    )
    {
        parent::__construct($input, $dispatcher);
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities) : void
    {
        parent::register($entities);

        $entities->setSingleton(new GameCameraComponent);
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities) : void
    {
        parent::unregister($entities);

        $entities->removeSingleton(GameCameraComponent::class);
    }

    /**
     * Override this method to handle the cursor position in game mode
     * 
     * @param CursorPosSignal $signal 
     * @return void 
     */
    protected function handleCursorPosVISUGame(EntitiesInterface $entities, CursorPosSignal $signal) : void
    {
        $gameCamera = $entities->getSingleton(GameCameraComponent::class);

        if ($this->input->isMouseButtonPressed(GLFW_MOUSE_BUTTON_RIGHT)) {
            $gameCamera->rotationVelocity->x += $signal->offsetX * $gameCamera->rotationVelocityMouse;
            $gameCamera->rotationVelocity->y += $signal->offsetY * $gameCamera->rotationVelocityMouse;
        }
    }

    /**
     * Override this method to update the camera in game mode
     * 
     * @param EntitiesInterface $entities
     */
    public function updateGameCamera(EntitiesInterface $entities, Camera $camera) : void
    {
        $gameCamera = $entities->getSingleton(GameCameraComponent::class);

        if ($this->inputContext->actions->isButtonDown('camera_move_forward')) {
            $gameCamera->focusPoint->z = $gameCamera->focusPoint->z + 1.0;
        } elseif ($this->inputContext->actions->isButtonDown('camera_move_backward')) {
            $gameCamera->focusPoint->z = $gameCamera->focusPoint->z - 1.0;
        }


        // $gameCamera->focusPoint = new Vec3(sin(glfwGetTime()) * 512, 0.0, -512.0);

        $camera->transform->lookAt($gameCamera->focusPoint);

        D3D::cross($gameCamera->focusPoint, D3D::$colorRed, 50.0);

        return;
        if ($camera->transform->position == new Vec3(0.0, 0.0, 0.0) || $camera->transform->position == $gameCamera->focusPoint) {
            $camera->transform->position->z = $camera->transform->position->z - 1.0;
        }

        $camera->transform->lookAt($gameCamera->focusPoint);
        $camera->transform->position = $gameCamera->focusPoint->copy();
        $camera->transform->moveForward($gameCamera->focusRadius);

        $pos = new Vec4($camera->transform->position->x, $camera->transform->position->y, $camera->transform->position->z, 1.0);
        $focus = new Vec4($gameCamera->focusPoint->x, $gameCamera->focusPoint->y, $gameCamera->focusPoint->z, 1.0);

        $xangle = deg2rad(-$gameCamera->rotationVelocity->x);
        $yangle = deg2rad($gameCamera->rotationVelocity->y);

        $gameCamera->rotationVelocity = $gameCamera->rotationVelocity * $gameCamera->rotationVelocityDamp;

        $cosAngle = Vec3::dot($camera->transform->dirForward(), Transform::worldUp());
        if (($cosAngle * Math::sgn($yangle)) > 0.99) {
            $yangle = 0;
        }

        $right = $camera->transform->dirRight();
        $right->normalize();

        $tmp = new Mat4;
        $tmp->rotate($yangle, $right);
        $tmp = $tmp * ($pos - $focus);
        $tmp = $tmp + $focus;

        $pos = $tmp;

        $tmp = new Mat4;
        $tmp->rotate($xangle, Transform::worldUp());
        $tmp = $tmp * ($pos - $focus);
        $tmp = $tmp + $focus;

        $pos = $tmp;

        $camera->transform->position = new Vec3($pos->x, $pos->y, $pos->z);
        $camera->transform->lookAt($gameCamera->focusPoint);
    }
}
