<?php

namespace Tests\Unit;

use App\Services\NumberInWordsService;
use PHPUnit\Framework\TestCase;

class NumberInWordsServiceTest extends TestCase
{
    private NumberInWordsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NumberInWordsService;
    }

    public function test_plain_numbers_do_not_receive_a_unit(): void
    {
        $this->assertSame('dois mil e vinte e seis', $this->service->number(2026));
        $this->assertSame('doze vírgula cinco', $this->service->number('12,5'));
    }

    public function test_money_distinguishes_reais_and_cents(): void
    {
        $this->assertSame('um real e um centavo', $this->service->money(1.01));
        $this->assertSame('cinquenta centavos', $this->service->money(0.50));
        $this->assertSame('dois milhões de reais', $this->service->money(2_000_000));
    }

    public function test_percentage_and_quantity_use_their_semantic_units(): void
    {
        $this->assertSame('sete vírgula cinco por cento', $this->service->percentage(7.5));
        $this->assertSame('um quilograma', $this->service->quantity(1, 'quilograma', 'quilogramas'));
        $this->assertSame('dois quilogramas', $this->service->quantity(2, 'quilograma', 'quilogramas'));
    }

    public function test_invalid_or_missing_values_remain_empty(): void
    {
        $this->assertSame('', $this->service->number(null));
        $this->assertSame('', $this->service->money('nao numerico'));
    }
}
