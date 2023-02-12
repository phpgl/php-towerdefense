<?php

namespace TowerDefense\Component;

use GL\Math\Vec2;
use GL\Math\Vec3;

class GameCameraComponent
{
    /**
     * Point in world space the camera is focused on (looking at)
     */
    public Vec3 $focusPoint;
    
    /**
     * The velocity the focus point is currently moving at
     */
    public Vec3 $focusPointVelocity;

    /**
     * The velocity gained when moving the focus when the camera is far away
     */
    public float $focusPointSpeedFar = 0.5;

    /**
     * The velocity gained when moving the focus when the camera is close
     */
    public float $focusPointSpeedClose = 0.01;

    /**
     * The damp applied to the focus move velcoity each update
     */
    public float $focusPointVelocityDamp = 0.8;

    /**
     * Distance from which the camera is looking at the point
     */
    public float $focusRadius = 50.0;

    /**
     * Max distance
     */
    public float $focusRadiusMax = 250.0;

    /**
     * The speed of zooming in and out
     */
    public float $focusRadiusZoomFactor = 100.0;

    /**
     * Rotation around the focus point
     */
    public Vec2 $rotation;

    /**
     * The current rotatinal velcoity the camera is moving around the focus point
     */
    public Vec2 $rotationVelocity;

    /**
     * The amount the velocity is dumpen each update
     */
    public float $rotationVelocityDamp = 0.9;

    /**
     * How much rotational velocity is added based on traveld
     * pixels by the mouse
     */
    public float $rotationVelocityMouse = 0.1;

    /**
     * How much rotational velocity is added based on pressed key
     */
    public float $rotationVelocityKeyboard = 0.1;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->focusPoint = new Vec3(0.0);
        $this->focusPointVelocity = new Vec3(0.0);
        $this->rotationVelocity = new Vec2(0.0);
        $this->rotation = new Vec2(0.0);
    }

    /**
     * Updates the zoom / focus radius safely
     */
    public function setFocusRadius(float $radius)
    {
        $this->focusRadius = min(max(abs($radius), 5.0), $this->focusRadiusMax);
    }
}
