<?php

namespace Skvn\Geo;

use Skvn\Base\Traits\AppHolder;
use Skvn\Base\Helpers\Str;

class GeoService
{
    use AppHolder;

    protected $config;

    function __construct($config)
    {
        $this->config = $config;
    }

    function getParam($name)
    {
        return $this->config[$name] ?? null;
    }

    function calcKadDistance($address, $lat = null, $lng = null, $kad_id = null)
    {
        $address = htmlentities(urlencode($address));


        $addrPoint = !empty($lat) && !empty($lng) ? new Point($lat, $lng) : $this->getAddressCoordinates($address);
        $destination = $addrPoint->lat . ',' . $addrPoint->lng;

        if (!$kad_id) {
            $data = $this->geocode($destination, 'reverse');

            $found = false;
            foreach ($data['result'][0]['address_component'] as $key => $val) {
                foreach ($this->config['kads'] as $k => $v) {
                    $gert = explode(',', $v['str_rep']);
                    if (($gert[0] == $val['long_name'] && $val['type'][0] == $gert[1]) or ($gert[2] == $val['short_name'] && $val['type'][0] == $gert[1])) {
                        $razv = $v['razv'];
                        $points = $v['points'];
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                throw new Exceptions\GeoException('KAD not found');
            }
        } else {
            $razv = $this->config['kads'][$kad_id]['razv'];
            $points = $this->config['kads'][$kad_id]['points'];
        }



        $razv = array_map(function($item) use ($addrPoint){
            $item['r'] = $addrPoint->distance(new Point($item['lat'], $item['lng']));
            return $item;
        }, $razv);

        if ($addrPoint->inPolygon(new Polygon($points))) {
            return 0;
        }

        usort($razv, function($a, $b) {
            return $a['r'] <=> $b['r'];
        });
        $first = $razv[0];
        $second = $razv[1];

        $data = $this->geocode(['origin' => $first['lat'] . ',' . $first['lng'], 'destination' => $destination], 'route');
        $first['dl'] = $data['route']['leg']['distance']['value'] / 1000;


        $data = $this->geocode(['origin' => $second['lat'] . ',' . $second['lng'], 'destination' => $destination], 'route');
        $second['dl'] = $data['route']['leg']['distance']['value'] / 1000;

        return $second['dl'] > $first['dl'] ? $first['dl'] : $second['dl'];
    }

    function getAddressCoordinates($address, $provider = 'google')
    {
        $address = urlencode($address);
        $geocode = $this->geocode($address, 'direct', $provider);
        switch ($provider) {
            case 'yandex':
                $coordsStr = $geocode['GeoObjectCollection']['featureMember']['GeoObject']['Point']['pos'];
                $parts = explode(' ', $coordsStr);
                return new Point($parts[1], $parts[0]);
            break;
            case 'google':
                return new Point($geocode['result']['geometry']['location']['lat'], $geocode['result']['geometry']['location']['lng']);
            break;
            default:
                throw new Exceptions\GeoException('Geocode provider not found: ' . $provider);
            break;
        }
    }

    function geocode($query, $operation = 'direct', $provider = 'google')
    {
        $url = null;
        switch ($provider) {
            case 'google':
                switch ($operation) {
                    case 'direct':
                        $url = 'https://maps.google.com/maps/api/geocode/xml?address=' . $query;
                    break;
                    case 'reverse':
                        $url = 'https://maps.google.com/maps/api/geocode/xml?latlng=' . $query . '&sensor=true_or_false&language=ru';
                    break;
                    case 'route':
                        $url = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . $query['origin'] . '&destination=' . $query['destination'] . '&mode=driving&avoid=tolls|highways|ferries';
                    break;
                    default:
                        throw new Exceptions\GeoException('Unknown geocode operation: ' . $operation);
                    break;
                }
            break;
            case 'yandex':
                switch ($operation) {
                    case 'direct':
                        $url = 'https://geocode-maps.yandex.ru/1.x/?results=1&geocode=' . $query;
                    break;
                    default:
                        throw new Exceptions\GeoException('Unknown geocode operation: ' . $operation);
                    break;
                }
            break;
            default:
                throw new Exceptions\GeoException('Unknown geocode provider: ' . $provider);
            break;
        }
        if (empty($url)) {
            throw new Exceptions\GeoException('Unable to define geocode URI');
        }
        switch ($provider) {
            case 'google':
                $url .= '&key=' . $this->config['google_key'];
            break;
        }

        //var_dump($url);
        $result = Str :: xml2array($this->app->urlLoader->load($url, null, ['ssl_verifypeer' => false]));
        switch ($provider) {
            case 'google':
                if ($result['status'] != 'OK') {
                    //var_dump($query);
                    //var_dump($result);
                    throw new Exceptions\AddressNotResolvedException($query);
                }
            break;
        }
        return $result;
    }





}