<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddPoiAcquisitionFormFieldToApps extends Migration
{

    public $default_json =
    '[
        {
            "id" : "poi",
            "label" : 
            {
                "it" : "Punto di interesse",
                "en" : "Point of interest"
            },
            "fields" :
            [
                {
                    "name" : "title",
                    "type" : "text",
                    "placeholder": {
                        "it" : "Inserisci un titolo",
                        "en" : "Add a title"
                    },
                    "required" : true,
                    "label" : 
                    {
                        "it" : "Titolo",
                        "en" : "Title"
                    }
                },
                {
                    "name" : "waypointtype",
                    "type" : "select",
                    "required" : true,
                    "label" : 
                    {
                        "it" : "Tipo punto di interesse",
                        "en" : "Point of interest type"
                    },
                    "values" : [
                        {
                            "value" : "landscape",
                            "label" :
                            {
                                "it" : "Panorama",
                                "en" : "Landscape"
                            }
                        },
                        {
                            "value" : "place_to_eat",
                            "label" :
                            {
                                "it" : "Luogo dove mangiare",
                                "en" : "Place to eat"
                            }
                        },
                        {
                            "value" : "place_to_sleep",
                            "label" :
                            {
                                "it" : "Luogo dove dormire",
                                "en" : "Place to eat"
                            }
                        },
                        {
                            "value" : "natural",
                            "label" :
                            {
                                "it" : "Luogo di interesse naturalistico",
                                "en" : "Place of naturalistic interest"
                            }
                        },
                        {
                            "value" : "cultural",
                            "label" :
                            {
                                "it" : "Luogo di interesse culturale",
                                "en" : "Place of cultural interest"
                            }
                        },
                        {
                            "value" : "other",
                            "label" :
                            {
                                "it" : "Altri tipi ti luoghi di interesse",
                                "en" : "Other types of Point of interest"
                            }
                        }
                    ]
                },
                {
                    "name" : "description",
                    "type" : "textarea",
                    "placeholder": {
                        "it" : "Se vuoi puoi aggiungere una descrizione",
                        "en" : "You can add a description if you want"
                    },
                    "required" : false,
                    "label" : 
                    {
                        "it" : "Descrizione",
                        "en" : "Title"
                    }
                }
            ] 
        }
    ]';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {


        Schema::table('apps', function (Blueprint $table) {
            $table->text('poi_acquisition_form')->default($this->default_json);
        });
        DB::statement("UPDATE apps SET poi_acquisition_form = '$this->default_json';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('apps', function (Blueprint $table) {
            Schema::table('apps', function (Blueprint $table) {
                $table->dropColumn('poi_acquisition_form');
            });
        });
    }
}
