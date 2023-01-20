<?php

namespace TowerDefense\Animation;

class BaseAnimationContainer
{
    public bool $finished = false;

    public bool $running = false;

    public function __construct(public array $animations)
    {
    }
}
