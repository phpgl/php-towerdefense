<?php

namespace TowerDefense\Renderer;

use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\Geo\Transform;
use VISU\Graphics\GLState;
use VISU\Graphics\QuadVertexArray;
use VISU\Graphics\Rendering\Pass\BackbufferData;
use VISU\Graphics\Rendering\Pass\CallbackPass;
use VISU\Graphics\Rendering\Pass\CameraData;
use VISU\Graphics\Rendering\RenderContext;
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
class BarBillboardRenderer
{
    private ShaderProgram $shaderProgram;

    public function __construct(
        private GLState $gl
    ) {
        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;

        uniform mat4 projection;
        uniform mat4 view;
        uniform vec3 position_worldspace;
        uniform vec2 bar_size;

        void main()
        {
            gl_Position = (projection * view) * vec4(position_worldspace, 1.0f);
            gl_Position /= gl_Position.w;
            gl_Position.xy += a_position.xy * bar_size;
        }
        GLSL));
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        uniform vec4 bar_color;
        out vec4 fragment_color;

        void main()
        {
            fragment_color = bar_color;
        }
        GLSL));
        $this->shaderProgram->link();
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        $context->pipeline->addPass(new CallbackPass(
            // setup
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) {
                // writes directly to the backbuffer
                $backbuffer = $data->get(BackbufferData::class)->target;
                $pipeline->writes($pass, $backbuffer);
            },
            // execute
            function (PipelineContainer $data, PipelineResources $resources) use ($entities) {
                /** @var QuadVertexArray */
                $quadVA = $resources->cacheStaticResource('quadva', function (GLState $gl) {
                    return new QuadVertexArray($gl);
                });

                // activate the backbuffer as render target
                $backbuffer = $data->get(BackbufferData::class)->target;
                $renderTarget = $resources->getRenderTarget($backbuffer);
                $renderTarget->preparePass();

                // activate the shader program
                $this->shaderProgram->use();

                // set the gl state
                glDisable(GL_DEPTH_TEST);
                glEnable(GL_CULL_FACE);

                // set the bar config static for now for testing, we will get this from the bar components later on
                $barColor = new Vec4(0.0, 0.0, 0.0, 1.0);
                $barWidth = 120.0;
                $barHeight = 20.0;
                $barSize = new Vec2(($barWidth / $renderTarget->width()) * $renderTarget->contentScaleX, ($barHeight / $renderTarget->height()) * $renderTarget->contentScaleY);
                $barProgress = 0.5; // 50%
                $progressColor = new Vec4(0.5, 0.0, 0.0, 1.0);
                $innerBarBorderWidth = 4.0;
                $fullBorderWidth = ($innerBarBorderWidth * 2.0);
                $innerBarWidth = ($barWidth - $fullBorderWidth) * $barProgress;
                $innerBarHeight = $barHeight - $fullBorderWidth;
                $innerBarSize = new Vec2(($innerBarWidth / $renderTarget->width()) * $renderTarget->contentScaleX, ($innerBarHeight / $renderTarget->height()) * $renderTarget->contentScaleY);
                $center = new Vec3(0.0, 2.0, 0.0); // 2.0 for y spacing above the model / object

                // get the camera data
                $cameraData = $data->get(CameraData::class);

                // set the projection matrix
                $this->shaderProgram->setUniformMat4('projection', false, $cameraData->projection);
                $this->shaderProgram->setUniformMat4('view', false, $cameraData->view);

                // for now lets assume every craft_racer.obj model has a bar
                foreach ($entities->view(DynamicRenderableModel::class) as $entity => $model) {
                    if ($model->model->name === 'craft_racer.obj') {
                        // get the transform of this entity
                        $transform = $entities->get($entity, Transform::class);
                        $highestY = 0.0;
                        foreach ($model->model->meshes as $mesh) {
                            // get the maxaabb with the highest y value
                            if ($mesh->aabb->max->y > $highestY) {
                                $highestY = $mesh->aabb->max->y;
                            }
                        }

                        $barCenter = $center->copy();
                        $barCenter->y = $barCenter->y + ($highestY * $transform->scale->y);
                        $barCenter = $barCenter + $transform->getWorldPosition($entities);

                        $this->shaderProgram->setUniformVec3('position_worldspace', $barCenter);
                        $this->shaderProgram->setUniformVec2('bar_size', $barSize);

                        // set the bar color of the outer bar
                        $this->shaderProgram->setUniformVec4('bar_color', $barColor);

                        // draw the outer bar
                        $quadVA->draw();

                        $this->shaderProgram->setUniformVec3('position_worldspace', $barCenter);
                        $this->shaderProgram->setUniformVec2('bar_size', $innerBarSize);

                        // set the bar color of the inner bar
                        $this->shaderProgram->setUniformVec4('bar_color', $progressColor);

                        // draw the inner bar (progress)
                        $quadVA->draw();
                    }
                }
            }
        ));
    }
}
