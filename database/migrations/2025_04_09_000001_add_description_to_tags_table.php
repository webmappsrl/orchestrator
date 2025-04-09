<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Tag;

class AddDescriptionToTagsTable extends Migration
{
    public function up()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
        // Se il tag è associato a un project, copia la descrizione del project nel tag usando Eloquent
        $tags = Tag::where('taggable_type', 'App\\Models\\Project')->get();
        foreach ($tags as $tag) {
            if ($tag->taggable) {
                $tag->description = $tag->taggable->description;
                $tag->save();
            }
        }
    }

    public function down()
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}
