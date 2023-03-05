<?php

namespace TowerDefense\Renderer;

use GL\Math\Mat4;
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
        private GLState $gl,
        private ShaderCollection $shaders
    ) {
        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;

        uniform mat4 projection;
        uniform mat4 view;
        uniform mat4 model;

        void main()
        {
            gl_Position = projection * view * model * vec4(a_position, 1.0f);
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

                // set the bar config static for now for testing, we will get this from the bar components later on
                $barColor = new Vec4(0.0, 0.0, 0.0, 1.0);
                $barWidth = 120.0 / 50.0;
                $barHeight = 20.0 / 50.0;
                $barProgress = 0.5; // 50%
                $progressColor = new Vec4(0.5, 0.0, 0.0, 1.0);
                $innerBarBorderWidth = 4.0 / 50.0;
                $fullBorderWidth = ($innerBarBorderWidth * 2.0);
                $innerBarWidth = ($barWidth - $fullBorderWidth) * $barProgress;
                $innerBarHeight = $barHeight - $fullBorderWidth;
                $center = new Vec3(0.0, 1.0, 0.0); // 1.0 for y spacing above the model / object
                $outerBarScale = new Vec3($barWidth, $barHeight, 1.0);
                $innerBarScale = new Vec3($innerBarWidth, $innerBarHeight, 1.0);

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
                            $aabbMax = $mesh->aabb->max;
                            if ($aabbMax->y > $highestY) {
                                $highestY = $aabbMax->y;
                            }
                        }

                        $barCenter = $center->copy();
                        $barCenter->y = $center->y + ($highestY * $transform->scale->y);
                        $barCenter = $barCenter + $transform->getWorldPosition($entities);

                        // set the model matrix of the outer bar
                        $model = new Mat4;
                        $model->translate($barCenter);
                        $model->scale($outerBarScale);
                        $this->shaderProgram->setUniformMat4('model', false, $model);

                        // set the bar color of the outer bar
                        $this->shaderProgram->setUniformVec4('bar_color', $barColor);

                        // draw the outer bar
                        $quadVA->draw();

                        $barCenter = $barCenter->copy();
                        $barCenter->x = ($barCenter->x - ($barWidth - $innerBarWidth)) + $fullBorderWidth;

                        // set the model matrix of the inner bar
                        $model = new Mat4;
                        $model->translate($barCenter);
                        $model->scale($innerBarScale);
                        $this->shaderProgram->setUniformMat4('model', false, $model);

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
