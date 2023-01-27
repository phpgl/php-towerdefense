<?php

namespace TowerDefense\Geometry;

use GL\Math\GLM;
use GL\Math\Quat;
use GL\Math\Vec3;

/**
 * Collection of utility functions for Vec3
 */
class Vec3Util
{
    /**
     * Calculate the rotation between two vectors
     *
     * @param Vec3 $start
     * @param Vec3 $dest
     * @return Quat
     */
    public static function rotationBetweenVectors(Vec3 $start, Vec3 $dest): Quat
    {
        $start = $start->copy();
        $dest = $dest->copy();

        $start->normalize();
        $dest->normalize();

        $cosTheta = Vec3::dot($start, $dest);

        if ($cosTheta < -1 + 0.001) {
            // special case when vectors in opposite directions:
            // there is no "ideal" rotation axis
            // So guess one; any will do as long as it's perpendicular to start
            $rotationAxis = Vec3::cross(new Vec3(0.0, 0.0, 1.0), $start);
            if ($rotationAxis->length() < 0.01) {
                // bad luck, they were parallel, try again!
                $rotationAxis = Vec3::cross(new Vec3(1.0, 0.0, 0.0), $start);
            }

            $rotationAxis->normalize();
            $quat = new Quat();
            $quat->rotate(GLM::radians(180.0), $rotationAxis);
            return $quat;
        }

        $rotationAxis = Vec3::cross($start, $dest);

        $s = sqrt((1 + $cosTheta) * 2);
        $invs = 1 / $s;

        return new Quat(
            $s * 0.5,
            $rotationAxis->x * $invs,
            $rotationAxis->y * $invs,
            $rotationAxis->z * $invs
        );
    }

    /**
     * Return a quaternion that will rotate the object to face forward to the target position
     * The object is assumed to be facing forward along the forward vector
     * The object is assumed to be facing up along the up vector
     *
     * @param Vec3 $position
     * @param Vec3 $targetPosition
     * @param Vec3 $forward
     * @param Vec3 $up
     * @return Quat
     */
    public static function quatFacingForwardTowardsTarget(Vec3 $position, Vec3 $targetPosition, Vec3 $forward, Vec3 $up): Quat
    {
        // Find the rotation between the front of the object (that we assume towards +Z,
        // but this depends on your model) and the desired direction
        $direction = $targetPosition - $position;
        $rot1 = self::rotationBetweenVectors($forward, $direction);

        // Recompute desiredUp so that it's perpendicular to the direction
        // You can skip that part if you really want to force desiredUp
        $right = Vec3::cross($direction, $up);
        $desiredUp = Vec3::cross($right, $direction);

        // Because of the 1rst rotation, the up is probably completely screwed up.
        // Find the rotation between the "up" of the rotated object, and the desired up
        $newUp = Quat::multiplyVec3($rot1, new Vec3(0.0, 1.0, 0.0));
        $rot2 = self::rotationBetweenVectors($newUp, $desiredUp);
        $targetOrientation = $rot2 * $rot1; // remember, in reverse order.

        return $targetOrientation;
    }
}
