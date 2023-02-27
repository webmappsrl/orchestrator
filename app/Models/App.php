<?php

namespace App\Models;

use App\Models\Layer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class App extends Model
{
    use HasFactory, HasTranslations;
    //use ConfTrait;



    //translatable fields
    public $translatable = ['name', 'description', 'welcome'];

    public $fillable = ['name', 'description', 'welcome'];


    /**
     * relationship with layers
     * @return hasMany
     */
    public function layers()
    {
        return $this->hasMany(Layer::class);
    }

    public function ugc_medias()
    {
        return $this->hasMany(UgcMedia::class);
    }

    public function ugc_pois()
    {
        return $this->hasMany(UgcPoi::class);
    }

    public function ugc_tracks()
    {
        return $this->hasMany(UgcTrack::class);
    }

    public function getGeojson()
    {
        $tracks = EcTrack::where('user_id', $this->user_id)->get();

        if (!is_null($tracks)) {
            $geoJson = ["type" => "FeatureCollection"];
            $features = [];
            foreach ($tracks as $track) {
                $geojson = $track->getGeojson();
                //                if (isset($geojson))
                $features[] = $geojson;
            }
            $geoJson["features"] = $features;

            return json_encode($geoJson);
        }
    }

    public function getMostViewedPoiGeojson()
    {
        $pois = EcPoi::where('user_id', $this->user_id)->limit(10)->get();

        if (!is_null($pois)) {
            $geoJson = ["type" => "FeatureCollection"];
            $features = [];
            foreach ($pois as $count => $poi) {
                $feature = $poi->getEmptyGeojson();
                if (isset($feature["properties"])) {
                    $feature["properties"]["name"] = $poi->name;
                    $feature["properties"]["visits"] = (11 - $count) * 10;
                }

                $features[] = $feature;
            }
            $geoJson["features"] = $features;

            return json_encode($geoJson);
        }
    }

    public function getUGCPoiGeojson($app_id)
    {
        $pois = UgcPoi::where('app_id', $app_id)->get();

        if ($pois->count() > 0) {
            $geoJson = ["type" => "FeatureCollection"];
            $features = [];
            foreach ($pois as $count => $poi) {
                $feature = $poi->getEmptyGeojson();
                if (isset($feature["properties"])) {
                    $feature["properties"]["view"] = '/resources/ugc-pois/' . $poi->id;
                }

                $features[] = $feature;
            }
            $geoJson["features"] = $features;

            return json_encode($geoJson);
        }
    }

    public function getUGCMediaGeojson($app_id)
    {
        $medias = UgcMedia::where('app_id', $app_id)->get();

        if ($medias->count() > 0) {
            $geoJson = ["type" => "FeatureCollection"];
            $features = [];
            foreach ($medias as $count => $media) {
                $feature = $media->getEmptyGeojson();
                if (isset($feature["properties"])) {
                    $feature["properties"]["view"] = '/resources/ugc-medias/' . $media->id;
                }

                $features[] = $feature;
            }
            $geoJson["features"] = $features;

            return json_encode($geoJson);
        }
    }

    public function getiUGCTrackGeojson($app_id)
    {
        $tracks = UgcTrack::where('app_id', $app_id)->get();

        if ($tracks->count() > 0) {
            $geoJson = ["type" => "FeatureCollection"];
            $features = [];
            foreach ($tracks as $count => $track) {
                $feature = $track->getEmptyGeojson();
                if (isset($feature["properties"])) {
                    $feature["properties"]["view"] = '/resources/ugc-tracks/' . $track->id;
                }

                $features[] = $feature;
            }
            $geoJson["features"] = $features;

            return json_encode($geoJson);
        }
    }

    function BuildConfJson()
    {
        $confUri = $this->id . ".json";
        $json = $this->config();
        $jidoTime = $this->config_get_jido_time();
        if (!is_null($jidoTime)) {
            $json['JIDO_UPDATE_TIME'] = $jidoTime;
        }
        Storage::disk('conf')->put($confUri, json_encode($json));
        return $json;
    }

    public function GenerateAppConfig()
    {
        $this->BuildConfJson();
    }

    public function config_update_jido_time()
    {
        $confUri = $this->id . ".json";
        if (Storage::disk('conf')->exists($confUri)) {
            $json = json_decode(Storage::disk('conf')->get($confUri));
            $json->JIDO_UPDATE_TIME = floor(microtime(true) * 1000);
            Storage::disk('conf')->put($confUri, json_encode($json));
        }
    }
    public function config_get_jido_time()
    {
        $confUri = $this->id . ".json";
        if (Storage::disk('conf')->exists($confUri)) {
            $json = json_decode(Storage::disk('conf')->get($confUri));
            if (isset($json->JIDO_UPDATE_TIME)) {
                return $json->JIDO_UPDATE_TIME;
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * @param string $url
     * @param string $type
     * @param string $posts
     */
    private function _curlExec(string $url, string $type, string $posts): void
    {
        Log::info("CURL EXEC TYPE:$type URL:$url");

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $type,
            CURLOPT_POSTFIELDS => $posts,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Basic ' . config('services.elastic.key')
            ),
        ));
        if (str_contains(env('ELASTIC_HOST'), 'localhost')) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        }
        $response = curl_exec($curl);
        if ($response === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }
        curl_close($curl);
    }
}