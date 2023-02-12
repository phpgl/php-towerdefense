<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use TowerDefense\Component\GameCameraComponent;
use TowerDefense\Component\HeightmapComponent;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Math;
use VISU\Geo\Transform;
use VISU\Graphics\Camera;
use VISU\OS\Input;
use VISU\OS\InputContextMap;
use VISU\OS\MouseButton;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\CursorPosSignal;
use VISU\Signals\Input\ScrollSignal;
use VISU\System\VISUCameraSystem;

class CameraSystem extends VISUCameraSystem
{
    /**
     * Default camera mode is game in the game... 
     */
    protected int $visuCameraMode = self::CAMERA_MODE_GAME;

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

        if ($this->input->isMouseButtonPressed(MouseButton::LEFT)) {
            $gameCamera->rotationVelocity->x = $gameCamera->rotationVelocity->x + ($signal->offsetX * $gameCamera->rotationVelocityMouse);
            $gameCamera->rotationVelocity->y = $gameCamera->rotationVelocity->y + ($signal->offsetY * $gameCamera->rotationVelocityMouse);
        }
    }

    /**
     * Override this method to handle the scroll wheel in game mode
     * 
     * @param ScrollSignal $signal
     * @return void 
     */
    protected function handleScrollVISUGame(EntitiesInterface $entities, ScrollSignal $signal) : void
    {
        $gameCamera = $entities->getSingleton(GameCameraComponent::class);

        $c = $gameCamera->focusRadius / $gameCamera->focusRadiusMax;
        $newRadius = $gameCamera->focusRadius + ($signal->y * $c * $gameCamera->focusRadiusZoomFactor);
        $gameCamera->setFocusRadius($newRadius);
    }

    /**
     * Override this method to update the camera in game mode
     * 
     * @param EntitiesInterface $entities
     */
    public function updateGameCamera(EntitiesInterface $entities, Camera $camera) : void
    {
        // skip until we have a heightmap
        if (!$entities->hasSingleton(HeightmapComponent::class)) {
            return;
        }

        $gameCamera = $entities->getSingleton(GameCameraComponent::class);
        $heightmap = $entities->getSingleton(HeightmapComponent::class);

        // calulate the focus point velocity update
        $c = $gameCamera->focusRadius / $gameCamera->focusRadiusMax;
        $speed = Math::lerp($gameCamera->focusPointSpeedClose, $gameCamera->focusPointSpeedFar, $c);

        if ($this->inputContext->actions->isButtonDown('camera_move_forward')) 
        {
            $dir = Math::projectOnPlane($camera->transform->dirForward(), Transform::worldUp());
            $dir->normalize();

            $gameCamera->focusPointVelocity = $gameCamera->focusPointVelocity + ($dir * $speed);
        }
        elseif ($this->inputContext->actions->isButtonDown('camera_move_backward')) 
        {
            $dir = Math::projectOnPlane($camera->transform->dirBackward(), Transform::worldUp());
            $dir->normalize();

            $gameCamera->focusPointVelocity = $gameCamera->focusPointVelocity + ($dir * $speed);
        }

        if ($this->inputContext->actions->isButtonDown('camera_move_left')) 
        {
            $dir = Math::projectOnPlane($camera->transform->dirLeft(), Transform::worldUp());
            $dir->normalize();

            $gameCamera->focusPointVelocity = $gameCamera->focusPointVelocity + ($dir * $speed);
        }
        elseif ($this->inputContext->actions->isButtonDown('camera_move_right')) 
        {
            $dir = Math::projectOnPlane($camera->transform->dirRight(), Transform::worldUp());
            $dir->normalize();

            $gameCamera->focusPointVelocity = $gameCamera->focusPointVelocity + ($dir * $speed);
        }

        // update the focus point itself
        $gameCamera->focusPoint = $gameCamera->focusPoint + $gameCamera->focusPointVelocity;
        $gameCamera->focusPointVelocity = $gameCamera->focusPointVelocity * $gameCamera->focusPointVelocityDamp;

        // y is always the height of the terrain
        $gameCamera->focusPoint->y = $heightmap->heightmap->getHeightAt($gameCamera->focusPoint->x, $gameCamera->focusPoint->z);
        
        // update the cameras rotation in euler angles
        $gameCamera->rotation = $gameCamera->rotation + $gameCamera->rotationVelocity;

        // clamp the rotation on the y axis
        $gameCamera->rotation->y = Math::clamp($gameCamera->rotation->y, -90.0, 90.0);

        // apply dampening to the rotation
        $gameCamera->rotationVelocity = $gameCamera->rotationVelocity * $gameCamera->rotationVelocityDamp;

        // use the eular angles to to rotate the camera in the correct direction
        $camera->transform->position = $gameCamera->focusPoint->copy();
        $camera->transform->orientation = new Quat;
        $camera->transform->orientation->rotate(GLM::radians($gameCamera->rotation->x), Transform::worldUp());
        $camera->transform->orientation->rotate(GLM::radians($gameCamera->rotation->y), Transform::worldRight());
        $camera->transform->moveBackward($gameCamera->focusRadius);

        // ensure the camera is always above the terrain
        $camera->transform->position->y = max(
            $camera->transform->position->y, 
            $heightmap->heightmap->getHeightAt($camera->transform->position->x, $camera->transform->position->z) + 1.0 // min 1.0 above the terrain
        );

        // var_dump((string) $camera->transform->position);
        // var_dump((string) $gameCamera->rotation);
    }
}
