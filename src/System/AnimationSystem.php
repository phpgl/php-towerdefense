<?php

namespace TowerDefense\System;

use TowerDefense\Animation\AnimationContainerInterface;
use TowerDefense\Animation\AnimationContainerType;
use TowerDefense\Animation\AnimationSequence;
use TowerDefense\Animation\AnimationType;
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
     * @param AnimationContainerInterface $animationContainer The animation container
     * @param Transform $transform The transform of the entity
     * @return void
     */
    private function handleAnimationContainer(AnimationContainerInterface $animationContainer, Transform $transform): void
    {
        if (!$animationContainer->finished) {
            // check the AnimationContainerType of $animationContainer
            if ($animationContainer->getAnimationContainerType() == AnimationContainerType::SINGLE_ANIMATION) {
                /** @var BaseAnimation $animationContainer */
                // handle the animation
                $this->handleSingleAnimation($animationContainer, $transform);
            } elseif ($animationContainer->getAnimationContainerType() == AnimationContainerType::SEQUENCE_OF_ANIMATIONS) {
                /** @var AnimationSequence $animationContainer */
                // get the current animation in line
                $this->handleAnimationContainer(array_values($animationContainer->animations)[0], $transform);
            } elseif ($animationContainer->getAnimationContainerType() == AnimationContainerType::PARALLEL_ANIMATIONS) {
                /** @var ParallelAnimations $animationContainer */
                // handle all animations "in parallel"
                foreach ($animationContainer->animations as $animation) {
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
        if ($animation->getAnimationType() == AnimationType::TRANSFORM_POSITION) {
            /** @var TransformPositionAnimation $animation */
            $this->handleTransformPositionAnimation($animation, $transform);
        } else if ($animation->getAnimationType() == AnimationType::TRANSFORM_SCALE) {
            /** @var TransformScaleAnimation $animation */
            $this->handleTransformScaleAnimation($animation, $transform);
        } else if ($animation->getAnimationType() == AnimationType::TRANSFORM_ORIENTATION) {
            /** @var TransformOrientationAnimation $animation */
            $this->handleTransformOrientationAnimation($animation, $transform);
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

    }
}
