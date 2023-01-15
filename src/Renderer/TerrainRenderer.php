<?php

namespace TowerDefense\Renderer;

use GL\Geometry\ObjFileParser;
use TowerDefense\Pass\TerrainPass;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

class TerrainRenderer implements GBufferGeometryPassInterface
{
    private int $terrainVAO = 0;
    private int $terrainVBO = 0;
    private int $vertexCount = 0;

    private ShaderProgram $terrainShader;

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

        // create the terrain shader
        // create the shader program
        $this->terrainShader = $this->shaders->get('terrain');
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
}
