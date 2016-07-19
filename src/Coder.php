<?php namespace Skvn\Geo;

use Illuminate\Container\Container;
use Illuminate\Support\Str;


class Coder
{

    protected $app;

    function __construct()
    {
        $this->app = Container :: getInstance();
        $this->config = $this->app['config']->get('geo');
    }


    function getAddressCoords($address, $service = null)
    {
        if (is_null($service))
        {
            $service = $this->config['coder'];
        }
        $method = "getCoordsByAddress" . Str :: studly($service);
        return $this->$method($address);
    }



    private function getCoordsByAddressGoogle($address)
    {
        //$base_url = "http://maps.google.com/maps/geo?output=xml" . "&key=" . $this->config['google_maps_key'];
        $base_url = "https://maps.googleapis.com/maps/api/geocode/json?key=" . $this->config['google_maps_key'];
        $request_url = $base_url . "&address=" . urlencode($address);

        $data = file_get_contents($request_url);
        //var_dump($data);

        $res = json_decode($data, true);
        //var_dump($res);
        if (!$res) return false;

        //var_dump($res['results'][0]['geometry']);

        if (isset($res['results'][0]['geometry']))
        {
            return $res['results'][0]['geometry']['location'];
        }

        return false;
    }


    private function getCoordsByAddressYandex($address)
    {
        $base_url = "http://geocode-maps.yandex.ru/1.x/?results=1" . "&key=" . $this->config['yandex_maps_key'];
        $request_url = $base_url . "&geocode=" . urlencode($address);

        $data =  file_get_contents($request_url);
        //var_dump($data);

        $xml = simplexml_load_string($data);
        if (!$xml)
        {
            //var_dump($xml);
            return false;
        }

        $found =  $xml->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found;
        if ($found>0)
        {
            $pos = $xml->GeoObjectCollection->featureMember->GeoObject->Point->pos;
            $coodrs = ['lat' => 0, 'lng' => 0];

            list($coords['lng'], $coords['lat']) = explode(' ', $pos);

            return $coords;
        }

        return false;

    }


}