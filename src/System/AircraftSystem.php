<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Component\AircraftComponent;
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

        $aircraftComponent->targetPosition = $transform->position;
        $aircraftComponent->startPosition = $transform->position;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldBackward());
        $aircraftComponent->targetPosition += $dir * 1000.0;
        $dir = Quat::multiplyVec3($transform->orientation, $transform::worldDown());
        $aircraftComponent->targetPosition += $dir * 100.0;
    }

    /**
     * @inheritDoc
     */
    public function register(EntitiesInterface $entities): void
    {
        // register components
        $entities->registerComponent(AircraftComponent::class);
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
            $travelProgress = 1.0 - (abs($transform->position->x - $aircraft->targetPosition->x) / abs($aircraft->targetPosition->x - $aircraft->startPosition->x));
            if ($travelProgress >= 1) {
                $goUp = true;
                if ($aircraft->startPosition->y < $aircraft->targetPosition->y) {
                    $goUp = false;
                }

                $transform->orientation->rotate(GLM::radians(180.0), new Vec3(0.0, 1.0, 0.0));
                $aircraft->targetPosition = $transform->position;
                $aircraft->startPosition = $transform->position;
                $dir = Quat::multiplyVec3($transform->orientation, $transform::worldBackward());
                $aircraft->targetPosition += $dir * 1000.0;

                if ($goUp) {
                    $dir = Quat::multiplyVec3($transform->orientation, $transform::worldUp());
                    $aircraft->targetPosition += $dir * 100.0;
                } else {
                    $dir = Quat::multiplyVec3($transform->orientation, $transform::worldDown());
                    $aircraft->targetPosition += $dir * 100.0;
                }

                $travelProgress = 0.0;
            }
            $newTravelProgress = min($travelProgress + 0.005, 1.0);
            $newPosition = Vec3::lerp($aircraft->startPosition, $aircraft->targetPosition, $newTravelProgress);
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
