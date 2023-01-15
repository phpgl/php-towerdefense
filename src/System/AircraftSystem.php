<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Component\PositionComponent;
use TowerDefense\Component\OrientationComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class AircraftSystem implements SystemInterface
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
    public function register(EntitiesInterface $entities): void
    {
        // register components
        $entities->registerComponent(PositionComponent::class);
        $entities->registerComponent(OrientationComponent::class);

        // create some random renderable objects
        $this->aircraft = $entities->create();
        $renderable = $entities->attach($this->aircraft, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_speederA.obj'];
        $transform = $entities->attach($this->aircraft, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position->y = 250;
        $transform->position->x = 500;
        $transform->position->z = -550;
        $transform->orientation->rotate(GLM::radians(-90.0), new Vec3(0.0, 1.0, 0.0));

        $targetPosition = $entities->attach($this->aircraft, new PositionComponent);
        $targetPosition->targetPosition = $transform->position;
        $targetPosition->startPosition = $transform->position;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldBackward());
        $targetPosition->targetPosition += $dir * 1000.0;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldDown());
        $targetPosition->targetPosition += $dir * 100.0;

        $targetOrientation = $entities->attach($this->aircraft, new OrientationComponent);
        $targetOrientation->targetOrientation = $transform->orientation;
    }

    /**
     * @inheritDoc
     */
    public function unregister(EntitiesInterface $entities): void
    {
        $entities->detach($this->aircraft, PositionComponent::class);
        $entities->detach($this->aircraft, OrientationComponent::class);
        $entities->detach($this->aircraft, DynamicRenderableModel::class);
        $entities->detach($this->aircraft, Transform::class);
        $entities->destory($this->aircraft);
    }

    /**
     * @inheritDoc
     */
    public function update(EntitiesInterface $entities): void
    {
        $transform = $entities->get($this->aircraft, Transform::class);
        $targetPosition = $entities->get($this->aircraft, PositionComponent::class);
        $travelProgress = 1.0 - (abs($transform->position->x - $targetPosition->targetPosition->x) / abs($targetPosition->targetPosition->x - $targetPosition->startPosition->x));
        if ($travelProgress >= 1) {
            $goUp = true;
            if ($targetPosition->startPosition->y < $targetPosition->targetPosition->y) {
                $goUp = false;
            }

            $transform->orientation->rotate(GLM::radians(180.0), new Vec3(0.0, 1.0, 0.0));
            $targetPosition->targetPosition = $transform->position;
            $targetPosition->startPosition = $transform->position;
            $dir = Quat::multiplyVec3($transform->orientation, $transform::worldBackward());
            $targetPosition->targetPosition += $dir * 1000.0;

            if ($goUp) {
                $dir = Quat::multiplyVec3($transform->orientation, $transform::worldUp());
                $targetPosition->targetPosition += $dir * 100.0;
            } else {
                $dir = Quat::multiplyVec3($transform->orientation, $transform::worldDown());
                $targetPosition->targetPosition += $dir * 100.0;
            }

            $travelProgress = 0.0;
        }
        $newTravelProgress = min($travelProgress + 0.005, 1.0);
        $newPosition = Vec3::lerp($targetPosition->startPosition, $targetPosition->targetPosition, $newTravelProgress);
        $transform->setPosition($newPosition);
    }

    /**
     * @inheritDoc
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }
}