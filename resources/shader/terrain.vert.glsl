#version 330 core
layout (location = 0) in vec3 a_position;
layout (location = 1) in vec3 a_normal;

out vec3 v_normal;
out vec3 v_position;

uniform mat4 projection;
uniform mat4 view;

void main()
{
    v_normal = a_normal;

    mat4 model = mat4(1.0f);

    v_position = vec3(model * vec4(a_position, 1.0f));
    gl_Position = projection * view * model * vec4(a_position, 1.0f);
}