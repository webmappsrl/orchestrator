<?php

namespace Tests\Unit\Models;

use App\Models\Deadline;
use App\Enums\DeadlineStatus;
use Carbon\Carbon;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class DeadlineTest extends TestCase
{
    /** @test */
    public function it_sets_status_to_expired_if_due_date_has_passed()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => DeadlineStatus::New->value,
        ]);

        $deadline->checkIfExpired();


        $this->assertEquals(DeadlineStatus::Expired->value, $deadline->status);
    }

    /** @test */
    public function it_does_not_set_status_to_expired_if_due_date_has_not_passed()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::tomorrow(),
            'status' => DeadlineStatus::New->value,
        ]);

        $deadline->checkIfExpired();


        $this->assertEquals(DeadlineStatus::New->value, $deadline->status);
    }

    /** @test */
    public function it_does_not_change_the_status_if_already_expired()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => DeadlineStatus::Expired->value,
        ]);

        $deadline->checkIfExpired();


        $this->assertEquals(DeadlineStatus::Expired->value, $deadline->status);
    }

    /** @test */
    public function it_does_not_change_the_status_if_already_done()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => DeadlineStatus::Done->value,
        ]);

        $deadline->checkIfExpired();


        $this->assertEquals(DeadlineStatus::Done->value, $deadline->status);
    }

    /** @test */
    public function it_does_change_the_status_if_in_progress()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => DeadlineStatus::Progress->value,
        ]);

        $deadline->checkIfExpired();

        $this->assertEquals(DeadlineStatus::Expired->value, $deadline->status);
    }

    /** @test */
    public function it_does_change_the_status_if_new()
    {

        $deadline = Deadline::factory()->create([
            'due_date' => Carbon::yesterday(),
            'status' => DeadlineStatus::New->value,
        ]);

        $deadline->checkIfExpired();

        $this->assertEquals(DeadlineStatus::Expired->value, $deadline->status);
    }
}
