<?php

namespace TowerDefense\System;

use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Animation\AnimationEasingType;
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
                // set the required ticks based on the duration
                $animationContainer->requiredTicks = ceil(($animationContainer->duration / 1000.0) * $this->ticksPerSecond);
                $animationContainer->currentTick = 0;

                // check if the animation should wait
                if ($animationContainer->runCount == 0 && $animationContainer->initialDelay > 0 && !$animationContainer->reversing) {
                    // set the required delay ticks based on the initial delay
                    $animationContainer->requiredDelayTicks = ceil(($animationContainer->initialDelay / 1000.0) * $this->ticksPerSecond);
                    $animationContainer->currentDelayTick = 0;
                    $animationContainer->waiting = true;
                    $animationContainer->running = true;
                } else if ($animationContainer->reverseDelay > 0 && $animationContainer->reversing) {
                    // set the required delay ticks based on the reverse delay
                    $animationContainer->requiredDelayTicks = ceil(($animationContainer->reverseDelay / 1000.0) * $this->ticksPerSecond);
                    $animationContainer->currentDelayTick = 0;
                    $animationContainer->waiting = true;
                    $animationContainer->running = true;
                } else if (!$animationContainer->reversing && $animationContainer->runCount > 0 && $animationContainer->repeatDelay > 0) {
                    // set the required delay ticks based on the repeat delay
                    $animationContainer->requiredDelayTicks = ceil(($animationContainer->repeatDelay / 1000.0) * $this->ticksPerSecond);
                    $animationContainer->currentDelayTick = 0;
                    $animationContainer->waiting = true;
                    $animationContainer->running = true;
                }
            }

            // check if the animation is waiting
            if ($animationContainer->waiting) {
                // check if the delay is over
                if ($animationContainer->currentDelayTick >= $animationContainer->requiredDelayTicks) {
                    $animationContainer->waiting = false;
                    $animationContainer->running = false;
                } else {
                    // increase the delay tick
                    $animationContainer->currentDelayTick++;
                    return;
                }
            }

            // increase the current tick
            $animationContainer->currentTick++;

            // calculate the progress
            $progress = (1.0 / $animationContainer->requiredTicks) * $animationContainer->currentTick;

            // apply easing curves if necessary
            // @TODO We will add a lot more of them: https://easings.net/
            if ($animationContainer->easingType != AnimationEasingType::LINEAR) {
                if ($animationContainer->easingType == AnimationEasingType::EASE_OUT) {
                    // https://easings.net/#easeOutCubic
                    $progress = 1 - pow(1 - $progress, 3);
                } elseif ($animationContainer->easingType == AnimationEasingType::EASE_IN_OUT) {
                    // https://easings.net/#easeInOutCubic
                    $progress = $progress < 0.5 ? 4 * $progress * $progress * $progress : 1 - pow(-2 * $progress + 2, 3) / 2;
                } elseif ($animationContainer->easingType == AnimationEasingType::EASE_IN) {
                    // https://easings.net/#easeInCubic
                    $progress = $progress * $progress * $progress;
                }
            }

            if ($animationContainer instanceof TransformPositionAnimation) {
                if (!$animationContainer->running) {
                    if (!$animationContainer->reversing) {
                        // set the initial and target position
                        $animationContainer->initialPosition = $transform->position->copy();
                        $animationContainer->targetPosition = $animationContainer->initialPosition + $animationContainer->modifier;
                    }
                    $animationContainer->running = true;
                }
                // calculate the new position
                $newPosition = Vec3::lerp($animationContainer->initialPosition, $animationContainer->targetPosition, $progress);
                $transform->position = $newPosition;
                $transform->isDirty = true;
            } else if ($animationContainer instanceof TransformScaleAnimation) {
                if (!$animationContainer->running) {
                    if (!$animationContainer->reversing) {
                        // set the initial and target scale
                        $animationContainer->initialScale = $transform->scale->copy();
                        $animationContainer->targetScale = $animationContainer->initialScale * $animationContainer->modifier;
                    }
                    $animationContainer->running = true;
                }
                // calculate the new scale
                $newScale = Vec3::lerp($animationContainer->initialScale, $animationContainer->targetScale, $progress);
                $transform->scale = $newScale;
                $transform->isDirty = true;
            } else if ($animationContainer instanceof TransformOrientationAnimation) {
                if (!$animationContainer->running) {
                    if (!$animationContainer->reversing) {
                        // set the initial and target orientation
                        $animationContainer->initialOrientation = $transform->orientation->copy();
                        $animationContainer->targetOrientation = $animationContainer->initialOrientation * $animationContainer->modifier;
                    }
                    $animationContainer->running = true;
                }
                // calculate the new orientation
                $newOrientation = Quat::slerp($animationContainer->initialOrientation, $animationContainer->targetOrientation, $progress);
                $transform->orientation = $newOrientation;
                $transform->isDirty = true;
            }
            // check if the animation is finished
            if ($animationContainer->currentTick >= $animationContainer->requiredTicks) {
                $reverseBlockedByRepeat = false;
                if ($animationContainer->reversing) {
                    $animationContainer->reversing = false;
                    $animationContainer->reversedCount++;
                    // check if the reverse is blocked by repeat
                    if ($animationContainer->repeat && ($animationContainer->repeatCount == 0 || $animationContainer->repeatCount > $animationContainer->repeatedCount)) {
                        $reverseBlockedByRepeat = true;
                    }
                } else if ($animationContainer->runCount > 0) {
                    $animationContainer->repeatedCount++;
                }
                $animationContainer->finished = true;
                $animationContainer->running = false;
                $animationContainer->runCount++;

                // check if we need to reverse the animation
                if (!$reverseBlockedByRepeat && $animationContainer->reverse && ($animationContainer->reverseCount == 0 || $animationContainer->reverseCount > $animationContainer->reversedCount)) {
                    $animationContainer->reversing = true;
                    $animationContainer->finished = false;
                    if ($animationContainer instanceof TransformPositionAnimation) {
                        // swap the initial and target position
                        $animationContainer->targetPosition = $animationContainer->initialPosition->copy();
                        $animationContainer->initialPosition = $transform->position->copy();
                    } else if ($animationContainer instanceof TransformScaleAnimation) {
                        // swap the initial and target scale
                        $animationContainer->targetScale = $animationContainer->initialScale->copy();
                        $animationContainer->initialScale = $transform->scale->copy();
                    } else if ($animationContainer instanceof TransformOrientationAnimation) {
                        // swap the initial and target orientation
                        $animationContainer->targetOrientation = $animationContainer->initialOrientation->copy();
                        $animationContainer->initialOrientation = $transform->orientation->copy();
                    }
                } else {
                    // check if we need to repeat the animation
                    if ($animationContainer->repeat && ($animationContainer->repeatCount == 0 || $animationContainer->repeatCount > $animationContainer->repeatedCount)) {
                        $animationContainer->finished = false;
                    }
                }
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
            // check if all animations in the container are finished and set the finished flag
            if ($finishedAnimations == count($animationContainer->animations)) {
                $animationContainer->finished = true;
            }
        }
    }
}
