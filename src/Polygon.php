<?php

namespace Skvn\Geo;

class Polygon
{

    /**
     * @var Point[] list of poygon points
     */
    public $points;

    function __construct(array $points)
    {
        foreach ($points as $point) {
            if ($point instanceof Point) {
                $this->points[] = $point;
            } else {
                $this->points[] = new Point($point);
            }
        }
    }

}