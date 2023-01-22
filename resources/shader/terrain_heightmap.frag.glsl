#version 330 core

out float frag_height;
in vec3 v_position;

void main()
{
    frag_height = v_position.y;
}