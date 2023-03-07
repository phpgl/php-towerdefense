<?php

namespace TowerDefense\Renderer;

use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use TowerDefense\Component\HealthComponent;
use TowerDefense\Component\ProgressComponent;
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
        uniform vec2 render_target_size;
        uniform vec2 render_target_content_scale;
        
        uniform float offset_worldspace_y;
        uniform vec3 anchor_worldspace;
        
        uniform vec2 bar_size;
        uniform float bar_progress;
        uniform float border_width;

        void main()
        {
            vec4 position_worldspace = vec4(anchor_worldspace.x, anchor_worldspace.y + offset_worldspace_y, anchor_worldspace.z, 1.0f);
            vec2 final_bar_size = bar_size;
            final_bar_size.x -= border_width * 2.0f;
            final_bar_size.y -= border_width * 2.0f;
            final_bar_size.x *= bar_progress;
            vec2 clip_space_bar_size = vec2((final_bar_size.x / render_target_size.x) * render_target_content_scale.x, (final_bar_size.y / render_target_size.y) * render_target_content_scale.y);
            gl_Position = projection * view * position_worldspace;
            gl_Position /= gl_Position.w;
            gl_Position.xy += a_position.xy * clip_space_bar_size;
            gl_Position.x -= (((bar_size.x - final_bar_size.x) / render_target_size.x) * render_target_content_scale.x) - (((border_width * 2.0f) / render_target_size.x) * render_target_content_scale.x);
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
        // set the bar config static for now for testing, we will get this from the bar components later on
        $barColor = new Vec4(0.0, 0.0, 0.0, 1.0);
        $barWidth = 120.0;
        $barHeight = 20.0;
        $barSize = new Vec2($barWidth, $barHeight);
        $innerBarBorderWidth = 4.0;
        $barEntities = [];

        // get all the health components
        foreach ($entities->view(HealthComponent::class) as $entity => $health) {
            $progressColor = new Vec4(0.5, 0.0, 0.0, 1.0);
            $barProgress = $health->health;
            // get the model of this entity
            $model = $entities->get($entity, DynamicRenderableModel::class);
            // get the transform of this entity
            $transform = $entities->get($entity, Transform::class);
            $highestY = 0.0;
            foreach ($model->model->meshes as $mesh) {
                // get the maxaabb with the highest y value
                if ($mesh->aabb->max->y > $highestY) {
                    $highestY = $mesh->aabb->max->y;
                }
            }
            $yOffset = 2.0 + ($highestY * $transform->scale->y);
            $anchor = $transform->getWorldPosition($entities);
            $barEntities[] = [$barSize, $barColor, $progressColor, $barProgress, $innerBarBorderWidth, $yOffset, $anchor];
        }

        // get all the progress components
        foreach ($entities->view(ProgressComponent::class) as $entity => $progress) {
            $progressColor = new Vec4(0.00, 1.00, 0.29, 1.0);
            $barProgress = $progress->progress;
            // get the model of this entity
            $model = $entities->get($entity, DynamicRenderableModel::class);
            // get the transform of this entity
            $transform = $entities->get($entity, Transform::class);
            $highestY = 0.0;
            foreach ($model->model->meshes as $mesh) {
                // get the maxaabb with the highest y value
                if ($mesh->aabb->max->y > $highestY) {
                    $highestY = $mesh->aabb->max->y;
                }
            }
            $yOffset = 2.0 + ($highestY * $transform->scale->y);
            $anchor = $transform->getWorldPosition($entities);
            $barEntities[] = [$barSize, $barColor, $progressColor, $barProgress, $innerBarBorderWidth, $yOffset, $anchor];
        }

        // if there are no entities with health or progress components, return
        if (count($barEntities) == 0) {
            return;
        }

        $context->pipeline->addPass(new CallbackPass(
            // setup
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) {
                // writes directly to the backbuffer
                $backbuffer = $data->get(BackbufferData::class)->target;
                $pipeline->writes($pass, $backbuffer);
            },
            // execute
            function (PipelineContainer $data, PipelineResources $resources) use ($barEntities) {
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

                $renderTargetSize = new Vec2($renderTarget->width(), $renderTarget->height());
                $renderTargetContentScale = new Vec2($renderTarget->contentScaleX, $renderTarget->contentScaleY);

                // get the camera data
                $cameraData = $data->get(CameraData::class);

                // set the projection matrix and other uniforms which are static for every bar
                $this->shaderProgram->setUniformMat4('projection', false, $cameraData->projection);
                $this->shaderProgram->setUniformMat4('view', false, $cameraData->view);
                $this->shaderProgram->setUniformVec2('render_target_size', $renderTargetSize);
                $this->shaderProgram->setUniformVec2('render_target_content_scale', $renderTargetContentScale);

                // loop through all the bar entities
                foreach ($barEntities as $barEntity) {
                    // set the bar config for the outer bar
                    $this->shaderProgram->setUniformVec2('bar_size', $barEntity[0]);
                    $this->shaderProgram->setUniformFloat('border_width', 0.0);
                    $this->shaderProgram->setUniformFloat('bar_progress', 1.0);
                    $this->shaderProgram->setUniformFloat('offset_worldspace_y', $barEntity[5]);
                    $this->shaderProgram->setUniformVec3('anchor_worldspace', $barEntity[6]);

                    // set the bar color of the outer bar
                    $this->shaderProgram->setUniformVec4('bar_color', $barEntity[1]);

                    // draw the outer bar
                    $quadVA->draw();

                    // set the additional bar config for the inner bar
                    $this->shaderProgram->setUniformFloat('border_width', $barEntity[4]);
                    $this->shaderProgram->setUniformFloat('bar_progress', $barEntity[3]);

                    // set the bar color of the inner bar
                    $this->shaderProgram->setUniformVec4('bar_color', $barEntity[2]);

                    // draw the inner bar (progress)
                    $quadVA->draw();
                }
            }
        ));
    }
}
