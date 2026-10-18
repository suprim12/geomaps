<?php

namespace sup\craftgeo\utilities;

/**
 * Implementation of distance calculation with Vincenty Method
 *
 * @see http://www.movable-type.co.uk/scripts/latlong-vincenty.html
 */
class Vincenty
{
    // WGS-84 ellipsoid
    private const A = 6_378_137.0;          // semi-major axis (metres)
    private const F = 1 / 298.257_223_563;  // flattening
    private const B = self::A * (1 - self::F); // semi-minor axis

    private const MAX_ITERATIONS = 200;
    private const CONVERGENCE_THRESHOLD = 1e-12;

    /**
     * Return the geodesic distance in metres between two lat/lng points.
     *
     * @param  array{0:float,1:float}|object $point1  [lat, lng] array or object with ->lat / ->lng
     * @param  array{0:float,1:float}|object $point2
     * @return float  Distance in metres.  Returns 0.0 for coincident points.
     *
     * @throws \RuntimeException  If the formula fails to converge (nearly-antipodal points).
     */
    public function getDistance(mixed $point1, mixed $point2): float
    {
        [$lat1, $lng1] = self::unpack($point1);
        [$lat2, $lng2] = self::unpack($point2);

        // Coincident points
        if ($lat1 === $lat2 && $lng1 === $lng2) {
            return 0.0;
        }

        $L = deg2rad($lng2 - $lng1);
        $U1 = atan((1 - self::F) * tan(deg2rad($lat1)));
        $U2 = atan((1 - self::F) * tan(deg2rad($lat2)));

        $sinU1 = sin($U1);
        $cosU1 = cos($U1);
        $sinU2 = sin($U2);
        $cosU2 = cos($U2);

        $lambda = $L;
        $lambdaPrev = PHP_FLOAT_MAX;

        $sinSigma = $cosSigma = $sigma = 0.0;
        $sinAlpha = $cos2SigmaM = $cosSqAlpha = 0.0;

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);

            $sinSigma = sqrt(
                ($cosU2 * $sinLambda) ** 2 +
                ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) ** 2
            );

            if ($sinSigma === 0.0) {
                return 0.0; // coincident
            }

            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;
            $sigma = atan2($sinSigma, $cosSigma);
            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;
            $cosSqAlpha = 1 - $sinAlpha ** 2;

            $cos2SigmaM = $cosSqAlpha !== 0.0
                ? $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha
                : 0.0; // equatorial line

            $C = self::F / 16 * $cosSqAlpha * (4 + self::F * (4 - 3 * $cosSqAlpha));

            $lambdaPrev = $lambda;
            $lambda = $L + (1 - $C) * self::F * $sinAlpha * (
                $sigma + $C * $sinSigma * (
                    $cos2SigmaM + $C * $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)
                )
            );

            if (abs($lambda - $lambdaPrev) < self::CONVERGENCE_THRESHOLD) {
                break;
            }

            if ($i === self::MAX_ITERATIONS - 1) {
                throw new \RuntimeException(
                    'Vincenty formula failed to converge — points may be nearly antipodal.'
                );
            }
        }

        $uSq = $cosSqAlpha * (self::A ** 2 - self::B ** 2) / self::B ** 2;
        $A_coef = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B_coef = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));

        $deltaSigma = $B_coef * $sinSigma * (
            $cos2SigmaM + $B_coef / 4 * (
                $cosSigma * (-1 + 2 * $cos2SigmaM ** 2)
                - $B_coef / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma ** 2) * (-3 + 4 * $cos2SigmaM ** 2)
            )
        );

        return self::B * $A_coef * ($sigma - $deltaSigma); // metres
    }

    // -------------------------------------------------------------------------

    /**
     * Accept [lat, lng] arrays, numeric-indexed arrays, or objects with
     * ->lat / ->lng properties (phpgeo Coordinate-compatible).
     *
     * @return array{float, float}  [lat, lng]
     */
    private static function unpack(mixed $point): array
    {
        if (is_array($point)) {
            if (isset($point['lat'], $point['lng'])) {
                return [(float) $point['lat'], (float) $point['lng']];
            }
            if (isset($point[0], $point[1])) {
                return [(float) $point[0], (float) $point[1]];
            }
            throw new \InvalidArgumentException('Array point must have lat/lng keys or [0]/[1] indices.');
        }

        if (is_object($point)) {
            // phpgeo Coordinate: getLat() / getLng()
            if (method_exists($point, 'getLat') && method_exists($point, 'getLng')) {
                return [(float) $point->getLat(), (float) $point->getLng()];
            }
            // Plain object with properties
            if (property_exists($point, 'lat') && property_exists($point, 'lng')) {
                return [(float) $point->lat, (float) $point->lng];
            }
        }

        throw new \InvalidArgumentException('Cannot unpack point — expected array or object with lat/lng.');
    }

    /**
     * @throws \InvalidArgumentException
     * @return float Distance in meters
     */
    public function rawgetDistance(mixed $point1, mixed $point2): float
    {
        if ($point1->getEllipsoid()->getName() !== $point2->getEllipsoid()->getName()) {
            throw new \InvalidArgumentException('The ellipsoids for both coordinates must match');
        }

        $lat1 = deg2rad($point1->getLat());
        $lat2 = deg2rad($point2->getLat());
        $lng1 = deg2rad($point1->getLng());
        $lng2 = deg2rad($point2->getLng());

        $a = $point1->getEllipsoid()->getA();
        $b = $point1->getEllipsoid()->getB();
        $f = 1 / $point1->getEllipsoid()->getF();

        $L = $lng2 - $lng1;
        $U1 = atan((1 - $f) * tan($lat1));
        $U2 = atan((1 - $f) * tan($lat2));

        $iterationsLeft = 100;
        $lambda = $L;

        $sinU1 = sin($U1);
        $sinU2 = sin($U2);
        $cosU1 = cos($U1);
        $cosU2 = cos($U2);

        do {
            $sinLambda = sin($lambda);
            $cosLambda = cos($lambda);

            $sinSigma = sqrt(
                $cosU2 * $sinLambda * $cosU2 * $sinLambda +
                ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda) * ($cosU1 * $sinU2 - $sinU1 * $cosU2 * $cosLambda)
            );

            if (abs($sinSigma) < 1E-12) {
                return 0.0;
            }

            $cosSigma = $sinU1 * $sinU2 + $cosU1 * $cosU2 * $cosLambda;

            $sigma = atan2($sinSigma, $cosSigma);

            $sinAlpha = $cosU1 * $cosU2 * $sinLambda / $sinSigma;

            $cosSqAlpha = 1 - $sinAlpha * $sinAlpha;

            $cos2SigmaM = 0;
            if (abs($cosSqAlpha) > 1E-12) {
                $cos2SigmaM = $cosSigma - 2 * $sinU1 * $sinU2 / $cosSqAlpha;
            }

            $C = $f / 16 * $cosSqAlpha * (4 + $f * (4 - 3 * $cosSqAlpha));

            $lambdaP = $lambda;

            $lambda = $L
                + (1 - $C)
                * $f
                * $sinAlpha
                * ($sigma + $C * $sinSigma * ($cos2SigmaM + $C * $cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM)));

            $iterationsLeft--;
        } while (abs($lambda - $lambdaP) > 1e-12 && $iterationsLeft > 0);

        if ($iterationsLeft === 0) {
            throw new \InvalidArgumentException('Vincenty calculation does not converge');
        }

        $uSq = $cosSqAlpha * ($a * $a - $b * $b) / ($b * $b);
        $A = 1 + $uSq / 16384 * (4096 + $uSq * (-768 + $uSq * (320 - 175 * $uSq)));
        $B = $uSq / 1024 * (256 + $uSq * (-128 + $uSq * (74 - 47 * $uSq)));
        $deltaSigma = $B * $sinSigma * (
            $cos2SigmaM
            + $B / 4 * ($cosSigma * (-1 + 2 * $cos2SigmaM * $cos2SigmaM)
            - $B / 6 * $cos2SigmaM * (-3 + 4 * $sinSigma * $sinSigma) * (-3 + 4 * $cos2SigmaM * $cos2SigmaM))
        );
        $s = $b * $A * ($sigma - $deltaSigma);

        return round($s, 3);
    }
}
