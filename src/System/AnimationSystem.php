<?php

namespace TowerDefense\System;

use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Animation\AnimationSequence;
use TowerDefense\Animation\BaseAnimation;
use TowerDefense\Animation\ParallelAnimations;
use TowerDefense\Animation\TransformOrientationAnimation;
use TowerDefense\Animation\TransformPositionAnimation;
use TowerDefense\Animation\TransformScaleAnimation;
use TowerDefense\Component\AnimationComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;

class AnimationSystem implements SystemInterface
{
    public function __construct(private readonly int $ticksPerSecond)
    {
    }

    /**
     * @inheritDoc
     */
    public function register(EntitiesInterface $entities): void
    {
        // register components
        $entities->registerComponent(AnimationComponent::class);
    }

    /**
     * @inheritDoc
     */
    public function unregister(EntitiesInterface $entities): void
    {
        // TODO: Implement unregister() method.
    }

    /**
     * @inheritDoc
     */
    public function update(EntitiesInterface $entities): void
    {
        // get alle animation components and their entities
        foreach ($entities->view(AnimationComponent::class) as $entity => $animationComponent) {
            /** @var AnimationComponent $animationComponent */
            // only if the animation container is not finished
            if (!$animationComponent->animation->finished) {
                // get the transform of the entity
                $transform = $entities->get($entity, Transform::class);
                $this->handleAnimationContainer($animationComponent->animation, $transform);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }

    /**
     * Handles an animation container
     *
     * @param object $animationContainer The animation container
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleAnimationContainer(object $animationContainer, Transform $transform): void
    {
        // check the AnimationContainerType of $animationContainer
        if ($animationContainer instanceof BaseAnimation) {
            // handle the animation
            $this->handleSingleAnimation($animationContainer, $transform);
        } elseif ($animationContainer instanceof AnimationSequence) {
            // get the current animation in line
            foreach ($animationContainer->animations as $animation) {
                if (!$animation->finished) {
                    // handle the animation
                    $this->handleAnimationContainer($animation, $transform);
                    break;
                }
            }
        } elseif ($animationContainer instanceof ParallelAnimations) {
            // handle all animations "in parallel"
            foreach ($animationContainer->animations as $animation) {
                if (!$animation->finished) {
                    // handle the animation
                    $this->handleAnimationContainer($animation, $transform);
                }
            }
        }
    }

    /**
     * Handles a single animation
     *
     * @param BaseAnimation $animation The animation
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleSingleAnimation(BaseAnimation $animation, Transform $transform): void
    {
        if ($animation instanceof TransformPositionAnimation) {
            $this->handleTransformPositionAnimation($animation, $transform);
        } else if ($animation instanceof TransformScaleAnimation) {
            $this->handleTransformScaleAnimation($animation, $transform);
        } else if ($animation instanceof TransformOrientationAnimation) {
            $this->handleTransformOrientationAnimation($animation, $transform);
        }
    }

    /**
     * Handles the stuff for the base animation
     *
     * @param BaseAnimation $animation The animation
     * @param Transform $transform The transform of the entity
     *
     * @return void
     */
    private function handleBaseAnimation(BaseAnimation $animation, Transform $transform): void
    {
        if (!$animation->running) {
            $animation->requiredTicks = ($animation->duration / 1000.0) * $this->ticksPerSecond;
            $animation->currentTick = 0;
        }
        $animation->currentTick++;
    }

    private function handleBaseAnimationFinalizer(BaseAnimation $animation, Transform $transform): void
    {
        if ($animation->currentTick >= $animation->requiredTicks) {
            $animation->finished = true;
            $animation->running = false;
        }
    }

    /**
     * Handles a TransformPositionAnimation
     *
     * @param TransformPositionAnimation $animation The animation
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleTransformPositionAnimation(TransformPositionAnimation $animation, Transform $transform): void
    {
        $this->handleBaseAnimation($animation, $transform);
        if (!$animation->running) {
            $animation->initialPosition = $transform->position->copy();
            $animation->targetPosition = $animation->initialPosition + $animation->modifier;
            $animation->running = true;
        }
        $newPosition = Vec3::slerp($animation->initialPosition, $animation->targetPosition, (1.0 / $animation->requiredTicks) * $animation->currentTick);
        $transform->setPosition($newPosition);
        $this->handleBaseAnimationFinalizer($animation, $transform);
    }

    /**
     * Handles a TransformScaleAnimation
     *
     * @param TransformScaleAnimation $animation The animation
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleTransformScaleAnimation(TransformScaleAnimation $animation, Transform $transform): void
    {
        $this->handleBaseAnimation($animation, $transform);
    }

    /**
     * Handles a TransformOrientationAnimation
     *
     * @param TransformOrientationAnimation $animation The animation
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleTransformOrientationAnimation(TransformOrientationAnimation $animation, Transform $transform): void
    {
        $this->handleBaseAnimation($animation, $transform);
        if (!$animation->running) {
            $animation->initialOrientation = $transform->orientation->copy();
            $animation->targetOrientation = $animation->initialOrientation * $animation->modifier;
            $animation->running = true;
        }
        $newOrientation = Quat::slerp($animation->initialOrientation, $animation->targetOrientation, (1.0 / $animation->requiredTicks) * $animation->currentTick);
        $transform->setOrientation($newOrientation);
        $this->handleBaseAnimationFinalizer($animation, $transform);
    }
}
