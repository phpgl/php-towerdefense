<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Animation\AnimationEasingType;
use TowerDefense\Animation\AnimationSequence;
use TowerDefense\Animation\ParallelAnimations;
use TowerDefense\Animation\TransformOrientationAnimation;
use TowerDefense\Animation\TransformPositionAnimation;
use TowerDefense\Component\AnimationComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class AircraftSystem implements SystemInterface
{
    /**
     * @var int[] $aircrafts The list of aircraft entity ids
     */
    private array $aircrafts = [];

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
        $this->aircrafts[] = $newAircraft;

        $renderable = $entities->attach($newAircraft, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_speederA.obj'];

        $transform = $entities->attach($newAircraft, new Transform);
        $transform->scale = $transform->scale * 100;
        $transform->position = $initialPosition;
        $transform->orientation = $initialOrientation;

        $animationComponent = $entities->attach($newAircraft, new AnimationComponent());
        $orientation = new Quat();
        $orientation->rotate(GLM::radians(-180.0), new Vec3(0.0, 1.0, 0.0));
        $animationComponent->animation = new AnimationSequence([
            new TransformPositionAnimation(
                new Vec3(-500.0, -200.0, 0.0),
                2000,
                AnimationEasingType::LINEAR,
                0,
                false,
                0,
                0,
                false,
                0,
                0),
            new ParallelAnimations([
                new TransformPositionAnimation(
                    new Vec3(0.0, 500.0, 0.0),
                    2000,
                    AnimationEasingType::LINEAR,
                    0,
                    false,
                    0,
                    0,
                    false,
                    0,
                    0),
                new TransformOrientationAnimation(
                    $orientation,
                    2000,
                    AnimationEasingType::LINEAR,
                    0,
                    false,
                    0,
                    0,
                    false,
                    0,
                    0)
            ])
        ]);
    }

    /**
     * @inheritDoc
     */
    public function register(EntitiesInterface $entities): void
    {
    }

    /**
     * @inheritDoc
     */
    public function unregister(EntitiesInterface $entities): void
    {
        foreach ($this->aircrafts as $aircraft) {
            $entities->destory($aircraft);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(EntitiesInterface $entities): void
    {

    }

    /**
     * @inheritDoc
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }
}
