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
    // normalize the vertex position to be between 0 and 1
    vec3 pos = a_position;
    pos.x -= x_mm.x;
    float t = pos.x / (x_mm.y - x_mm.x);
    pos.x = 0; // we compute the x position based on t on the curve

    // current point on the curve
    vec3 currp = get_beizer_pos(origin, p0, dest, t);

    // sample some point on the curve to get the tangent
    vec3 nextp = get_beizer_pos(origin, p0, dest, t + 0.01);

    // calculate the tangent, normal, and binormal
    // so we can rotate the point to the tangent
    vec3 tangent = normalize(nextp - currp);
    vec3 normal = normalize(cross(tangent, vec3(0.0, 1.0, 0.0)));
    vec3 binormal = normalize(cross(normal, tangent));
    mat3 rot = mat3(tangent, binormal, normal);

    // build the model matrix from the 
    // calculated rotation and current point on the curve
    mat4 model = mat4(rot);
    model[3] = vec4(currp, 1.0);

    vec4 world_pos = model * vec4(pos, 1.0);
    v_position = world_pos.xyz;
    v_vposition = view * world_pos;

    // world normal
    vec3 n = normalize(mat3(model) * a_normal);
    v_normal = n;
    
    gl_Position = projection * view * world_pos;
}