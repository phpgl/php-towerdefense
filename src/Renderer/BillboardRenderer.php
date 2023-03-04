<?php

namespace TowerDefense\Renderer;

use GL\Math\Mat4;
use GL\Math\Vec3;
use VISU\ECS\EntitiesInterface;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\GBufferGeometryPassInterface;
use VISU\Graphics\Rendering\RenderContext;
use VISU\Graphics\Rendering\Pass\GBufferPassData;
use VISU\Graphics\Rendering\PipelineContainer;
use VISU\Graphics\Rendering\PipelineResources;
use VISU\Graphics\Rendering\RenderPass;
use VISU\Graphics\Rendering\RenderPipeline;
use VISU\Graphics\ShaderCollection;
use VISU\Graphics\ShaderProgram;
use VISU\Graphics\ShaderStage;

/**
 * Billboard renderer
 * 
 * Renders billboards for entities.
 * 
 * @package TowerDefense\Renderer
 */
class BillboardRenderer implements GBufferGeometryPassInterface
{
    private QuadVertexArray $quadVAOuter;

    private QuadVertexArray $quadVAInner;

    private ShaderProgram $shaderProgram;

    public function __construct(
        private GLState $gl,
        private ShaderCollection $shaders
    ) {
        $this->quadVAInner = new QuadVertexArray($gl);
        $this->quadVAOuter = new QuadVertexArray($gl);

        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;

        uniform mat4 projection;
        uniform mat4 model;

        void main()
        {
            gl_Position = projection * model * vec4(a_position, 1.0f);
        }
        GLSL));
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        out vec4 fragment_color;

        void main()
        {
            fragment_color = vec4(0.5f, 0.0f, 0.0f, 1.0f);
        }
        GLSL));
        $this->shaderProgram->link();
    }

    public function renderToGBuffer(EntitiesInterface $entities, RenderContext $context, GBufferPassData $gbufferData): void
    {
        $context->pipeline->addPass(new CallbackPass(
            // setup
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) {
                $gbuffer = $data->get(GBufferPassData::class);
                $pipeline->writes($pass, $gbuffer->renderTarget);
            },
            // execute
            function (PipelineContainer $data, PipelineResources $resources) {
                $gbuffer = $data->get(GBufferPassData::class);
                $renderTarget = $resources->getRenderTarget($gbuffer->renderTarget);
                $resources->activateRenderTarget($gbuffer->renderTarget);
                $this->shaderProgram->use();

                $proj = new Mat4;
                $proj->ortho(0, $renderTarget->width(), 0, $renderTarget->height(), -1, 1);
                $this->shaderProgram->setUniformMat4('projection', false, $proj);

                $model = new Mat4;
                $model->translate(new Vec3(500, 500, 0));
                $model->scale(new Vec3(100, 10, 1));
                $this->shaderProgram->setUniformMat4('model', false, $model);

                glDisable(GL_DEPTH_TEST);
                glDisable(GL_CULL_FACE);

                $this->quadVAInner->draw();
            }
        ));
    }
}
