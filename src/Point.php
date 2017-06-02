<?php

namespace Skvn\Geo;

class Point
{

    public $lat;
    public $lng;

    function __construct($lat, $lng = null)
    {
        if (is_array($lat)) {
            $this->lat = $lat[0];
            $this->lng = $lat[1];
        } else {
            $this->lat = $lat;
            $this->lng = $lng;
        }
    }

    function distance(Point $p)
    {
        $lat1 = deg2rad($this->lat);
        $lng1 = deg2rad($this->lng);
        $lat2 = deg2rad($p->lat);
        $lng2 = deg2rad($p->lng);
        return round(6378137 * acos(cos($lat1) * cos($lat2) * cos($lng1 - $lng2) + sin($lat1) * sin($lat2)));
    }

    function inPolygon(Polygon $poly)
    {
        $pk = 0;
        $n = count($poly->points);
        for ($i = 0; $i < $n; $i++) {
            $yu = $poly->points[$i]->lat > $poly->points[($i + 1) % $n]->lat ? $poly->points[$i]->lat : $poly->points[($i + 1) % $n]->lat;
            $yl = $poly->points[$i]->lat < $poly->points[($i + 1) % $n]->lat ? $poly->points[$i]->lat : $poly->points[($i + 1) % $n]->lat;
            if ($poly->points[($i + 1) % $n]->lat - $poly->points[$i]->lat) {
                $wrkx = $poly->points[$i]->lng + ($poly->points[($i + 1) % $n]->lng - $poly->points[$i]->lng) * ($this->lat - $poly->points[$i]->lat) / ($poly->points[($i + 1) % $n]->lat - $poly->points[$i]->lat);
            } else {
                $wrkx = $poly->points[$i]->lng;
            }
            if ($yu >= $this->lat && $yl < $this->lat) {
                    if ($this->lng > $wrkx) {
                        $pk++;
                    }
                    if (abs($this->lng - $wrkx) < 0.00001) return true;
            }
            if ((abs($this->lat - $yl) < 0.00001) && (abs($yu - $yl) < 0.00001) && (abs(abs($wrkx - $poly->points[$i]->lng) + abs($wrkx - $poly->points[($i + 1) % $n]->lng) - abs($poly->points[$i]->lng - $poly->points[($i + 1) % $n]->lng)) < 0.0001)) {
                return true;
            }
        }
        return $pk % 2 ? true : false;
    }

}