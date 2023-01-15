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
        $this->terrainShader = new ShaderProgram($this->gl);

        // attach a simple vertex shader
        $this->terrainShader->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 1) in vec3 a_normal;

        out vec3 v_normal;
        out vec3 v_position;

        uniform mat4 projection;
        uniform mat4 view;

        void main()
        {
            v_normal = a_normal;

            mat4 model = mat4(1.0f);

            v_position = vec3(model * vec4(a_position, 1.0f));
            gl_Position = projection * view * model * vec4(a_position, 1.0f);
        }
        GLSL));

        // also attach a simple fragment shader
        $this->terrainShader->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        
        layout (location = 0) out vec3 gbuffer_position;
        layout (location = 1) out vec3 gbuffer_normal;
        layout (location = 2) out vec4 gbuffer_albedo;

        in vec3 v_normal;
        in vec3 v_position;

        void main()
        {
            vec3 basecolor = vec3(0.855, 0.851, 0.843);

            // now darken the color based on the normal
            vec4 albedo = vec4(basecolor * (0.5f + 0.5f * v_normal.y), 1.0f);

            // // basic phong lighting
            // vec3 lightDir = normalize(vec3(0.0f, 1.0f, 1.0f));
            // float diffuse = max(dot(v_normal, lightDir), 0.0f);

            // gbuffer_albedo = albedo * diffuse;
            gbuffer_albedo = albedo;
            gbuffer_normal = v_normal;
            gbuffer_position = v_position;
            

            // //fragment_color = vec4(v_normal, 1.0f);
        }
        GLSL));
        $this->terrainShader->link();
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
