<?php

namespace TowerDefense\System;

use GL\Buffer\FloatBuffer;
use GL\Math\Vec2;
use GL\Math\Vec3;
use GL\Math\Vec4;
use TowerDefense\Component\HealthComponent;
use TowerDefense\Component\ProgressComponent;
use VISU\Component\VISULowPoly\DynamicRenderableModel;
use VISU\ECS\EntitiesInterface;
use VISU\ECS\SystemInterface;
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
use VISU\System\VISULowPoly\LPModelCollection;

/**
 * Billboard renderer for bars.
 * 
 * Renders bar billboards for entities having a HealthComponent or a ProgressComponent.
 * 
 * @package TowerDefense\Renderer
 */
class BarBillboardSystem implements SystemInterface
{
    private ShaderProgram $shaderProgram; // the shader program

    private int $barInstanceBuffer = 0; // buffer for the instance data
    private int $barAnchorProgressBuffer = 0; // buffer for the anchor and progress data
    private FloatBuffer $barInstanceData; // instance data
    private FloatBuffer $barAnchorProgressData; // anchor and progress data

    private array $bars = []; // the bars to render, used for cache checks

    public function __construct(
        private GLState $gl,
        private LPModelCollection $models,
    ) {
        // configure buffers
        $this->barInstanceData = new FloatBuffer();
        glGenBuffers(1, $this->barInstanceBuffer);

        $this->barAnchorProgressData = new FloatBuffer();
        glGenBuffers(1, $this->barAnchorProgressBuffer);

        // create the shader program
        $this->shaderProgram = new ShaderProgram($gl);
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::VERTEX, <<< 'GLSL'
        #version 330 core
        layout (location = 0) in vec3 a_position;
        layout (location = 2) in vec2 bar_size;
        layout (location = 3) in float border_width;
        layout (location = 4) in float z_offset;
        layout (location = 5) in vec4 bar_color;
        layout (location = 6) in float offset_worldspace_y;
        layout (location = 7) in vec3 anchor_worldspace;
        layout (location = 8) in float bar_progress;

        uniform vec3 camera_position;
        uniform mat4 projection;
        uniform mat4 view;
        uniform vec2 render_target_size;
        uniform vec2 render_target_content_scale;

        out vec4 fColor;
        out float distance_to_camera;

        void main()
        {
            vec4 position_worldspace = vec4(anchor_worldspace.x, anchor_worldspace.y + offset_worldspace_y, anchor_worldspace.z, 1.0f);
            vec2 final_bar_size = bar_size;
            final_bar_size.x -= border_width * 2.0f;
            final_bar_size.y -= border_width * 2.0f;
            final_bar_size.x *= clamp(bar_progress, 0.0, 1.0);
            vec2 clip_space_bar_size = vec2((final_bar_size.x / render_target_size.x) * render_target_content_scale.x, (final_bar_size.y / render_target_size.y) * render_target_content_scale.y);
            gl_Position = projection * view * position_worldspace;
            gl_Position /= gl_Position.w;
            gl_Position.xy += a_position.xy * clip_space_bar_size;
            gl_Position.x -= (((bar_size.x - final_bar_size.x) / render_target_size.x) * render_target_content_scale.x) - (((border_width * 2.0f) / render_target_size.x) * render_target_content_scale.x);
            distance_to_camera = distance(camera_position, anchor_worldspace);
            gl_Position.z = (distance_to_camera + z_offset) / 1000.0f;
            fColor = bar_color;
        }
        GLSL));
        $this->shaderProgram->attach(new ShaderStage(ShaderStage::FRAGMENT, <<< 'GLSL'
        #version 330 core
        in float distance_to_camera;
        in vec4 fColor;
        out vec4 fragment_color;

        void main()
        {
            float fade_factor = 1.0 - smoothstep(0.0, 1.0, clamp((distance_to_camera - 75.0) / 150.0, 0.0, 1.0));
            fragment_color = fColor;
            fragment_color.a *= fade_factor;
        }
        GLSL));
        $this->shaderProgram->link();
    }

    /**
     * Registers the system, this is where you should register all required components.
     * 
     * @return void 
     */
    public function register(EntitiesInterface $entities): void
    {
        // register the components
        $entities->registerComponent(HealthComponent::class);
        $entities->registerComponent(ProgressComponent::class);

        // attach a health bar to random objects
        // @TODO this is for testing
        foreach ($entities->view(DynamicRenderableModel::class) as $entity => $comp) {
            $health = $entities->attach($entity, new HealthComponent);
            $health->health = rand(0, 100) / 100;
        }
    }

    /**
     * Unregisters the system, this is where you can handle any cleanup.
     * 
     * @return void 
     */
    public function unregister(EntitiesInterface $entities): void
    {
        // nothing here yet
    }

    /**
     * Updates handler, this is where the game state should be updated.
     * 
     * @return void 
     */
    public function update(EntitiesInterface $entities): void
    {
        // @TODO this is for testing
        /*foreach ($entities->view(HealthComponent::class) as $entity => $comp) {
            $comp->health = max(0, min(1, $comp->health + 0.001));
        }*/
    }

    public function render(EntitiesInterface $entities, RenderContext $context): void
    {
        // process the entities
        $this->processBars($entities);

        // check if there are any bars to render
        if (count($this->bars) == 0) {
            return;
        }

        $context->pipeline->addPass(new CallbackPass(
            'BarBillboardPass',
            // setup
            function (RenderPass $pass, RenderPipeline $pipeline, PipelineContainer $data) {
                // writes directly to the backbuffer
                $backbuffer = $data->get(BackbufferData::class)->target;
                $pipeline->writes($pass, $backbuffer);
            },
            // execute
            function (PipelineContainer $data, PipelineResources $resources) {
                /** @var QuadVertexArray */
                $quadVA = $resources->cacheStaticResource('quadva', function (GLState $gl) {
                    return new QuadVertexArray($gl);
                });

                // activate the backbuffer as render target
                $backbuffer = $data->get(BackbufferData::class)->target;
                $renderTarget = $resources->getRenderTarget($backbuffer);
                $renderTarget->preparePass();

                // instance data
                glBindBuffer(GL_ARRAY_BUFFER, $this->barInstanceBuffer);

                // bar size, we start at 2 because the first two locations are reserved for the quad vertex position and texcoords
                glEnableVertexAttribArray(2);
                glVertexAttribPointer(2, 2, GL_FLOAT, false, 8 * GL_SIZEOF_FLOAT, 0);
                glVertexAttribDivisor(2, 1);

                // border width
                glEnableVertexAttribArray(3);
                glVertexAttribPointer(3, 1, GL_FLOAT, false, 8 * GL_SIZEOF_FLOAT, 2 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(3, 1);

                // z offset
                glEnableVertexAttribArray(4);
                glVertexAttribPointer(4, 1, GL_FLOAT, false, 8 * GL_SIZEOF_FLOAT, 3 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(4, 1);

                // bar color
                glEnableVertexAttribArray(5);
                glVertexAttribPointer(5, 4, GL_FLOAT, false, 8 * GL_SIZEOF_FLOAT, 4 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(5, 1);

                // anchor progress data
                glBindBuffer(GL_ARRAY_BUFFER, $this->barAnchorProgressBuffer);

                // offset worldspace y
                glEnableVertexAttribArray(6);
                glVertexAttribPointer(6, 1, GL_FLOAT, false, 5 * GL_SIZEOF_FLOAT, 0);
                glVertexAttribDivisor(6, 1);

                // anchor worldspace
                glEnableVertexAttribArray(7);
                glVertexAttribPointer(7, 3, GL_FLOAT, false, 5 * GL_SIZEOF_FLOAT, 1 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(7, 1);

                // progress
                glEnableVertexAttribArray(8);
                glVertexAttribPointer(8, 1, GL_FLOAT, false, 5 * GL_SIZEOF_FLOAT, 4 * GL_SIZEOF_FLOAT);
                glVertexAttribDivisor(8, 1);

                // activate the shader program
                $this->shaderProgram->use();

                // set the gl state
                glEnable(GL_DEPTH_TEST);
                glEnable(GL_CULL_FACE);
                glEnable(GL_BLEND);
                glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
                glBlendEquation(GL_FUNC_ADD);

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
                glVertexAttribDivisor(0, 0); // position
                glVertexAttribDivisor(1, 0); // texcoord (not used)
                glDrawArraysInstanced(GL_TRIANGLES, 0, 6, count($this->bars) * 2); // * 2 because we have to quads per bar (outer border and inner progress)
            }
        ));
    }

    private function processBars(EntitiesInterface $entities): void
    {
        // set the bar config static for now for testing, we will get this from the bar components later on
        $barWidth = 120.0;
        $barHeight = 20.0;
        $barSize = new Vec2($barWidth, $barHeight);
        $innerBarBorderWidth = 4.0;
        $updateInstanceData = false;
        $updateAnchorProgressData = false;
        $listedBars = [];

        // get all the health components
        $barColor = new Vec4(0.0, 0.0, 0.0, 1.0);
        $progressColor = new Vec4(0.5, 0.0, 0.0, 1.0);
        foreach ($entities->view(HealthComponent::class) as $entity => $health) {
            if (isset($this->bars[$entity]['progress'])) {
                // currently we only support one bar being displayed per entity, health or progress
                continue;
            }
            $listedBars[$entity]['health'] = true;
            $barProgress = $health->health;
            // get the model of this entity
            $model = $entities->get($entity, DynamicRenderableModel::class);
            // get the transform of this entity
            $transform = $entities->get($entity, Transform::class);
            $anchor = $transform->getWorldPosition($entities);
            $highestY = 0.0;
            $highestY = $this->models->get($model->modelIdentifier)->aabb->max->y;
            $yOffset = 2.0 + ($highestY * $transform->scale->y);
            // if the bar already exists, check if the data has changed
            if (isset($this->bars[$entity]['health'])) {
                if (
                    $this->bars[$entity]['health'][3] != $barProgress ||
                    $this->bars[$entity]['health'][5] != $yOffset ||
                    $this->bars[$entity]['health'][6]->length() != $anchor->length()
                ) {
                    $this->bars[$entity]['health'][3] = $barProgress;
                    $this->bars[$entity]['health'][5] = $yOffset;
                    $this->bars[$entity]['health'][6] = $anchor->copy();
                    $updateAnchorProgressData = true;
                }
                continue;
            }
            $this->bars[$entity]['health'] = [$barSize, $barColor, $progressColor, $barProgress, $innerBarBorderWidth, $yOffset, $anchor->copy()];
            $updateInstanceData = true;
            $updateAnchorProgressData = true;
        }

        // get all the progress components
        $barColor = new Vec4(0.34, 0.34, 0.34, 1.0);
        $progressColor = new Vec4(0.00, 1.00, 0.29, 1.0);
        foreach ($entities->view(ProgressComponent::class) as $entity => $progress) {
            if (isset($this->bars[$entity]['health'])) {
                // currently we only support one bar being displayed per entity, health or progress
                continue;
            }
            $listedBars[$entity]['progress'] = true;
            $barProgress = $progress->progress;
            // get the model of this entity
            $model = $entities->get($entity, DynamicRenderableModel::class);
            // get the transform of this entity
            $transform = $entities->get($entity, Transform::class);
            $anchor = $transform->getWorldPosition($entities);
            $highestY = $this->models->get($model->modelIdentifier)->aabb->max->y;
            $yOffset = 2.0 + ($highestY * $transform->scale->y);
            // if the bar already exists, check if the data has changed
            if (isset($this->bars[$entity]['progress'])) {
                if (
                    $this->bars[$entity]['progress'][3] != $barProgress ||
                    $this->bars[$entity]['progress'][5] != $yOffset ||
                    $this->bars[$entity]['progress'][6]->length() != $anchor->length()
                ) {
                    $this->bars[$entity]['progress'][3] = $barProgress;
                    $this->bars[$entity]['progress'][5] = $yOffset;
                    $this->bars[$entity]['progress'][6] = $anchor->copy();
                    $updateAnchorProgressData = true;
                }
                continue;
            }
            $this->bars[$entity]['progress'] = [$barSize, $barColor, $progressColor, $barProgress, $innerBarBorderWidth, $yOffset, $anchor->copy()];
            $updateInstanceData = true;
            $updateAnchorProgressData = true;
        }

        // if there are no entities with health or progress components, return
        if (count($this->bars) == 0) {
            return;
        }

        // remove any bars that are no longer needed
        foreach ($this->bars as $entity => $barTypes) {
            // if we don't have any more bars for this entity, remove it
            if (!isset($listedBars[$entity])) {
                unset($this->bars[$entity]);
                $updateInstanceData = true;
                $updateAnchorProgressData = true;
                continue;
            }
            // if we don't have a health or progress bar for this entity, remove it
            foreach ($barTypes as $barType => $barEntity) {
                if (!isset($listedBars[$entity][$barType])) {
                    unset($this->bars[$entity][$barType]);
                    $updateInstanceData = true;
                    $updateAnchorProgressData = true;
                }
            }
        }

        // update the instance data
        if ($updateInstanceData) {
            if ($this->barInstanceData->size() > 0) {
                $this->barInstanceData->clear();
            }
            foreach ($this->bars as $entity => $barTypes) {
                foreach ($barTypes as $barEntity) {
                    // outer bar (border bar)
                    $this->barInstanceData->pushVec2($barEntity[0]); // bar size
                    $this->barInstanceData->push(0.0); // border width
                    $this->barInstanceData->push(0.0); // z offset
                    $this->barInstanceData->pushVec4($barEntity[1]); // bar color
                    // inner bar (progress bar)
                    $this->barInstanceData->pushVec2($barEntity[0]); // bar size
                    $this->barInstanceData->push($barEntity[4]); // border width
                    $this->barInstanceData->push(-0.01); // z offset
                    $this->barInstanceData->pushVec4($barEntity[2]); // bar color
                }
            }
            glBindBuffer(GL_ARRAY_BUFFER, $this->barInstanceBuffer);
            glBufferData(GL_ARRAY_BUFFER, $this->barInstanceData, GL_STATIC_DRAW);
        }

        // update the anchor progress data
        if ($updateAnchorProgressData) {
            if ($this->barAnchorProgressData->size() > 0) {
                $this->barAnchorProgressData->clear();
            }
            foreach ($this->bars as $entity => $barTypes) {
                foreach ($barTypes as $barEntity) {
                    // outer bar (border bar)
                    $this->barAnchorProgressData->push($barEntity[5]); // offset worldspace y
                    $this->barAnchorProgressData->pushVec3($barEntity[6]); // anchor worldspace
                    $this->barAnchorProgressData->push(1.0); // bar progress
                    // inner bar (progress bar)
                    $this->barAnchorProgressData->push($barEntity[5]); // offset worldspace y
                    $this->barAnchorProgressData->pushVec3($barEntity[6]); // anchor worldspace
                    $this->barAnchorProgressData->push($barEntity[3]); // bar progress
                }
            }
            glBindBuffer(GL_ARRAY_BUFFER, $this->barAnchorProgressBuffer);
            glBufferData(GL_ARRAY_BUFFER, $this->barAnchorProgressData, GL_STATIC_DRAW);
        }
    }
}
