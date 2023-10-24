<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CustomerStoriesDigestTest extends TestCase
{

    use DatabaseTransactions;

    /**
     * @test
     */
    public function it_has_the_correct_from_address()
    {

        $user = User::factory()->create();

        $mailable = new \App\Mail\CustomerStoriesDigest($user);

        $mailable->assertFrom(config('mail.from.address'), config('mail.from.name'));
    }

    /**
     * @test
     */
    public function it_has_the_correct_to_address()
    {

        $user = User::factory()->create();

        $mailable = new \App\Mail\CustomerStoriesDigest($user);
        $mailable->to($user->email, $user->name);

        $mailable->assertTo($user->email, $user->name);
    }

    /**
     * @test
     */
    public function it_has_the_correct_subject()
    {

        $user = User::factory()->create();

        $mailable = new \App\Mail\CustomerStoriesDigest($user);

        $mailable->assertHasSubject('Orchestrator - Your stories digest');
    }

    /**
     * @test
     */
    public function it_has_the_correct_view()
    {

        $user = User::factory()->create();

        $mailHeader = 'Digest Customer Stories';

        $mailable = new \App\Mail\CustomerStoriesDigest($user);

        $mailable->assertSeeInHtml($mailHeader);

        //get all stories that are in status done from the user
        $doneStories = \App\Models\Story::where('creator_id', $user->id)->where('status', \App\Enums\StoryStatus::Done)->get();

        //get all stories that are in status test from the user
        $testStories = \App\Models\Story::where('creator_id', $user->id)->where('status', \App\Enums\StoryStatus::Test)->get();

        //get all stories that are in status progress from the user
        $progressStories = \App\Models\Story::where('creator_id', $user->id)->where('status', \App\Enums\StoryStatus::Progress)->get();

        if (count($doneStories) > 0) {
            $mailable->assertSeeInHtml('Storie Concluse');
        }

        if (count($testStories) > 0) {
            $mailable->assertSeeInHtml('Storie in Test');
        }

        if (count($progressStories) > 0) {
            $mailable->assertSeeInHtml('Storie in Progress');
        }
    }
}
