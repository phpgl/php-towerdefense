<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Mat4;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Animation\AnimationEasingType;
use TowerDefense\Animation\AnimationSequence;
use TowerDefense\Animation\ParallelAnimations;
use TowerDefense\Animation\TransformOrientationAnimation;
use TowerDefense\Animation\TransformPositionAnimation;
use TowerDefense\Animation\TransformScaleAnimation;
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
        $transform->isDirty = true;

        // $animationComponent = $entities->attach($newAircraft, new AnimationComponent());
        $orientation = new Quat();
        $orientation->rotate(GLM::radians(-90.0), new Vec3(0.0, 1.0, 0.0));
        /*$animationComponent->animation = new AnimationSequence([
            new TransformPositionAnimation(new Vec3(-500.0, -200.0, 0.0), 2000, AnimationEasingType::EASE_IN_OUT),
            new ParallelAnimations([
                new TransformPositionAnimation(new Vec3(0.0, 500.0, 0.0), 2000, AnimationEasingType::EASE_IN_OUT),
                new AnimationSequence([
                    new TransformScaleAnimation(new Vec3(0.5, 0.5, 0.5), 500),
                    new TransformScaleAnimation(new Vec3(2.0, 2.0, 2.0), 500)
                ]),
                new TransformOrientationAnimation($orientation, 1000, AnimationEasingType::EASE_IN_OUT)
            ]),
            new TransformPositionAnimation(new Vec3(500.0, 0.0, 0.0), 2000, AnimationEasingType::EASE_IN_OUT),
        ]);*/

        /*$animationComponent->animation = new AnimationSequence([
            new TransformOrientationAnimation($orientation, 1000, AnimationEasingType::EASE_IN_OUT, initialDelay: 0, repeat: true, repeatCount: 4, repeatDelay: 1000, reverse: true, reverseCount: 1, reverseDelay: 250),
            new TransformPositionAnimation(new Vec3(-500.0, -200.0, 0.0), 2000, AnimationEasingType::EASE_IN_OUT, initialDelay: 500, repeat: true, repeatCount: 1, repeatDelay: 500, reverse: true, reverseCount: 1, reverseDelay: 500),
            new TransformScaleAnimation(new Vec3(0.5, 0.5, 0.5), 1000, AnimationEasingType::EASE_IN_OUT, repeat: true, repeatCount: 3, reverse: true, reverseCount: 4)
        ]);*/
    }

    /**
     * Handle a click event
     *
     * @param EntitiesInterface $entities
     * @param Vec3 $position
     * @return void
     */
    public function clickAt(EntitiesInterface $entities, Vec3 $position): void
    {
        // get the first aircraft
        $aircraft = $this->aircrafts[0];
        $transform = $entities->get($aircraft, Transform::class);

        // target position $position
        $targetPosition = $position;
        
        // world forward
        $worldForward = Transform::worldForward();

        // based on world forward and the target position, calculate the new orientation
        $mat = new Mat4();
        $newOrientation = $mat->lookAt($worldForward, $targetPosition, new Vec3(0.0, 1.0, 0.0));
        $transform->orientation = Quat::fromMat4($mat);

        $transform->markDirty();
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
