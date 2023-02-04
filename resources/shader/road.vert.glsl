#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;

out vec3 v_normal;
out vec3 v_position;
out vec4 v_vposition;

uniform mat4 projection;
uniform mat4 view;

uniform vec3 origin = vec3(0.0, 0.0, 0.0);
uniform vec3 p0 = vec3(0.0, 0.0, 0.0);
uniform vec3 dest = vec3(0.0, 0.0, 0.0);
uniform vec2 x_mm = vec2(0.0, 0.0);

vec3 get_beizer_pos(vec3 origin, vec3 p0, vec3 dest, float t)
{
    vec3 p1 = origin + (p0 - origin) * t;
    vec3 p2 = p0 + (dest - p0) * t;
    vec3 p3 = p1 + (p2 - p1) * t;
    return p3;
}

void main()
{
    // t = 0 to 1 of the object along the curve
    float t = (a_position.x - x_mm.x) / (x_mm.y - x_mm.x);

    vec3 pos = get_beizer_pos(origin, p0, dest, t);

    mat4 model = mat4(1.0);
    model[3] = vec4(pos, 1.0);

    // mat4 model = mat4(1.0);

    vec4 world_pos = vec4(a_position + pos, 1.0);
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;

    vec3 n = normalize(mat3(model) * a_normal);
    v_normal = n;
    
    gl_Position = projection * view * world_pos;
}