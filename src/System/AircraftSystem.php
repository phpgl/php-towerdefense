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
     * Calculate the rotation between two vectors
     *
     * @param Vec3 $start
     * @param Vec3 $dest
     * @return Quat
     */
    private function rotationBetweenVectors(Vec3 $start, Vec3 $dest): Quat
    {
        $start->normalize();
        $dest->normalize();

        $cosTheta = Vec3::dot($start, $dest);

        if ($cosTheta < -1 + 0.001) {
            // special case when vectors in opposite directions:
            // there is no "ideal" rotation axis
            // So guess one; any will do as long as it's perpendicular to start
            $rotationAxis = Vec3::cross(new Vec3(0.0, 0.0, 1.0), $start);
            if ($rotationAxis->length() < 0.01) {
                // bad luck, they were parallel, try again!
                $rotationAxis = Vec3::cross(new Vec3(1.0, 0.0, 0.0), $start);
            }

            $rotationAxis->normalize();
            $quat = new Quat();
            $quat->rotate(GLM::radians(180.0), $rotationAxis);
            return $quat;
        }

        $rotationAxis = Vec3::cross($start, $dest);

        $s = sqrt((1 + $cosTheta) * 2);
        $invs = 1 / $s;

        return new Quat(
            $s * 0.5,
            $rotationAxis->x * $invs,
            $rotationAxis->y * $invs,
            $rotationAxis->z * $invs
        );

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

        // set the target position to the click position
        $targetPosition = $position;
        //$targetPosition->y = $transform->position->y; // always keep the same height

        // Find the rotation between the front of the object (that we assume towards +Z,
        // but this depends on your model) and the desired direction
        $direction = $targetPosition - $transform->position;
        $rot1 = $this->rotationBetweenVectors(Transform::worldBackward(), $direction);

        // Recompute desiredUp so that it's perpendicular to the direction
        // You can skip that part if you really want to force desiredUp
        $right = Vec3::cross($direction, Transform::worldUp());
        $desiredUp = Vec3::cross($right, $direction);

        // Because of the 1rst rotation, the up is probably completely screwed up.
        // Find the rotation between the "up" of the rotated object, and the desired up
        $newUp = Quat::multiplyVec3($rot1, new Vec3(0.0, 1.0, 0.0));
        $rot2 = $this->rotationBetweenVectors($newUp, $desiredUp);
        $targetOrientation = $rot2 * $rot1; // remember, in reverse order.

        $transform->orientation = $targetOrientation;

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
