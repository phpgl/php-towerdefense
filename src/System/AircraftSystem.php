<?php

namespace TowerDefense\System;

use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class AircraftSystem implements \VISU\ECS\SystemInterface
{
    private array $loadedObjects;

    private int $aircraft;

    /**
     * Constructor
     *
     * @param array $loadedObjects
     *
     */
    public function __construct(array &$loadedObjects)
    {
        $this->loadedObjects = $loadedObjects;
    }

    /**
     * @inheritDoc
     */
    public function register(\VISU\ECS\EntitiesInterface $entities): void
    {
        // create some random renderable objects
        $this->aircraft = $entities->create();
        $renderable = $entities->attach($this->aircraft, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_speederA.obj'];
        $transform = $entities->attach($this->aircraft, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position->y = 250;
        $transform->position->x = 250;
        $transform->position->z = -1000;
    }

    /**
     * @inheritDoc
     */
    public function unregister(\VISU\ECS\EntitiesInterface $entities): void
    {
        $entities->detach($this->aircraft, DynamicRenderableModel::class);
        $entities->detach($this->aircraft, Transform::class);
        $entities->destory($this->aircraft);
    }

    /**
     * @inheritDoc
     */
    public function update(\VISU\ECS\EntitiesInterface $entities): void
    {
        $transform = $entities->get($this->aircraft, Transform::class);
        $transform->moveBackward(5.0); // <- move the aircraft forward, seems that the model itself is not correctly rotated? so forward is actually backward?!
    }

    /**
     * @inheritDoc
     */
    public function render(\VISU\ECS\EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }
}
