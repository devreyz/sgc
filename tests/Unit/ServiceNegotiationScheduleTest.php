<?php

namespace Tests\Unit;

use App\Services\Services\ServiceNegotiationService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceNegotiationScheduleTest extends TestCase
{
    public function test_schedule_accepts_entry_and_different_installment_values(): void
    {
        $schedule = (new ServiceNegotiationService)->normalizeSchedule(1000, [
            ['kind' => 'entry', 'due_date' => '2026-09-19', 'amount' => 150],
            ['kind' => 'installment', 'due_date' => '2026-10-19', 'amount' => 300],
            ['kind' => 'installment', 'due_date' => '2026-11-19', 'amount' => 550],
        ]);

        $this->assertSame('entry', $schedule[0]['kind']);
        $this->assertSame(150.0, $schedule[0]['amount']);
        $this->assertSame(550.0, $schedule[2]['amount']);
    }

    public function test_schedule_rejects_a_total_different_from_the_negotiated_balance(): void
    {
        $this->expectException(ValidationException::class);

        (new ServiceNegotiationService)->normalizeSchedule(1000, [
            ['kind' => 'entry', 'due_date' => '2026-09-19', 'amount' => 100],
            ['kind' => 'installment', 'due_date' => '2026-10-19', 'amount' => 800],
        ]);
    }

    public function test_schedule_rejects_more_than_one_entry(): void
    {
        $this->expectException(ValidationException::class);

        (new ServiceNegotiationService)->normalizeSchedule(200, [
            ['kind' => 'entry', 'due_date' => '2026-09-19', 'amount' => 100],
            ['kind' => 'entry', 'due_date' => '2026-09-20', 'amount' => 100],
        ]);
    }
}
