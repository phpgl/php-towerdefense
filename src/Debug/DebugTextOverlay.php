<?php

namespace TowerDefense\Debug;

use GameContainer;
use VISU\Graphics\Font\DebugFontRenderer;
use VISU\OS\Input;
use VISU\OS\Key;
use VISU\Signals\Input\KeySignal;

class DebugTextOverlay
{
    /**
     * Debug font renderer
     * 
     * @var DebugFontRenderer
     */
    private DebugFontRenderer $debugTextRenderer;

    /**
     * An array of string to be renderd on the next frame
     * 
     * @var array<string>
     */
    private array $rows = [];

    /**
     * Toggles debug overlay
     */
    public bool $enabled = true;

    /**
     * Constructor
     * 
     * As this is a debugging utility, we will use the container directly
     */
    public function __construct(
        private GameContainer $container
    ) {
        $this->debugTextRenderer = new DebugFontRenderer(
            DebugFontRenderer::loadDebugFontAtlas(), 
            $container->resolveGL()
        );

        // listen to keyboard events to toggle debug overlay
        $container->resolveVisuDispatcher()->register('input.key', function(KeySignal $keySignal) {
            if ($keySignal->key == Key::F1 && $keySignal->action == Input::PRESS) {
                $this->enabled = !$this->enabled;
            }
        });
    }

    private function gameLoopMetrics(float $deltaTime) : string
    {
        $gameLoop = $this->container->resolveGameLoopMain();

        $row = "FPS: " . round($gameLoop->getAverageFps());
        $row .= " | ATC: " . sprintf("%.2f", $gameLoop->getAverageTickCount());
        $row .= " | delta: " . sprintf("%.4f", $deltaTime);
    
        return $row;
    }

    /**
     * Draws the debug text overlay if enabled
     */
    public function draw(float $compensation)
    {
        if (!$this->enabled) {
            $this->rows = []; // reset the rows to avoid them stacking up
            return;
        }

        // Add current FPS plus the average tick count and the compensation
        $this->rows[] = $this->gameLoopMetrics($compensation);

        // draw the debug overlay
        $this->debugTextRenderer->renderText(implode("\n", $this->rows));
        $this->rows = [];
    }
}
