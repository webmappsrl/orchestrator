<?php

namespace App\Console\Commands;

use App\Models\App;
use App\Models\Customer;
use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\Story;
use App\Models\Tag;

class TagAllProjects extends Command
{
    protected $signature = 'tag:projects';
    protected $description = 'Creates a tag for each project and associates it with the project.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $projects = Project::all();  // Carica tutti i progetti
        $apps = App::all();
        $customers = Customer::all();
        $entities = $projects->concat($apps)->concat($customers);

        foreach ($entities as $entity) {
            // Controlla se esiste già un tag con lo stesso nome del progetto
            $tag = Tag::firstOrCreate([
                'name' => class_basename($entity) . ': ' . $entity->name
            ]);
            $tag->taggable()->save($entity);

            // Verifica che il tag non sia già associato al progetto
            if (!$entity->tags->contains($tag)) {
                $entity->tags()->save($tag);
                $this->info("CREATE Tag '{$tag->name}' created and associated with '{$entity->name}'.");
            }
        }

        $stories = Story::all();
        foreach ($stories as $story) {
            // Controlla se esiste già un tag con lo stesso nome del progetto
            $project = $story->project;
            if ($project) {

                $tag = Tag::firstOrCreate([
                    'name' => class_basename($project) . ': ' . $project->name
                ]);
                // Verifica che il tag non sia già associato al progetto
                if (!$story->tags->contains($tag)) {
                    $story->tags()->save($tag);
                    $this->info("ATTACH Tag '{$tag->name}' created and associated with project '{$project->name}'.");
                }
            }
        }

        $this->info('All projects have been tagged successfully.');
    }
}
