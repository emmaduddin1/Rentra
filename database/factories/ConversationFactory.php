<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Reviewed]);

        return [
            'property_id' => $application->property_id,
            'application_id' => $application->id,
            'renter_id' => $application->renter_id,
            'owner_id' => $application->property->owner_id,
            'is_locked' => false,
            'last_message_at' => null,
        ];
    }
}
