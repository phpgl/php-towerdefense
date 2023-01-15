#version 330 core

layout (location = 0) out vec3 gbuffer_position;
layout (location = 1) out vec3 gbuffer_normal;
layout (location = 2) out vec4 gbuffer_albedo;

in vec3 v_normal;
in vec3 v_position;

void main()
{
    vec3 basecolor = vec3(0.855, 0.851, 0.843);

    // super simple terrain shading using the normal
    // and height to determine the color
    float height = v_position.y;

    // grassy plains green color
    if (height > 0.0f) {
        basecolor = vec3(0.638, 0.658, 0.467);
    }

    // base color
    // snowy mountain tops
    if (height > 170.0f) {
        basecolor = vec3(0.955, 0.951, 0.943);
    }

    // brownish color for the dirt
    if (v_normal.y < 0.98f) {
        basecolor = vec3(0.74, 0.551, 0.404);
    }

    // if the normal is to steep then it is a cliff
    // so the basecolor again
    if (v_normal.y < 0.9f) {
        basecolor = vec3(0.855, 0.851, 0.843);
    }

    // now darken the color based on the normal
    vec4 albedo = vec4(basecolor * (0.5f + 0.5f * v_normal.y), 1.0f);

    gbuffer_albedo = albedo;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
    

    //fragment_color = vec4(v_normal, 1.0f);
}