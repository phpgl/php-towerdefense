<?php

namespace TowerDefense\Renderer;

use GL\Buffer\FloatBuffer;
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
    private const MAX_BARS = 500; // max number of bars to render

    private ShaderProgram $shaderProgram;

    private int $barInstanceBuffer = 0;
    private int $barAnchorProgressBuffer = 0;
    private FloatBuffer $barInstanceData;
    private FloatBuffer $barAnchorProgressData;

    public function __construct(
        private GLState $gl
    ) {
        // configure buffers
        $this->barInstanceData = new FloatBuffer();
        $this->barInstanceData->reserve((self::MAX_BARS * 2) * 9);
        glGenBuffers(1, $this->barInstanceBuffer);

        $this->barAnchorProgressData = new FloatBuffer();
        $this->barAnchorProgressData->reserve((self::MAX_BARS * 2) * 4);
        glGenBuffers(1, $this->barAnchorProgressBuffer);

        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 2) in vec2 bar_size;
        layout (location = 3) in float border_width;
        layout (location = 4) in float z_offset;
        layout (location = 5) in float offset_worldspace_y;
        layout (location = 6) in vec4 bar_color;
        layout (location = 7) in vec3 anchor_worldspace;
        layout (location = 8) in float bar_progress;

        uniform vec3 camera_position;
        uniform mat4 projection;
        uniform mat4 view;
        uniform vec2 render_target_size;
        uniform vec2 render_target_content_scale;

        out vec4 fColor;

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
            gl_Position.z = (distance(camera_position, anchor_worldspace) + z_offset) / 1000.0f;
            fColor = bar_color;
        }
        GLSL));
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        in vec4 fColor;
        out vec4 fragment_color;

        void main()
        {
            fragment_color = fColor;
        }
        GLSL));
        $this->shaderProgram->link();
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // set the bar config static for now for testing, we will get this from the bar components later on
        $barWidth = 120.0;
        $barHeight = 20.0;
        $barSize = new Vec2($barWidth, $barHeight);
        $innerBarBorderWidth = 4.0;
        $barEntities = [];

        // get all the health components
        $barColor = new Vec4(0.0, 0.0, 0.0, 1.0);
        $progressColor = new Vec4(0.5, 0.0, 0.0, 1.0);
        foreach ($entities->view(HealthComponent::class) as $entity => $health) {
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
        $barColor = new Vec4(0.34, 0.34, 0.34, 1.0);
        $progressColor = new Vec4(0.00, 1.00, 0.29, 1.0);
        foreach ($entities->view(ProgressComponent::class) as $entity => $progress) {
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

        // clear the buffers, we will take care of caching later (TODO: Caching)
        $this->barInstanceData->clear();
        $this->barAnchorProgressData->clear();

        // loop through all the bar entities
        foreach ($barEntities as $barEntity) {
            // outer bar
            $this->barInstanceData->pushVec2($barEntity[0]); // bar size
            $this->barInstanceData->push(0.0); // border width
            $this->barInstanceData->push(0.0); // z offset
            $this->barInstanceData->push($barEntity[5]); // offset worldspace y
            $this->barInstanceData->pushVec4($barEntity[1]); // bar color
            $this->barAnchorProgressData->pushVec3($barEntity[6]); // anchor worldspace
            $this->barAnchorProgressData->push(1.0); // bar progress

            // inner bar
            $this->barInstanceData->pushVec2($barEntity[0]); // bar size
            $this->barInstanceData->push($barEntity[4]); // border width
            $this->barInstanceData->push(-0.01); // z offset
            $this->barInstanceData->push($barEntity[5]); // offset worldspace y
            $this->barInstanceData->pushVec4($barEntity[2]); // bar color
            $this->barAnchorProgressData->pushVec3($barEntity[6]); // anchor worldspace
            $this->barAnchorProgressData->push($barEntity[3]); // bar progress
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

                // buffers
                glBindBuffer(GL_ARRAY_BUFFER, $this->barInstanceBuffer);
                glBufferData(GL_ARRAY_BUFFER, $this->barInstanceData, GL_STATIC_DRAW);

                // bar size
                glEnableVertexAttribArray(2);
                glVertexAttribPointer(2, 2, GL_FLOAT, false, 9 * GL_SIZEOF_FLOAT, 0);
                glVertexAttribDivisor(2, 1);

                // border width
                glEnableVertexAttribArray(3);
                glVertexAttribPointer(3, 1, GL_FLOAT, false, 9 * GL_SIZEOF_FLOAT, 2 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(3, 1);

                // z offset
                glEnableVertexAttribArray(4);
                glVertexAttribPointer(4, 1, GL_FLOAT, false, 9 * GL_SIZEOF_FLOAT, 3 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(4, 1);

                // offset worldspace y
                glEnableVertexAttribArray(5);
                glVertexAttribPointer(5, 1, GL_FLOAT, false, 9 * GL_SIZEOF_FLOAT, 4 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(5, 1);

                // bar color
                glEnableVertexAttribArray(6);
                glVertexAttribPointer(6, 4, GL_FLOAT, false, 9 * GL_SIZEOF_FLOAT, 5 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(6, 1);

                glBindBuffer(GL_ARRAY_BUFFER, $this->barAnchorProgressBuffer);
                glBufferData(GL_ARRAY_BUFFER, $this->barAnchorProgressData, GL_STATIC_DRAW);

                // anchor worldspace
                glEnableVertexAttribArray(7);
                glVertexAttribPointer(7, 3, GL_FLOAT, false, 4 * GL_SIZEOF_FLOAT, 0);
                glVertexAttribDivisor(7, 1);

                // progress
                glEnableVertexAttribArray(8);
                glVertexAttribPointer(8, 1, GL_FLOAT, false, 4 * GL_SIZEOF_FLOAT, 3 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(8, 1);

                // activate the shader program
                $this->shaderProgram->use();

                // set the gl state
                glEnable(GL_DEPTH_TEST);
                glEnable(GL_CULL_FACE);

                $renderTargetSize = new Vec2($renderTarget->width(), $renderTarget->height());
                $renderTargetContentScale = new Vec2($renderTarget->contentScaleX, $renderTarget->contentScaleY);

                // get the camera data
                $cameraData = $data->get(CameraData::class);

                // set the projection matrix and other uniforms which are static for every bar
                $this->shaderProgram->setUniformVec3('camera_position', $cameraData->frameCamera->transform->position);
                $this->shaderProgram->setUniformMat4('projection', false, $cameraData->projection);
                $this->shaderProgram->setUniformMat4('view', false, $cameraData->view);
                $this->shaderProgram->setUniformVec2('render_target_size', $renderTargetSize);
                $this->shaderProgram->setUniformVec2('render_target_content_scale', $renderTargetContentScale);

                // draw the bars
                $quadVA->bind();
                glVertexAttribDivisor(0, 0);
                glVertexAttribDivisor(1, 0);
                glDrawArraysInstanced(GL_TRIANGLES, 0, 6, count($barEntities) * 2);
            }
        ));
    }
}
