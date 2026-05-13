<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Console\Command;

class AlignTagsCommand extends Command
{
    protected $signature = 'tags:align';
    protected $description = 'Pulisce i nomi dei tag e allinea i tag su tutti i ticket esistenti';

    public function __construct(private TagService $tagService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cleanTagNames();
        $this->alignCustomerUsers();
        $this->alignQuarterTags();
        $this->alignRepoTags();
        $this->alignCustomerTags();

        $this->info('tags:align completato.');
        return self::SUCCESS;
    }

    private function cleanTagNames(): void
    {
        $this->info('Pulizia nomi tag...');

        Tag::all()->each(function (Tag $tag) {
            $cleaned = $tag->name;
            $cleaned = preg_replace('/^(Project|Customer|App):\s*/i', '', $cleaned);
            $cleaned = preg_replace('/Main project for customer\s*/i', '', $cleaned);
            $cleaned = trim($cleaned);

            if ($cleaned !== $tag->name) {
                $tag->name = $cleaned;
                $tag->saveQuietly();
            }
        });
    }

    private function alignCustomerUsers(): void
    {
        $this->info('Allineamento User → Customer via email...');

        User::whereJsonContains('roles', 'customer')->each(function (User $user) {
            $customer = Customer::where('email', $user->email)->first();
            if ($customer && $customer->associated_user_id !== $user->id) {
                $customer->associated_user_id = $user->id;
                $customer->saveQuietly();
            }
        });
    }

    private function alignQuarterTags(): void
    {
        $this->info('Aggiunta quarter tag ai ticket...');

        Story::chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachQuarterTagToStory($story);
            }
        });
    }

    private function alignRepoTags(): void
    {
        $this->info('Aggiunta tag repository dai link nei ticket...');

        Story::chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachTagsFromTextToStory($story);
            }
        });
    }

    private function alignCustomerTags(): void
    {
        $this->info('Aggiunta tag customer ai ticket creati da utenti customer...');

        Story::whereNotNull('creator_id')->chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachCustomerTagToStory($story);
            }
        });
    }
}
