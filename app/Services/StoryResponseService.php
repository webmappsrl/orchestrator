<?php

namespace App\Services;

use App\Models\User;
use App\Models\Story;
use App\Enums\UserRole;
use App\Mail\StoryResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StoryResponseService
{
    public function addResponse(Story $story, string $response, string $field = 'customer_request')
    {
        $sender = auth()->user();
        $style = $this->getStyleForUser($sender, $story);
        $divider = "<div style='height: 2px; background-color: #e2e8f0; margin: 20px 0;'></div>";

        $formattedResponse = $sender->name . " ha risposto il: " . now()->format('d-m-Y H:i') . "\n <div $style> <p>" . $response . " </p> </div>" . $divider;

        // Update the field
        $story->{$field} = $formattedResponse . $story->{$field};
        $story->save();

        if ($field === 'customer_request') {
            $this->handleParticipantsAndNotifications($story, $sender, $response);
        }
    }

    private function getStyleForUser(User $sender, Story $story): string
    {
        $senderRoles = $sender->roles->toArray();

        if (array_search(UserRole::Developer, $senderRoles) !== false) {
            return "style='background-color: #f8f9fa; border-left: 4px solid #6c757d; padding: 10px 20px;'";
        }
        if ($sender->id == $story->tester_id) {
            return "style='background-color: #e6f7ff; border-left: 4px solid #1890ff; padding: 10px 20px;'";
        }
        if (array_search(UserRole::Customer, $senderRoles) !== false) {
            return "style='background-color: #fff7e6; border-left: 4px solid #ffa940; padding: 10px 20px;'";
        }

        return "style='background-color: #d7f7de; border-left: 4px solid #6c757d; padding: 10px 20px;'";
    }

    private function handleParticipantsAndNotifications(Story $story, User $sender, string $response)
    {
        $story->participants()->syncWithoutDetaching([$sender->id]);

        $recipients = $this->collectRecipients($story, $sender);

        foreach ($recipients as $recipientId) {
            $recipient = User::find($recipientId);
            if ($recipient) {
                $this->sendNotificationEmail($story, $recipient, $sender, $response);
            }
        }
    }

    private function collectRecipients(Story $story, User $sender): array
    {
        $recipients = $story->participants->pluck('id')->toArray();

        foreach (['creator_id', 'user_id', 'tester_id'] as $field) {
            if ($story->{$field} && !in_array($story->{$field}, $recipients)) {
                $recipients[] = $story->{$field};
            }
        }

        return array_unique(array_diff($recipients, [$sender->id]));
    }

    private function sendNotificationEmail(Story $story, User $recipient, User $sender, string $response)
    {
        try {
            Mail::to($recipient->email)->send(new StoryResponse($story, $recipient, $sender, $response));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
