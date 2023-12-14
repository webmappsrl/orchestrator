<?php

namespace Tests\fFeature;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendMailWhenStatusUpdatedTest extends TestCase
{
    use DatabaseTransactions;
    /** @test */
    public function it_sends_email_to_tester_when_developer_updates_status()
    {
        Mail::fake();

        $developer = User::factory()->create();
        $tester = User::factory()->create();
        $authUser = $this->actingAs($developer);

        $story = Story::factory()->create([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Progress,
        ]);

        $story->status = StoryStatus::Test;
        $story->save();

        Mail::assertSent(\App\Mail\StoryStatusUpdated::class, function ($mail) use ($story, $tester) {
            return $mail->hasTo($tester->email) &&
                $mail->story->id === $story->id &&
                $mail->user->id === $tester->id;
        });
    }

    /** @test */
    public function it_sends_email_to_developer_when_tester_updates_status()
    {
        Mail::fake();

        $developer = User::factory()->create();
        $tester = User::factory()->create();
        $this->actingAs($tester);

        $story = Story::factory()->create([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Progress,
        ]);

        $story->status = StoryStatus::Done;
        $story->save();

        Mail::assertSent(\App\Mail\StoryStatusUpdated::class, function ($mail) use ($story, $developer) {
            return $mail->hasTo($developer->email) &&
                $mail->story->id === $story->id &&
                $mail->user->id === $developer->id;
        });
    }

    /** @test */
    public function it_does_not_send_email_to_tester_when_developer_and_tester_are_the_same_person()
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $story = Story::factory()->create([
            'user_id' => $user->id,
            'tester_id' => $user->id,
            'status' => StoryStatus::Progress,
        ]);



        $story->status = StoryStatus::Test;
        $story->save();

        Mail::assertNotSent(\App\Mail\StoryStatusUpdated::class);
    }

    /** @test */
    public function it_does_not_send_email_to_developer_when_tester_and_developer_are_the_same_person()
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $story = Story::factory()->create([
            'user_id' => $user->id,
            'tester_id' => $user->id,
            'status' => StoryStatus::Progress,
        ]);

        $story->status = StoryStatus::Done;
        $story->save();

        Mail::assertNotSent(\App\Mail\StoryStatusUpdated::class);
    }
}
