<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
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

    private const CLOSED_MESSAGE = '{1} :count request marked as done.|[0,*] :count requests marked as done.';

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

    private function runAction(array|string $storyIds, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->customer)->postJson(self::ENDPOINT, [
            'resources' => $storyIds,
        ]);
    }

    private function closedMessage(int $count): string
    {
        return trans_choice(self::CLOSED_MESSAGE, $count, ['count' => $count]);
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
            $this->closedMessage(3),
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
            $this->closedMessage(2),
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
            $this->closedMessage(0),
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
            $this->closedMessage(1),
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

    public function test_message_is_singular_for_one_story_in_italian()
    {
        $one = $this->makeStory($this->customer, StoryStatus::Todo);
        $two = [
            $this->makeStory($this->customer, StoryStatus::Todo),
            $this->makeStory($this->customer, StoryStatus::Todo),
        ];

        $this->assertSame('1 richiesta segnata come completata.', $this->runAction([$one->id])->json('message'));
        $this->assertSame('2 richieste segnate come completate.', $this->runAction([$two[0]->id, $two[1]->id])->json('message'));
    }

    public function test_action_is_hidden_on_a_done_story_and_shown_on_a_rejected_one()
    {
        $done = $this->makeStory($this->customer, StoryStatus::Done);
        $rejected = $this->makeStory($this->customer, StoryStatus::Rejected);

        $actionsFor = fn (Story $story) => $this->actingAs($this->customer)
            ->getJson('/nova-api/stories/actions?resourceId=' . $story->id . '&display=detail')
            ->assertOk()
            ->json('actions.*.uriKey');

        $this->assertNotContains('close-own-stories', $actionsFor($done));
        // R1: il cliente può chiudere da qualsiasi stato, anche da rejected.
        $this->assertContains('close-own-stories', $actionsFor($rejected));
    }

    public function test_no_mail_is_sent_on_close()
    {
        Mail::fake();
        $developer = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = $this->makeStory($this->customer, StoryStatus::Progress, ['user_id' => $developer->id]);

        $this->runAction([$story->id])->assertOk();

        $this->assertSame(StoryStatus::Done->value, $story->fresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_select_all_closes_only_own_stories()
    {
        $other = User::factory()->create(['roles' => [UserRole::Customer]]);
        $own = [
            $this->makeStory($this->customer, StoryStatus::New),
            $this->makeStory($this->customer, StoryStatus::Progress),
        ];
        $foreign = $this->makeStory($other, StoryStatus::Progress);

        $response = $this->runAction('all')->assertOk();

        $this->assertSame(StoryStatus::Done->value, $own[0]->fresh()->status);
        $this->assertSame(StoryStatus::Done->value, $own[1]->fresh()->status);
        $this->assertSame(StoryStatus::Progress->value, $foreign->fresh()->status);
        $this->assertSame($this->closedMessage(2), $response->json('message'));
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
