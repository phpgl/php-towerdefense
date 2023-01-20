<?php

namespace TowerDefense\Animation;

class BaseAnimationContainer
{
    public bool $finished = false; // Are all animations finished?

    public bool $running = false; // Is any animation running?

    /**
     * @param BaseAnimation[] $animations Animations to run
     */
    public function __construct(public array $animations)
    {
    }
}
