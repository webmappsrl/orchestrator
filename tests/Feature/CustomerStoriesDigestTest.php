<?php

namespace Tests\Feature;

use App\Mail\CustomerStoriesDigest;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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

    /**
     * @test
     */
    public function it_is_sent_to_customer()
    {
        $user = User::factory()->create();

        Mail::fake();

        Mail::to($user->email)->send(new CustomerStoriesDigest($user));

        Mail::assertSent(function (CustomerStoriesDigest $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->hasSubject('Orchestrator - Your stories digest') && $mail->hasFrom(config('mail.from.address'), config('mail.from.name'));
        });
    }
}
