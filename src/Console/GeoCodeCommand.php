<?php namespace Skvn\Geo\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Skvn\Laraext\Console\LockableCommand;
use Illuminate\Container\Container;
use Skvn\Crud\Models\CrudModel;

class GeoCodeCommand extends LockableCommand {

    protected $name = 'geo:code';
    protected $description = 'fetch coordinates for geo objects';



    public function handle()
    {
        $models = Container :: getInstance()['config']->get('geo.models');
        foreach ($models as $model)
        {
            $class = CrudModel :: resolveClass($model);
            $list = $class :: geoUncoded()->take(100)->get();
            $this->log("Geocode process started for " . $list->count() . " " . $model, "geocode");
            foreach ($list as $obj)
            {
                try
                {
                    $obj->geoFetchCoordinates();
                    if ($obj->geo_coded == 1)
                    {
                        $this->log($obj->full_text_address . " coded with [".$obj->lng.", ".$obj->lat."]", "geocode");
                    }
                    else
                    {
                        $this->log($obj->classViewName . "#" . $obj->id . " geocode failed", "geocode");
                    }
                }
                catch (\ErrorException $e)
                {
                    $this->log($obj->classViewName . "#" . $obj->id . " geocode failed with error: " . $e->getMessage(), "geocode");
                }
            }
            $this->log("Geocode process finished", "geocode");
        }
    }





}
