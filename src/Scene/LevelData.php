<?php

namespace TowerDefense\Scene;

/**
 * This class represents the data of a level.
 * 
 * This is the format the level data are serialized to disk. It should 
 * contain all data that is required to load a level.
 */
class LevelData
{
    /**
     * The user friendly name of the level.
     */
    public string $name = 'Untitled';

    /**
     * The Terrain / Heightmap file to load for this level.
     */
    public ?string $terrainFileName = null;
}
