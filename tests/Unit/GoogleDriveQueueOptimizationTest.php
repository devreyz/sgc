<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use App\Support\NotificationEventCatalog;
use Tests\TestCase;

class GoogleDriveQueueOptimizationTest extends TestCase
{
    #[Test]
    public function automatic_schedule_compacts_the_queue_instead_of_rescanning_every_document(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        self::assertStringContainsString("Schedule::command('drive:compact-queue')", $schedule);
        self::assertStringNotContainsString("Schedule::command('drive:sync-documents')", $schedule);
        self::assertArrayHasKey('drive:compact-queue', Artisan::all());
    }

    #[Test]
    public function receipt_job_skips_handled_versions_and_discards_permanent_validation_errors(): void
    {
        $job = file_get_contents(base_path('app/Jobs/SyncAssociateReceiptToDrive.php'));

        self::assertStringContainsString('ShouldBeUniqueUntilProcessing', $job);
        self::assertStringContainsString('alreadyHandled($receipt, $fingerprint)', $job);
        self::assertStringContainsString('recordRejected(', $job);
        self::assertStringContainsString('return;', $job);
    }

    #[Test]
    public function receipt_observer_only_schedules_sync_for_meaningful_changes(): void
    {
        $observer = file_get_contents(base_path('app/Observers/AssociateReceiptObserver.php'));

        self::assertStringContainsString('$syncChanged = $receipt->wasRecentlyCreated || $receipt->wasChanged([', $observer);
        self::assertStringContainsString("'delivery_ids', 'total_gross', 'total_fees', 'total_net'", $observer);
    }

    #[Test]
    public function successful_drive_sync_notifies_administrators_and_treasurers_by_default(): void
    {
        $event = NotificationEventCatalog::get('drive.receipt_synced');

        self::assertNotNull($event);
        self::assertSame(['admin', 'tesoureiro'], $event['roles']);
        self::assertTrue($event['pushDefault']);
        self::assertStringContainsString(
            "dispatchToConfiguredRoles('drive.receipt_synced'",
            file_get_contents(base_path('app/Jobs/SyncAssociateReceiptToDrive.php')),
        );
    }
}
