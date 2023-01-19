<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Component\AircraftComponent;
use TowerDefense\Component\PositionComponent;
use TowerDefense\Component\OrientationComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class AircraftSystem implements SystemInterface
{
    /**
     * Constructor
     *
     * @param array $loadedObjects
     *
     */
    public function __construct(private array &$loadedObjects)
    {
    }

    public function spawnAircraft(EntitiesInterface $entities, Vec3 $initialPosition, Quat $initialOrientation): void
    {
        $newAircraft = $entities->create();

        $aircraftComponent = $entities->attach($newAircraft, new AircraftComponent);

        $renderable = $entities->attach($newAircraft, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_speederA.obj'];

        $transform = $entities->attach($newAircraft, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position = $initialPosition;
        $transform->orientation = $initialOrientation;

        $targetPosition = $entities->attach($newAircraft, new PositionComponent);
        $targetPosition->targetPosition = $transform->position;
        $targetPosition->startPosition = $transform->position;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldBackward());
        $targetPosition->targetPosition += $dir * 1000.0;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldDown());
        $targetPosition->targetPosition += $dir * 100.0;

        $targetOrientation = $entities->attach($newAircraft, new OrientationComponent);
        $targetOrientation->targetOrientation = $transform->orientation;
    }

    /**
     * @inheritDoc
     */
    public function register(EntitiesInterface $entities): void
    {
        // register components
        $entities->registerComponent(AircraftComponent::class);
        $entities->registerComponent(PositionComponent::class);
        $entities->registerComponent(OrientationComponent::class);
    }

    /**
     * @inheritDoc
     */
    public function unregister(EntitiesInterface $entities): void
    {
        foreach ($entities->view(AircraftComponent::class) as $entity => $aircraft) {
            $entities->destory($entity);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(EntitiesInterface $entities): void
    {
        foreach ($entities->view(AircraftComponent::class) as $entity => $aircraft) {
            $transform = $entities->get($entity, Transform::class);
            $targetPosition = $entities->get($entity, PositionComponent::class);
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
    }

    /**
     * @inheritDoc
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }
}
