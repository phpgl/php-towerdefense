<?php

namespace TowerDefense\Scene;

use GL\Math\Vec3;
use TowerDefense\Component\LevelSceneryComponent;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\EntityRegisty;

// components
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\Geo\Transform;

class LevelLoader
{
    /**
     * @var array<string>
     */
    private array $availableLevels = [];

    /**
     * @var int
     */
    private const SERIALIZATION_VERSION = 1;
    
    public function __construct(
        private string $path
    )
    {
        $this->scanDirForLevels();   
    }

    /**
     * Scans the loaders directory for available level files.
     */
    private function scanDirForLevels() : void
    {
        $this->availableLevels = [];
        $files = scandir($this->path);
        foreach ($files as $file) {
            if (substr($file, -4) === '.lvl') {
                $this->availableLevels[] = substr($file, 0, -4);
            }
        }
    }

    /**
     * Returns all available level names
     */
    public function getAvailableLevels() : array
    {
        return $this->availableLevels;
    }

    /**
     * Returns true if the given level exists.
     */
    public function exists(string $levelName) : bool
    {
        return file_exists($this->fullPath($levelName));
    }

    /**
     * Returns the full path for the given level name.
     */
    public function fullPath(string $levelName) : string
    {
        return $this->path . '/' . $levelName . '.lvl';
    }

    /**
     * Returns level loader options for the given level name.
     * Will throw an exception if the level has not be found yet.
     */
    public function createOptions(string $levelName) : LevelLoaderOptions
    {
        $options = new LevelLoaderOptions($this->fullPath($levelName));
        return $options;
    }

    /**
     * Deserializes the given level file into the given registry.
     */
    public function deserialize(EntitiesInterface $entities, LevelLoaderOptions $options) : void
    {
        if (!file_exists($options->levelFilePath) || !is_readable($options->levelFilePath)) {
            throw new \Exception('Level file could not be found or is not readable: ' . $options->levelFilePath);
        }

        $buffer = file_get_contents($options->levelFilePath);
        if (!$buffer = gzdecode($buffer)) {
            throw new \Exception('Could not decode level state :(');
        }

        $levelData = json_decode($buffer, true);

        if ($levelData['version'] !== static::SERIALIZATION_VERSION) {
            throw new \Exception('Level file version mismatch, expected ' . static::SERIALIZATION_VERSION . ' but got ' . $levelData['version']);
        }

        if (!$entities instanceof EntityRegisty) {
            throw new \Exception('Only EntityRegistry instances are supported for serialization.');
        }

        $entities->deserialize($levelData['entities']);
    }

    /**
     * Serializes the given registry into the given level file.
     */
    public function serialize(EntitiesInterface $entities, LevelLoaderOptions $options) : void
    {
        if (!$entities instanceof EntityRegisty) {
            throw new \Exception('Only EntityRegistry instances are supported for serialization.');
        }

        $levelData = [
            'version' => static::SERIALIZATION_VERSION,
            'entities' => $entities->serialize([
                Transform::class,
                DynamicRenderableModel::class,
            ], LevelSceneryComponent::class),
        ];

        $buffer = json_encode($levelData);
        if (!$buffer = gzencode($buffer, 9)) {
            throw new \Exception('Could not serialize and compress registry...');
        }

        file_put_contents($options->levelFilePath, $buffer);
    }
}
