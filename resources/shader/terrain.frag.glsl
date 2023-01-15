#version 330 core

layout (location = 0) out vec3 gbuffer_position;
layout (location = 1) out vec3 gbuffer_normal;
layout (location = 2) out vec4 gbuffer_albedo;

in vec3 v_normal;
in vec3 v_position;

void main()
{
    vec3 basecolor = vec3(0.855, 0.851, 0.843);

    // now darken the color based on the normal
    vec4 albedo = vec4(basecolor * (0.5f + 0.5f * v_normal.y), 1.0f);

    // basic phong lighting
    vec3 lightDir = normalize(vec3(0.0f, 1.0f, 1.0f));
    float diffuse = max(dot(v_normal, lightDir), 0.0f);

    gbuffer_albedo = albedo * diffuse;
    gbuffer_normal = v_normal;
    gbuffer_position = v_position;
    

    //fragment_color = vec4(v_normal, 1.0f);
}