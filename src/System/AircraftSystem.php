<?php

namespace TowerDefense\System;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;
use TowerDefense\Component\HeightmapComponent;
use VISU\Animation\Transition\AnimationEasingType;
use VISU\Animation\Transition\AnimationSequence;
use VISU\Animation\Transition\AnimationUtil;
use VISU\Animation\Transition\BaseAnimation;
use VISU\Animation\Transition\BaseAnimationContainer;
use VISU\Animation\Transition\TransformOrientationAnimation;
use VISU\Animation\Transition\TransformPositionAnimation;
use VISU\Animation\Transition\TransformScaleAnimation;
use VISU\Component\AnimationComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Geo\Math;
use VISU\Geo\Transform;
use VISU\Graphics\Rendering\RenderContext;
use VISU\OS\Input;
use VISU\Signal\Dispatcher;
use VISU\Signals\Input\MouseClickSignal;
use VISU\System\VISUTransitionAnimationSystemDelegate;

class AircraftSystem implements SystemInterface, VISUTransitionAnimationSystemDelegate
{
    /**
     * @var int[] $aircrafts The list of aircraft entity ids
     */
    private array $aircrafts = [];

    /**
     * Function ID for mouse click handler
     */
    private int $mouseClickHandlerId = 0;

    /**
     * Constructor
     *
     * @param array $loadedObjects
     *
     */
    public function __construct(
        private array &$loadedObjects,
        private EntitiesInterface &$entities,
        private Dispatcher $dispatcher,
        private Input $input
    ) {
        $this->mouseClickHandlerId = $this->dispatcher->register('input.mouse_click', [$this, 'handleMouseClickEvent']);
    }

    public function spawnAircraft(EntitiesInterface $entities, Vec3 $initialPosition, Quat $initialOrientation): void
    {
        $newAircraft = $entities->create();
        $this->aircrafts[] = $newAircraft;

        $renderable = $entities->attach($newAircraft, new DynamicRenderableModel);
        $renderable->model = $this->loadedObjects['craft_speederA.obj'];

        $transform = $entities->attach($newAircraft, new Transform);
        $transform->scale = $transform->scale * 50;
        $transform->position = $initialPosition;
        $transform->orientation = $initialOrientation;
        $transform->isDirty = true;

        $animationComponent = $entities->attach($newAircraft, new AnimationComponent());
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

        $animationComponent->animation = new AnimationSequence([
            new TransformOrientationAnimation($orientation, 1000, AnimationEasingType::EASE_IN_OUT, initialDelay: 0, repeat: true, repeatCount: 4, repeatDelay: 1000, reverse: true, reverseCount: 1, reverseDelay: 250),
            new TransformPositionAnimation(new Vec3(-500.0, -200.0, 0.0), 2000, AnimationEasingType::EASE_IN_OUT, initialDelay: 500, repeat: true, repeatCount: 1, repeatDelay: 500, reverse: true, reverseCount: 1, reverseDelay: 500),
            new TransformScaleAnimation(new Vec3(0.5, 0.5, 0.5), 1000, AnimationEasingType::EASE_IN_OUT, repeat: true, repeatCount: 3, reverse: true, reverseCount: 4)
        ]);
    }

    /**
     * Mouse click event handler
     */
    public function handleMouseClickEvent(MouseClickSignal $signal)
    {
        $p = $this->input->getNormalizedCursorPosition();
        $heightmapComponent = $this->entities->getSingleton(HeightmapComponent::class);
        $o = $heightmapComponent->cursorWorldPosition;

        echo "Mouse click event native: x " . $signal->position->x . " y " . $signal->position->y . PHP_EOL;
        echo "Mouse click event normalized: x " . $p->x . " y " . $p->y . PHP_EOL;
        echo "Mouse click event heightmap based: x " . $o->x . " y " . $o->y . " z " . $o->z . PHP_EOL . PHP_EOL;

        // forward the click to the aircraft system
        $this->clickAt($o);
    }

    /**
     * Handle a click event
     *
     * @param EntitiesInterface $entities
     * @param Vec3 $position
     * @return void
     */
    private function clickAt(Vec3 $position): void
    {
        // get the first aircraft
        $aircraft = $this->aircrafts[0];

        // get the transform component
        $transform = $this->entities->get($aircraft, Transform::class);

        // set the new rotated orientation
        $height = $position->y;
        $position->y = $transform->position->y; // lock height to current height
        $targetOrientation = Math::quatFacingForwardTowardsTarget($transform->position, $position, Transform::worldBackward(), Transform::worldUp());

        // set the target height of the terrain + 10 "meters"
        $position->y = $height + 10.0;

        if (!$this->entities->has($aircraft, AnimationComponent::class)) {
            $animationComponent = $this->entities->attach($aircraft, new AnimationComponent());
        } else {
            $animationComponent = $this->entities->get($aircraft, AnimationComponent::class);
        }

        $orientationAnimation = TransformOrientationAnimation::fromCurrentAndTargetOrientation($transform->orientation, $targetOrientation, 500);
        $orientationAnimation->easingType = AnimationEasingType::EASE_IN_OUT;
        $orientationAnimation->duration = AnimationUtil::calculateOrientationTransitionDuration($orientationAnimation->modifier, 360, 250);

        $positionAnimation = TransformPositionAnimation::fromCurrentAndTargetPosition($transform->position, $position, 1000);
        $positionAnimation->easingType = AnimationEasingType::EASE_IN_OUT;
        $positionAnimation->duration = AnimationUtil::calculatePositionTransitionDuration($positionAnimation->modifier, 25, 500);

        $animationComponent->animation = new AnimationSequence([$orientationAnimation, $positionAnimation]);
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
        $this->dispatcher->unregister('input.mouse_click', $this->mouseClickHandlerId);

        foreach ($this->aircrafts as $aircraft) {
            $entities->destroy($aircraft);
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

    /**
     * @inheritDoc
     */
    public function animationDidStart(BaseAnimation $animation, int $entity, bool $reversing, bool $repeating, bool $waiting): void
    {
        // TODO: Implement animationDidStart() method.
        echo "Animation started: " . get_class($animation) . PHP_EOL;
        echo "Reversing: " . ($reversing ? 'true' : 'false') . PHP_EOL;
        echo "Repeating: " . ($repeating ? 'true' : 'false') . PHP_EOL;
        echo "Waiting: " . ($waiting ? 'true' : 'false') . PHP_EOL . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function animationDidStop(BaseAnimation $animation, int $entity, bool $finished): void
    {
        // TODO: Implement animationDidStop() method.
        echo "Animation stopped: " . get_class($animation) . PHP_EOL;
        echo "Finished: " . ($finished ? 'true' : 'false') . PHP_EOL . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function animationGroupDidBegin(BaseAnimationContainer $animationGroup, int $entity): void
    {
        // TODO: Implement animationGroupDidBegin() method.
        echo "Animation group began: " . get_class($animationGroup) . PHP_EOL . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function animationGroupDidFinish(BaseAnimationContainer $animationGroup, int $entity): void
    {
        // TODO: Implement animationGroupDidFinish() method.
        echo "Animation group finished: " . get_class($animationGroup) . PHP_EOL . PHP_EOL;
    }
}
