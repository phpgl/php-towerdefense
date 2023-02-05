<?php

namespace TowerDefense\System;

use TowerDefense\Component\GameCameraComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\Camera;
use VISU\Signals\Input\CursorPosSignal;
use VISU\System\VISUCameraSystem;

class CameraSystem extends VISUCameraSystem
{
    /**
     * Default camera mode is game in the game... 
     */
    protected int $visuCameraMode = self::CAMERA_MODE_FLYING;

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
    protected function handleCursorPosVISUGame(CursorPosSignal $signal) : void
    {
        // auto transform = get_active_camera_transform();
        // auto &cbcamera = entities.get<Component::Gameplay::CBCamera>(main_camera_entity);
        
        // // increase roation velocity if mouse is pressed
        // if (_input->mousebutton_is_pressed(GLFW_MOUSE_BUTTON_MIDDLE))
        // {
        //     cbcamera.rotation_velocity.x += event.offsetx * cbcamera.rotation_velocity_mouse;
        //     cbcamera.rotation_velocity.y += event.offsety * cbcamera.rotation_velocity_mouse;
        // }

        // $gameCamera = $entities->getSingleton(GameCameraComponent::class);
    }

    /**
     * Override this method to update the camera in game mode
     * 
     * @param EntitiesInterface $entities
     */
    public function updateGameCamera(EntitiesInterface $entities, Camera $camera) : void
    {
        $gameCamera = $entities->getSingleton(GameCameraComponent::class);

        $camera->transform->position = $gameCamera->focusPoint->copy();
        $camera->transform->position->z = $camera->transform->position->z + 10.0;
        $camera->transform->lookAt($gameCamera->focusPoint);
    }
}
