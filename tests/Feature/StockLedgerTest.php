<?php

namespace Tests\Feature;

use App\StockLedger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.clients.broiler' => str_repeat('b', 40), 'store.clients.layer' => str_repeat('l', 40)]);
    }

    private function inventory(): int
    {
        $id = DB::table('medicines')->insertGetId(['name' => 'دواء تجريبي', 'minimum_stock' => 2, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[2, '100.25', '2026-01-01'], [3, '120.50', '2026-01-02']] as [$q,$price,$date]) {
            app(StockLedger::class)->receive(['medicine_id' => $id, 'request_id' => (string) Str::uuid(), 'quantity' => $q, 'unit_price' => $price, 'received_on' => $date, 'kind' => 'purchase']);
        }

        return $id;
    }

    private function payload(int $id): array
    {
        return ['medicine_id' => $id, 'quantity' => 3, 'request_id' => (string) Str::uuid(), 'destination_id' => '1', 'destination' => 'عنبر ١ / الدورة الأولى', 'expected_total' => 32100];
    }

    public function test_fifo_cost_and_retries_charge_inventory_once(): void
    {
        $id = $this->inventory();
        $data = $this->payload($id);
        $this->withToken(str_repeat('b', 40))->postJson('/api/v1/quotes', ['medicine_id' => $id, 'quantity' => 3])->assertJsonPath('data.total_cost', 32100);
        $this->postJson('/api/v1/issues', $data)->assertOk()->assertJsonPath('data.total_cost', 32100);
        $this->postJson('/api/v1/issues', $data)->assertOk();
        $this->assertDatabaseCount('issues', 1);
        $this->assertSame(2, (int) DB::table('receipts')->sum('remaining'));
    }

    public function test_return_restores_original_cost_and_cannot_be_repeated_or_cross_client(): void
    {
        $id = $this->inventory();
        $data = $this->payload($id);
        $this->withToken(str_repeat('b', 40))->postJson('/api/v1/issues', $data)->assertOk();
        $this->withToken(str_repeat('l', 40))->postJson('/api/v1/issues/'.$data['request_id'].'/return')->assertNotFound();
        $this->withToken(str_repeat('b', 40))->postJson('/api/v1/issues/'.$data['request_id'].'/return')->assertOk();
        $this->postJson('/api/v1/issues/'.$data['request_id'].'/return')->assertOk();
        $this->assertSame(5, (int) DB::table('receipts')->sum('remaining'));
        $this->assertSame(56200, app(StockLedger::class)->medicines()->first()->value);
    }

    public function test_shortage_fraction_and_changed_cost_do_not_deduct_stock(): void
    {
        $id = $this->inventory();
        $data = $this->payload($id);
        foreach ([['quantity' => 6], ['quantity' => 1.5], ['expected_total' => 1]] as $change) {
            $this->withToken(str_repeat('b', 40))->postJson('/api/v1/issues', array_replace($data, $change))->assertUnprocessable();
        }
        $this->assertDatabaseCount('issues', 0);
        $this->assertSame(5, (int) DB::table('receipts')->sum('remaining'));
    }

    public function test_reused_request_with_changed_quantity_returns_409(): void
    {
        $id = $this->inventory();
        $data = $this->payload($id);
        $this->withToken(str_repeat('b', 40))->postJson('/api/v1/issues', $data)->assertOk();
        $this->postJson('/api/v1/issues', array_replace($data, ['quantity' => 1]))->assertConflict();
        $this->assertDatabaseCount('issues', 1);
    }

    public function test_api_and_web_require_credentials(): void
    {
        $this->getJson('/api/v1/medicines')->assertUnauthorized();
        $this->get('/')->assertRedirect('/login');
        config(['store.password' => 'test-password']);
        $this->post('/login', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/login', ['password' => 'test-password'])->assertRedirect('/');
        $this->get('/')->assertSee('نظرة على المخزن');
    }

    public function test_purchase_form_replay_is_idempotent_and_html_is_escaped(): void
    {
        $this->withSession(['store_unlocked' => true])->post('/medicines', ['name' => '<script>alert(1)</script>', 'minimum_stock' => 1])->assertRedirect();
        $data = ['medicine_id' => DB::table('medicines')->value('id'), 'quantity' => 10, 'unit_price' => '25.50', 'request_id' => (string) Str::uuid(), 'received_on' => today()->toDateString(), 'kind' => 'opening'];
        $this->post('/receipts', $data)->assertRedirect();
        $this->post('/receipts', $data)->assertRedirect();
        $this->assertDatabaseCount('receipts', 1);
        $this->get('/?tab=medicines')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        foreach (['overview', 'purchases', 'movements', 'reports'] as $tab) {
            $this->get('/?tab='.$tab)->assertOk();
        }
    }

    public function test_receipt_creates_new_medicine_and_reuses_it_on_replay_and_next_purchase(): void
    {
        $data = ['medicine_name' => '  دواء   جديد  ', 'minimum_stock' => 4, 'quantity' => 5, 'unit_price' => '12.50', 'request_id' => (string) Str::uuid(), 'received_on' => today()->toDateString(), 'kind' => 'purchase'];
        $this->withSession(['store_unlocked' => true])->post('/receipts', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/receipts', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('medicines', 1);
        $this->assertDatabaseCount('receipts', 1);
        $this->assertDatabaseHas('medicines', ['name' => 'دواء جديد', 'minimum_stock' => 4]);
        $data['request_id'] = (string) Str::uuid();
        $data['minimum_stock'] = 0;
        $this->post('/receipts', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('medicines', 1);
        $this->assertDatabaseHas('medicines', ['minimum_stock' => 4]);
        $this->assertSame(10, (int) DB::table('receipts')->sum('remaining'));
    }

    public function test_invalid_receipt_does_not_leave_new_medicine_and_conflicting_replay_rolls_back(): void
    {
        $id = $this->inventory();
        $data = ['medicine_name' => 'اسم جديد', 'quantity' => 1.5, 'unit_price' => '12.50', 'request_id' => (string) Str::uuid(), 'received_on' => today()->toDateString(), 'kind' => 'purchase'];
        $this->withSession(['store_unlocked' => true])->post('/receipts', $data)->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('medicines', 1);
        $data['quantity'] = 1;
        $data['request_id'] = DB::table('receipts')->where('medicine_id', $id)->value('request_id');
        $this->post('/receipts', $data)->assertConflict();
        $this->assertDatabaseCount('medicines', 1);
        $this->assertDatabaseCount('receipts', 2);
    }

    public function test_empty_store_shows_combined_receipt_form(): void
    {
        $this->withSession(['store_unlocked' => true])->get('/?tab=purchases')
            ->assertSee('name="medicine_name"', false)->assertSee('name="quantity"', false)
            ->assertDontSee('أضف الدواء أولًا، وبعدها سجل الوارد.');
    }
}
