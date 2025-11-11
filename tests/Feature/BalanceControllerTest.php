<?php

namespace Tests\Feature;

use App\Http\Services\BalanceService;
use App\Models\User;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected BalanceService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balanceService = $this->createMock(BalanceService::class);
        $this->app->instance(BalanceService::class, $this->balanceService);
    }

    public function test_deposit_creates_balance()
    {
        $user = User::factory()->create();

        $this->balanceService->expects($this->once())
            ->method('deposit')
            ->with($this->callback(function ($data) use ($user) {
                return (int)$data['user_id'] === $user->id
                    && (float)$data['amount'] === 500.0
                    && $data['comment'] === 'deposit test';
            }))
            ->willReturn(['balance' => 500.00, 'transaction_id' => 1]);

        $response = $this->actingAs($user)->postJson('/api/deposit', [
            'user_id' => $user->id,
            'amount' => 500.00,
            'comment' => 'deposit test'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'balance' => 500,
                    'transaction_id' => 1,
                ],
            ]);
    }

    public function test_withdraw_insufficient_funds()
    {
        $user = User::factory()->create();

        $this->balanceService->expects($this->once())
            ->method('withdraw')
            ->willThrowException(new InvalidArgumentException('Insufficient balance'));

        $this->postJson('/api/deposit', [
            'user_id' => $user->id,
            'amount' => 50.00,
            'comment' => 'initial deposit test'
        ]);

        $response = $this->actingAs($user)->postJson('/api/withdraw', [
            'user_id' => $user->id,
            'amount' => 150.00,
            'comment' => 'withdraw test'
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance',
            ]);
    }

    public function test_transfer_move_funds()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->balanceService->expects($this->once())
            ->method('transfer')
            ->with($this->callback(function ($data) use ($user1, $user2) {
                return (int)$data['from_user_id'] === $user1->id
                    && (int)$data['to_user_id'] === $user2->id
                    && (float)$data['amount'] === 150.0
                    && $data['comment'] === 'transfer test';
            }))
            ->willReturn([
                'from_user_id' => $user1->id,
                'to_user_id' => $user2->id,
                'from_user_balance' => 350.00,
                'to_user_balance' => 150.00,
                'transferred_amount' => 150.00,
            ]);

        $response = $this->actingAs($user1)->postJson('/api/transfer', [
            'from_user_id' => $user1->id,
            'to_user_id' => $user2->id,
            'amount' => 150.00,
            'comment' => 'transfer test',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'from_user_id' => $user1->id,
                    'to_user_id' => $user2->id,
                    'from_user_balance' => 350.00,
                    'to_user_balance' => 150.00,
                    'transferred_amount' => 150.00,
                ],
            ]);
    }

    public function test_get_balance()
    {
        $user = User::factory()->create();

        $this->balanceService->expects($this->once())
            ->method('getBalance')
            ->with($this->equalTo($user->id))
            ->willReturn(['user_id' => $user->id, 'balance' => 200.00]);

        $response = $this->actingAs($user)->getJson("/api/balance/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'balance' => 200.00,
                ],
            ]);
    }
}
