<?php

namespace TowerDefense\Renderer;

use GL\Buffer\FloatBuffer;
use GL\Geometry\ObjFileParser;
use GL\Math\Mat4;
use GL\Math\Vec3;
use TowerDefense\Pass\TerrainPass;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\Heightmap\GPUHeightmapGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class TerrainRenderer implements GBufferGeometryPassInterface, GPUHeightmapGeometryPassInterface
{
    private int $terrainVAO = 0;
    private int $terrainVBO = 0;
    private int $vertexCount = 0;

    private ShaderProgram $terrainShader;
    private ShaderProgram $terrainHeightmapShader;

    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
    )
    {
        // create the terrain VAO and VBO
        glGenVertexArrays(1, $this->terrainVAO);
        glGenBuffers(1, $this->terrainVBO);

        $this->gl->bindVertexArray($this->terrainVAO);
        $this->gl->bindVertexArrayBuffer($this->terrainVBO);

        // vertex attributes for the text
        // position
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(0, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, 0);

        // normal
        glEnableVertexAttribArray(1);
        glVertexAttribPointer(1, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, GL_SIZEOF_FLOAT * 3);

        // create the shader program
        $this->terrainShader = $this->shaders->get('terrain');
        $this->terrainHeightmapShader = $this->shaders->get('terrain_heightmap');
    }

    public function loadTerrainFromObj(string $path) : void
    {
        $model = new ObjFileParser($path);

        /** @var \GL\Buffer\FloatBuffer */
        $vertexData = $model->getVertices('pn');

        $this->gl->bindVertexArray($this->terrainVAO);
        $this->gl->bindVertexArrayBuffer($this->terrainVBO);

        glBufferData(GL_ARRAY_BUFFER, $vertexData, GL_STATIC_DRAW);

        $this->vertexCount = $vertexData->size() / 6;
    }

    /**
     * Creates a flat grid terrain with the given width, height and scale.
     * The scale is the distance between each vertex in the grid. Default is 1.0. = 1m 
     * 
     * @param int $width 
     * @param int $height 
     * @param float $scale 
     * @return void 
     */
    public function createFlatTerrain(int $width, int $height, float $scale = 1.0) : void
    {
        $vertexData = new FloatBuffer();
        $vertexData->reserve($width * $height * 6 * 6);

        // generate a mesh of two triangles for each vertex
        // width hand height position on the grid

        $w05 = (int) ($width * 0.5);
        $h05 = (int) ($height * 0.5);

        for ($y = 0; $y < $height; $y++) 
        {
            for ($x = 0; $x < $width; $x++) 
            {
                $px = ($x - $w05);
                $pz = ($y - $h05);

                $vertexData->pushArray([
                    // triangle 1
                    $px * $scale, 0.0, $pz * $scale, // v1 position
                    0.0, 1.0, 0.0, // v1 normal

                    $px * $scale, 0.0, ($pz + 1) * $scale, // v2 position
                    0.0, 1.0, 0.0, // v2 normal

                    ($px + 1) * $scale, 0.0, ($pz + 1) * $scale, // v3 position
                    0.0, 1.0, 0.0, // v3 normal

                    // triangle 2
                    $px * $scale, 0.0, $pz * $scale, // v1 position
                    0.0, 1.0, 0.0, // v1 normal

                    ($px + 1) * $scale, 0.0, ($pz + 1) * $scale, // v3 position
                    0.0, 1.0, 0.0, // v3 normal

                    ($px + 1) * $scale, 0.0, $pz * $scale, // v4 position
                    0.0, 1.0, 0.0, // v4 normal
                ]);
            }
        }

        $this->gl->bindVertexArray($this->terrainVAO);
        $this->gl->bindVertexArrayBuffer($this->terrainVBO);

        glBufferData(GL_ARRAY_BUFFER, $vertexData, GL_STATIC_DRAW);

        $this->vertexCount = $vertexData->size() / 6;
    }

    public function renderToGBuffer(EntitiesInterface $entities, RenderContext $context, GBufferPassData $gbufferData) : void
    {
        $context->pipeline->addPass(new TerrainPass(
            gl: $this->gl,
            shader: $this->terrainShader,
            VAO: $this->terrainVAO,
            VBO: $this->terrainVBO,
            vertexCount: $this->vertexCount,
        ));
    }

    public function renderToHeightmap(EntitiesInterface $entities, RenderTarget $heightRenderTarget, Mat4 $projection) : void
    {
        $this->terrainHeightmapShader->use();
        $this->terrainHeightmapShader->setUniformMat4('projection', false, $projection);

        $this->gl->bindVertexArray($this->terrainVAO);
        $this->gl->bindVertexArrayBuffer($this->terrainVBO);

        glEnable(GL_DEPTH_TEST);

        glDrawArrays(GL_TRIANGLES, 0, $this->vertexCount);
    }
}
