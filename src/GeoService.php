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

    function calcKadDistance($address, $lat = null, $lng = null, $kad_id = null)
    {
        $address = htmlentities(urlencode($address));

        if (!$lat || !$lng) {
            $data = $this->geocode($address);
            $addrPoint = new Point($data['result']['geometry']['location']['lat'], $data['result']['geometry']['location']['lng']);
            $x = $data['result']['geometry']['location']['lat'];
            $y = $data['result']['geometry']['location']['lng'];
            $destination = $x . ',' . $y;
        } else {
            $destination = $lat . ',' . $lng;
            $addrPoint = new Point($lat, $lng);
            $x = $lat;
            $y = $lng;
        }

        if (!$kad_id) {
            $data = $this->geocode($destination, 'reverse');
            $addr = $data['result'][0]['formatted_address'];

            foreach ($data['result'][0]['address_component'] as $key => $val) {
                foreach ($this->config['kads'] as $k => $v) {
                    $gert = explode(',', $v['str_rep']);
                    if (($gert[0] == $val['long_name'] && $val['type'][0] == $gert[1]) or ($gert[2] == $val['short_name'] && $val['type'][0] == $gert[1])) {
                        $id_dest = $v['id_dest'];//ID кольцевой
                        $obl_name = $v['name'];
                       // $kolco = self::$razv[$id_dest];//МАССИВ РАЗВЯЗОК КОЛЬЦЕВОЙ
                        $kolco = $v['razv'];
                        $points = $v['points'];
                        break;
                    }
                }
            }
        } else {
            //$kolco = self::$razv[$kolco_id];
            $kolco = $this->config['kads'][$kad_id]['razv'];
            $points = $this->config['kads'][$kad_id]['points'];
            $id_dest = $kad_id;
        }

        //СОЗДАЕМ ПАРУ МАССИВОВ ДЛЯ ДАЛЬНЕЙШЕЙ РОБОТЫ С КООРДИНАТАМИ
        foreach ($kolco as $k => $v) {
            //$v['y'] = $v['geo_y'];
            //$v['x'] = $v['geo_x'];
            //$v['r'] = self::distance($v['y'], $v['x'], $x, $y);//ГЕОМЕТРИЧЕСКОЕ расстояние от развязки до точки на карте (до адреса)
            $v['r'] = $addrPoint->distance(new Point($v['lat'], $v['lng']));
            $coords_array_min[] = $v;
            $coords[] = $v;
        }

        //ОПРЕДЕЛЯЕМ ВНУТРИ ЛИ ИЛИ СНАРУЖЕ ВЫБРАННОЙ КОЛЬЦЕВОЙ
//        if ($id_dest == 1) {
//            $er_in = self::into_poly($y, $x, self::$TOCHKI_DLYA_IN_OUT_MKAD, 'x', 'y');
//        }
//        if ($id_dest == 3) {
//            $er_in = self::into_poly($y, $x, self::$TOCHKI_DLYA_IN_OUT_KAD, 'x', 'y');
//        }
        $er_in = $addrPoint->inPolygon(new Polygon($points));
        if ($er_in) {
            if ($id_dest == '1') {
                return -1;
            }
            if ($id_dest == '3') {
                return -2;
            }
        }


        //НАХОДИМ ДВЕ БЛИЖАЙШИЕ РАЗВЯЗКИ К АДРЕСУ (по геометрическим данным т.е. не по дороге, а напрямую по сфере)
        //Так не придется делать за раз по 20-30 запросов к гуглу
        $min = 99999999999999999;
        for ($i = 0; $i < count($coords_array_min); $i++) {
            if ($coords_array_min[$i]['r'] < $min) {
                $min = $coords_array_min[$i]['r'];
                $f_id = $i;
            }
        }
        $min = 99999999999999999;
        for ($i = 0; $i < count($coords_array_min); $i++) {
            if ($coords_array_min[$i]['r'] < $min && $i != $f_id) {
                $min = $coords_array_min[$i]['r'];
                $s_id = $i;
            }
        }
        $first = $coords_array_min[$f_id];//точка 1 (развязка)
        $second = $coords_array_min[$s_id];//точка 2 (развязка)

        //var_dump($first);
        //var_dump($second);

        //РАССТОЯНИЕ ПО ПЕРВОЙ развязке в километрах по дорогам без учета платных дорог и паромов
        //$url1 = 'https://maps.googleapis.com/maps/api/directions/xml?origin=' . $first['y'] . ',' . $first['x'] . '&destination=' . $x . ',' . $y . '&key=' . \App :: config('app.geo_google_key') . '&mode=driving&avoid=tolls|highways|ferries';
        //$data = self::xml_to_array(\App :: get('urlLoader')->load($url1,null,['SSL_VERIFYPEER'=>false]));
        $data = $this->geocode(['origin' => $first['lat'] . ',' . $first['lng'], 'destination' => $destination], 'route');
        //var_dump($data);

        $first_point['dl'] = $data['route']['leg']['distance']['value'] / 1000;
        $first_point['name'] = $first['name'];


        //РАССТОЯНИЕ ПО ВТОРОЙ развязке в километрах по дорогам без учета платных дорого и паромов
        $data = $this->geocode(['origin' => $second['lat'] . ',' . $second['lng'], 'destination' => $destination], 'route');
        //var_dump($data);
        $second_point['dl'] = $data['route']['leg']['distance']['value'] / 1000;
        $second_point['name'] = $second['name'];

        //ВЫВОДИМ БЛИЖАЙШЕЕ РАССТОЯНИЕ ИЗ ДВУХ ТОЧЕК
        if ($second_point['dl'] > $first_point['dl']) {
            return $first_point['dl'];
        } else {
            return $second_point['dl'];
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