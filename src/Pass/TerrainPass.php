<?php

namespace TowerDefense\Pass;

use VISU\Graphics\GLState;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderProgram;

class TerrainPass extends RenderPass
{
    public function __construct(
        private GLState $gl,
        private ShaderProgram $shader,
        private int $VAO,
        private int $VBO,
        private int $vertexCount,
    ) {
    }

    /**
     * Executes the render pass
     */
    public function setup(RenderPipeline $pipeline, PipelineContainer $data): void
    {
        $backbuffer = $data->get(BackbufferData::class)->target;

        $pipeline->writes($this, $backbuffer);
    }

    /**
     * Executes the render pass
     */
    public function execute(PipelineContainer $data, PipelineResources $resources): void
    {
        $cameraData = $data->get(CameraData::class);
        $backbuffer = $data->get(BackbufferData::class)->target;

        $resources->activateRenderTarget($backbuffer);

        $this->shader->use();
        $this->shader->setUniformMatrix4f('projection', false, $cameraData->projection);
        $this->shader->setUniformMatrix4f('view', false, $cameraData->view);
        
        $this->gl->bindVertexArray($this->VAO);
        $this->gl->bindVertexArrayBuffer($this->VBO);

        glEnable(GL_DEPTH_TEST);
        glDisable(GL_CULL_FACE);

        glDrawArrays(GL_TRIANGLES, 0, $this->vertexCount);
    }
}
