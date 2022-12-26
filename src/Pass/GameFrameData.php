<?php

namespace TowerDefense\Pass;

use GL\Math\Mat4;

class GameFrameData
{
    public function __construct(
        public float $deltaTime,
        public Mat4 $projection,
        public Mat4 $view,
    ) {
    }
}
