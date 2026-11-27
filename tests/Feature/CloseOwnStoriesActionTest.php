<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Laravel\Nova\Notifications\NovaNotification;
use Tests\TestCase;

/**
 * Chiusura delle proprie Story da parte del cliente (oc:9032).
 *
 * I test passano dall'endpoint di esecuzione di Nova e non da handle():
 * è l'endpoint che riceve gli id dal client, ed è lì che va dimostrato che
 * una Story di un altro utente non si chiude.
 */
class CloseOwnStoriesActionTest extends TestCase
{
    use DatabaseTransactions;

    private const RESOURCE = 'story-showed-by-customers';

    private const ENDPOINT = '/nova-api/' . self::RESOURCE . '/action?action=close-own-stories';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['roles' => [UserRole::Customer]]);
    }

    private function makeStory(User $creator, StoryStatus $status, array $attributes = []): Story
    {
        $story = Story::factory()->create(array_merge([
            'status' => $status->value,
            'user_id' => null,
            'tester_id' => null,
            'epic_id' => null,
        ], $attributes));

        // L'hook created sovrascrive creator_id con l'utente autenticato:
        // lo si imposta qui, senza passare dagli hook.
        $story->creator_id = $creator->id;
        $story->saveQuietly();

        return $story->fresh();
    }

    private function runAction(array $storyIds, ?User $actor = null)
    {
        return $this->actingAs($actor ?? $this->customer)->postJson(self::ENDPOINT, [
            'resources' => $storyIds,
        ]);
    }

    public function test_customer_closes_own_stories_from_any_status()
    {
        $stories = collect([StoryStatus::New, StoryStatus::Progress, StoryStatus::Released])
            ->map(fn (StoryStatus $status) => $this->makeStory($this->customer, $status));

        $response = $this->runAction($stories->pluck('id')->all())->assertOk();

        foreach ($stories as $story) {
            $this->assertSame(StoryStatus::Done->value, $story->fresh()->status);
        }
        $this->assertSame(
            __(':closed request(s) marked as done.', ['closed' => 3]),
            $response->json('message')
        );
    }

    /**
     * Con più id Nova applica l'indexQuery della Resource, che filtra per
     * creator_id: la Story altrui non arriva all'Action e resta invariata.
     */
    public function test_story_of_another_user_in_multiple_selection_is_not_closed()
    {
        $other = User::factory()->create(['roles' => [UserRole::Customer]]);
        $own = [
            $this->makeStory($this->customer, StoryStatus::New),
            $this->makeStory($this->customer, StoryStatus::Todo),
        ];
        $foreign = $this->makeStory($other, StoryStatus::Progress);

        $response = $this->runAction([$own[0]->id, $own[1]->id, $foreign->id])->assertOk();

        $this->assertSame(StoryStatus::Done->value, $own[0]->fresh()->status);
        $this->assertSame(StoryStatus::Done->value, $own[1]->fresh()->status);
        $this->assertSame(StoryStatus::Progress->value, $foreign->fresh()->status);
        $this->assertSame(
            __(':closed request(s) marked as done.', ['closed' => 2]),
            $response->json('message')
        );
    }

    /**
     * Con un id solo Nova applica il detailQuery, che non filtra: la Story
     * altrui arriva all'Action, ed è il controllo in handle() a saltarla.
     * Dall'interfaccia il caso non si presenta: serve contro una richiesta
     * costruita a mano.
     */
    public function test_single_story_of_another_user_is_not_closed()
    {
        $other = User::factory()->create(['roles' => [UserRole::Customer]]);
        $foreign = $this->makeStory($other, StoryStatus::Progress);

        $response = $this->runAction([$foreign->id])->assertOk();

        $this->assertSame(StoryStatus::Progress->value, $foreign->fresh()->status);
        $this->assertSame(
            __(':closed request(s) marked as done.', ['closed' => 0]),
            $response->json('message')
        );
    }

    public function test_story_of_another_user_is_not_closed_through_the_stories_resource()
    {
        $other = User::factory()->create(['roles' => [UserRole::Customer]]);
        $foreign = $this->makeStory($other, StoryStatus::Progress);

        $this->actingAs($this->customer)->postJson('/nova-api/stories/action?action=close-own-stories', [
            'resources' => [$foreign->id],
        ])->assertOk();

        $this->assertSame(StoryStatus::Progress->value, $foreign->fresh()->status);
    }

    public function test_already_done_story_is_not_counted()
    {
        $done = $this->makeStory($this->customer, StoryStatus::Done);
        $todo = $this->makeStory($this->customer, StoryStatus::Todo);

        $response = $this->runAction([$done->id, $todo->id])->assertOk();

        $this->assertSame(StoryStatus::Done->value, $todo->fresh()->status);
        $this->assertSame(
            __(':closed request(s) marked as done.', ['closed' => 1]),
            $response->json('message')
        );
    }

    public function test_children_are_not_closed()
    {
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
        $parent = $this->makeStory($this->customer, StoryStatus::Progress);
        $child = $this->makeStory($developer, StoryStatus::Todo, ['parent_id' => $parent->id]);

        $this->runAction([$parent->id])->assertOk();

        $this->assertSame(StoryStatus::Done->value, $parent->fresh()->status);
        $this->assertSame(StoryStatus::Todo->value, $child->fresh()->status);
    }

    public function test_action_is_available_to_customers_only()
    {
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);

        $customerActions = $this->actingAs($this->customer)
            ->getJson('/nova-api/' . self::RESOURCE . '/actions')
            ->assertOk()
            ->json('actions.*.uriKey');
        $developerActions = $this->actingAs($developer)
            ->getJson('/nova-api/' . self::RESOURCE . '/actions')
            ->assertOk()
            ->json('actions.*.uriKey');

        $this->assertContains('close-own-stories', $customerActions);
        $this->assertNotContains('close-own-stories', $developerActions);
    }

    public function test_developer_and_tester_are_notified()
    {
        Notification::fake();
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
        $tester = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = $this->makeStory($this->customer, StoryStatus::Progress, [
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
        ]);

        $this->runAction([$story->id])->assertOk();

        Notification::assertSentToTimes($developer, NovaNotification::class, 1);
        Notification::assertSentToTimes($tester, NovaNotification::class, 1);
        Notification::assertSentTo($developer, NovaNotification::class, function (NovaNotification $notification) use ($story) {
            return $notification->toArray()['message'] === __('The story :id has been closed by :name', [
                'id' => $story->id,
                'name' => $this->customer->name,
            ]);
        });
    }

    public function test_single_notification_when_developer_is_tester()
    {
        Notification::fake();
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = $this->makeStory($this->customer, StoryStatus::Test, [
            'user_id' => $developer->id,
            'tester_id' => $developer->id,
        ]);

        $this->runAction([$story->id])->assertOk();

        Notification::assertSentToTimes($developer, NovaNotification::class, 1);
    }

    public function test_no_notification_without_assignees()
    {
        Notification::fake();
        $story = $this->makeStory($this->customer, StoryStatus::Backlog);

        $this->runAction([$story->id])->assertOk();

        $this->assertSame(StoryStatus::Done->value, $story->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_foreign_and_already_done_stories_do_not_notify()
    {
        Notification::fake();
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
        $other = User::factory()->create(['roles' => [UserRole::Customer]]);
        $foreign = $this->makeStory($other, StoryStatus::Progress, ['user_id' => $developer->id]);
        $done = $this->makeStory($this->customer, StoryStatus::Done, ['user_id' => $developer->id]);

        $this->runAction([$foreign->id])->assertOk();
        $this->runAction([$done->id])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_story_log_records_the_change()
    {
        $story = $this->makeStory($this->customer, StoryStatus::Todo);

        $this->runAction([$story->id])->assertOk();

        $logs = StoryLog::where('story_id', $story->id)
            ->whereRaw("changes::jsonb ->> 'status' = ?", [StoryStatus::Done->value])
            ->get();
        $this->assertCount(1, $logs);
        $this->assertSame($this->customer->id, $logs->first()->user_id);
    }
}
