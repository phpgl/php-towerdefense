<?php

namespace TowerDefense\Renderer;

use GL\Geometry\ObjFileParser;
use GL\Math\Mat4;
use GL\Math\Vec2;
use GL\Math\Vec3;
use TowerDefense\Pass\TerrainPass;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\Heightmap\GPUHeightmapGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\RenderTarget;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;

class RoadRenderer implements GBufferGeometryPassInterface, GPUHeightmapGeometryPassInterface
{
    private int $roadVAO = 0;
    private int $roadVBO = 0;
    private int $vertexCount = 0;

    private ShaderProgram $roadShader;

    public Vec3 $tmpOrigin;
    public Vec3 $tmpP0;
    public Vec3 $tmpDest;
    public Vec2 $xminMax;

    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders,
    )
    {
        // create the terrain VAO and VBO
        glGenVertexArrays(1, $this->roadVAO);
        glGenBuffers(1, $this->roadVBO);

        $this->gl->bindVertexArray($this->roadVAO);
        $this->gl->bindVertexArrayBuffer($this->roadVBO);

        // vertex attributes for the text
        // position
        glEnableVertexAttribArray(0);
        glVertexAttribPointer(0, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, 0);

        // normal
        glEnableVertexAttribArray(1);
        glVertexAttribPointer(1, 3, GL_FLOAT, false, GL_SIZEOF_FLOAT * 6, GL_SIZEOF_FLOAT * 3);

        // create the shader program
        $this->roadShader = $this->shaders->get('road');
    }

    public function loadRoad(string $path) : void
    {
        $model = new ObjFileParser($path);

        $meshes = $model->getMeshes('pn');
        $mesh = $meshes[0];

        $this->xminMax = new Vec2($mesh->aabbMin->x, $mesh->aabbMax->x);

        /** @var \GL\Buffer\FloatBuffer */
        $vertexData = $mesh->vertices;

        $this->gl->bindVertexArray($this->roadVAO);
        $this->gl->bindVertexArrayBuffer($this->roadVBO);

        glBufferData(GL_ARRAY_BUFFER, $vertexData, GL_STATIC_DRAW);

        $this->vertexCount = $vertexData->size() / 6;
    }

    public function renderToGBuffer(EntitiesInterface $entities, RenderContext $context, GBufferPassData $gbufferData) : void
    {
        $context->pipeline->addPass(new CallbackPass(
            // setup
            function(RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data)
            {
                $gbuffer = $data->get(GBufferPassData::class);
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            // execute
            function(PipelineContainer $data, PipelineResources $resources) 
            {
                $cameraData = $data->get(CameraData::class);
                $gbuffer = $data->get(GBufferPassData::class);

                $resources->activateRenderTarget($gbuffer->renderTarget);

                $this->roadShader->use();
                $this->roadShader->setUniformMatrix4f('projection', false, $cameraData->projection);
                $this->roadShader->setUniformMatrix4f('view', false, $cameraData->view);
                $this->roadShader->setUniformVec3('origin', $this->tmpOrigin);
                $this->roadShader->setUniformVec3('p0', $this->tmpP0);
                $this->roadShader->setUniformVec3('dest', $this->tmpDest);
                $this->roadShader->setUniformVec2('x_mm', $this->xminMax);
                
                $this->gl->bindVertexArray($this->roadVAO);
                $this->gl->bindVertexArrayBuffer($this->roadVBO);

                glEnable(GL_DEPTH_TEST);
                glEnable(GL_CULL_FACE);

                glDrawArrays(GL_TRIANGLES, 0, $this->vertexCount);
            }
        ));
    }

    public function renderToHeightmap(EntitiesInterface $entities, RenderTarget $heightRenderTarget, Mat4 $projection) : void
    {
    }
}