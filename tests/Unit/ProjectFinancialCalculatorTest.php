<?php

namespace Tests\Unit;

use App\Models\CustomerProjectFee;
use App\Models\ProjectFee;
use App\Models\SalesProject;
use App\Services\ProjectFinancialCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProjectFinancialCalculatorTest extends TestCase
{
    private ProjectFinancialCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('bcadd')) {
            $this->markTestSkipped('A extensao BCMath e obrigatoria para os calculos financeiros.');
        }

        $this->calculator = new ProjectFinancialCalculator;
    }

    #[DataProvider('producerCases')]
    public function test_producer_fee_precedence(
        float $legacyPercentage,
        array $modernFees,
        string $expectedDiscounts,
        string $expectedAccruals,
        string $expectedNet,
        int $expectedFeeCount,
    ): void {
        $project = $this->project($legacyPercentage, $modernFees);

        $result = $this->calculator->calculate($project, '100.00');

        $this->assertFinancialResult(
            $result,
            $expectedDiscounts,
            $expectedAccruals,
            $expectedNet,
            $expectedFeeCount,
        );
    }

    public static function producerCases(): array
    {
        return [
            'A: sem taxas do produtor' => [0, [], '0', '0', '100', 0],
            'B: taxa percentual moderna' => [0, [self::fee('Taxa', 'percentage', 'discount', 10)], '10', '0', '90', 1],
            'C: taxa fixa moderna' => [0, [self::fee('Frete', 'fixed', 'discount', 7.5)], '7.5', '0', '92.5', 1],
            'D: multiplas taxas modernas' => [0, [
                self::fee('Taxa', 'percentage', 'discount', 10),
                self::fee('Bonus', 'fixed', 'accrual', 3),
            ], '10', '3', '93', 2],
            'H: legado como fallback' => [5, [], '5', '0', '95', 1],
            'I: taxas modernas substituem legado' => [5, [self::fee('Taxa moderna', 'percentage', 'discount', 10)], '10', '0', '90', 1],
        ];
    }

    #[DataProvider('customerCases')]
    public function test_customer_fees_are_independent_from_producer_and_legacy_fees(
        float $legacyPercentage,
        array $customerFees,
        string $expectedDiscounts,
        string $expectedAccruals,
        string $expectedNet,
        int $expectedFeeCount,
    ): void {
        $project = $this->project($legacyPercentage, [
            self::fee('Taxa do membro', 'percentage', 'discount', 25),
        ]);

        $result = $this->calculator->calculateWithFees(
            $project,
            '100.00',
            new Collection($customerFees),
        );

        $this->assertFinancialResult(
            $result,
            $expectedDiscounts,
            $expectedAccruals,
            $expectedNet,
            $expectedFeeCount,
        );
    }

    public static function customerCases(): array
    {
        return [
            'E: sem taxa do cliente' => [0, [], '0', '0', '100', 0],
            'F: uma taxa do cliente' => [0, [self::customerFee('Taxa cliente', 'percentage', 'discount', 8)], '8', '0', '92', 1],
            'G: multiplas taxas do cliente' => [0, [
                self::customerFee('Taxa cliente', 'percentage', 'discount', 8),
                self::customerFee('Acrescimo cliente', 'fixed', 'accrual', 2),
            ], '8', '2', '94', 2],
            'J: legado nao contamina taxas do cliente' => [5, [
                self::customerFee('Taxa cliente', 'percentage', 'discount', 8),
            ], '8', '0', '92', 1],
        ];
    }

    private function project(float $legacyPercentage, array $fees): SalesProject
    {
        $project = new SalesProject(['admin_fee_percentage' => $legacyPercentage]);
        $project->setRelation('fees', new Collection($fees));

        return $project;
    }

    private static function fee(string $name, string $type, string $nature, float $value): ProjectFee
    {
        return new ProjectFee([
            'name' => $name,
            'type' => $type,
            'nature' => $nature,
            'value' => $value,
            'active' => true,
        ]);
    }

    private static function customerFee(string $name, string $type, string $nature, float $value): CustomerProjectFee
    {
        return new CustomerProjectFee([
            'name' => $name,
            'type' => $type,
            'nature' => $nature,
            'value' => $value,
            'active' => true,
        ]);
    }

    private function assertFinancialResult(
        array $result,
        string $discounts,
        string $accruals,
        string $net,
        int $feeCount,
    ): void {
        $this->assertSame(0, bccomp($discounts, $result['total_discounts'], 4));
        $this->assertSame(0, bccomp($accruals, $result['total_accruals'], 4));
        $this->assertSame(0, bccomp($net, $result['net'], 4));
        $this->assertCount($feeCount, $result['fees']);
    }
}
