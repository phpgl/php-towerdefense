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
            if (!$animationContainer->running) {
                $animationContainer->requiredTicks = ($animationContainer->duration / 1000.0) * $this->ticksPerSecond;
                $animationContainer->currentTick = 0;
            }
            $animationContainer->currentTick++;
            if ($animationContainer instanceof TransformPositionAnimation) {
                if (!$animationContainer->running) {
                    $animationContainer->initialPosition = $transform->position->copy();
                    $animationContainer->targetPosition = $animationContainer->initialPosition + $animationContainer->modifier;
                    $animationContainer->running = true;
                }
                $newPosition = Vec3::slerp($animationContainer->initialPosition, $animationContainer->targetPosition, (1.0 / $animationContainer->requiredTicks) * $animationContainer->currentTick);
                $transform->setPosition($newPosition);
            } else if ($animationContainer instanceof TransformScaleAnimation) {
                if (!$animationContainer->running) {
                    $animationContainer->initialScale = $transform->scale->copy();
                    $animationContainer->targetScale = $animationContainer->initialScale * $animationContainer->modifier;
                    $animationContainer->running = true;
                }
                $newScale = Vec3::slerp($animationContainer->initialScale, $animationContainer->targetScale, (1.0 / $animationContainer->requiredTicks) * $animationContainer->currentTick);
                $transform->setScale($newScale);
            } else if ($animationContainer instanceof TransformOrientationAnimation) {
                if (!$animationContainer->running) {
                    $animationContainer->initialOrientation = $transform->orientation->copy();
                    $animationContainer->targetOrientation = $animationContainer->initialOrientation * $animationContainer->modifier;
                    $animationContainer->running = true;
                }
                $newOrientation = Quat::slerp($animationContainer->initialOrientation, $animationContainer->targetOrientation, (1.0 / $animationContainer->requiredTicks) * $animationContainer->currentTick);
                $transform->setOrientation($newOrientation);
            }
            // check if the animation is finished
            if ($animationContainer->currentTick >= $animationContainer->requiredTicks) {
                $animationContainer->finished = true;
                $animationContainer->running = false;
            }
        } else {
            $finishedAnimations = 0;
            if ($animationContainer instanceof AnimationSequence) {
                // get the current animation in line
                foreach ($animationContainer->animations as $animation) {
                    if (!$animation->finished) {
                        $animationContainer->running = true;
                        // handle the animation
                        $this->handleAnimationContainer($animation, $transform);
                        break;
                    } else {
                        $finishedAnimations++;
                    }
                }
            } elseif ($animationContainer instanceof ParallelAnimations) {
                // handle all animations "in parallel"
                foreach ($animationContainer->animations as $animation) {
                    if (!$animation->finished) {
                        $animationContainer->running = true;
                        // handle the animation
                        $this->handleAnimationContainer($animation, $transform);
                    } else {
                        $finishedAnimations++;
                    }
                }
            }
            if ($finishedAnimations == count($animationContainer->animations)) {
                $animationContainer->finished = true;
            }
        }
    }
}
