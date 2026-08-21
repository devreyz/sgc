<?php

namespace Tests\Unit;

use App\Enums\CustomerReceiptStatus;
use App\Services\Accounting\AccountingNextActionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AccountingNextActionResolverTest extends TestCase
{
    #[DataProvider('financialStates')]
    public function test_resolves_human_state_and_next_action(
        CustomerReceiptStatus $status,
        string $expectedState,
        string $expectedAction,
    ): void {
        $result = (new AccountingNextActionResolver)->resolve($status);

        self::assertSame($expectedState, $result['state']);
        self::assertSame($expectedAction, $result['next_action_key']);
    }

    public function test_critical_integrity_issue_has_priority_over_financial_status(): void
    {
        $result = (new AccountingNextActionResolver)->resolve(CustomerReceiptStatus::PAID, 1);

        self::assertSame('critical_inconsistency', $result['state']);
        self::assertSame('review_inconsistency', $result['next_action_key']);
        self::assertSame('danger', $result['tone']);
    }

    public static function financialStates(): array
    {
        return [
            'draft' => [CustomerReceiptStatus::DRAFT, 'preparing', 'review_draft'],
            'closed' => [CustomerReceiptStatus::PENDING_PAYMENT, 'ready_for_authorization', 'send_authorization'],
            'partial' => [CustomerReceiptStatus::PARTIALLY_PAID, 'partially_received', 'track_balance'],
            'paid' => [CustomerReceiptStatus::PAID, 'received', 'view_dossier'],
        ];
    }
}
