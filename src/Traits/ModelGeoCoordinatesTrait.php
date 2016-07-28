<?php namespace Skvn\Geo\Traits;

trait ModelGeoCoordinatesTrait
{

    function scopeGeoUncoded($query)
    {
        return $query->where("geo_coded", 0);
    }

    function geoFetchCoordinates()
    {
        $coder = new \Skvn\Geo\Coder();;
        if (empty($this->full_text_address))
        {
            $this->geo_coded = 2;
        }
        else
        {
            $coords = $coder->getAddressCoords($this->full_text_address);
            $this->lng = $coords['lng'];
            $this->lat = $coords['lat'];
            $this->geo_coded = 1;
        }
        $this->save();
    }

}