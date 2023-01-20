<?php

namespace TowerDefense\System;

use TowerDefense\Component\AnimationComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
use VISU\Graphics\Rendering\RenderContext;

class AnimationSystem implements SystemInterface
{

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
        // TODO: Implement update() method.
    }

    /**
     * @inheritDoc
     */
    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // TODO: Implement render() method.
    }
}
