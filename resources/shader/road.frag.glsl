#version 330 core

#include "visu/gbuffer_layout.glsl"

in vec3 v_normal;
in vec3 v_position;
in vec4 v_vposition;

void main()
{
    vec3 basecolor = vec3(0.855, 0.851, 0.843);

    gbuffer_albedo = basecolor;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
    gbuffer_vposition = v_vposition.xyz;
}