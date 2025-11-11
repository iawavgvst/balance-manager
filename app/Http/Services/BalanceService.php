<?php

namespace App\Http\Services;

use App\Models\Balance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * @method method(string $string)
 */

class BalanceService
{
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function deposit(array $data): array
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        $comment = $data['comment'] ?? null;

        return DB::transaction(function () use ($userId, $amount, $comment) {

            $this->validateAmount($amount);

            User::findOrFail($userId);

            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            if (!$balance) {
                try {
                    Balance::create([
                        'user_id' => $userId,
                        'amount' => 0,
                    ]);
                } catch (QueryException $exception) {
                    $this->logger->warning('Balance create race', [
                        'user_id' => $userId, 'err' => $exception->getMessage()
                    ]);
                }
            }

            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            $balance->increment('amount', $amount);

            $transaction = Transaction::create([
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $amount,
                'comment' => $comment,
            ]);

            return [
                'balance' => (float) $balance->fresh()->amount,
                'transaction_id' => $transaction->id,
            ];
        });
    }

    public function withdraw(array $data): array
    {
        $userId = (int) $data['user_id'];
        $amount = (float) $data['amount'];
        $comment = $data['comment'] ?? null;

        return DB::transaction(function () use ($userId, $amount, $comment) {

            $this->validateAmount($amount);

            User::findOrFail($userId);

            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            if (!$balance || $balance->amount < $amount) {
                throw new InvalidArgumentException('Insufficient balance');
            }

            $balance->decrement('amount', $amount);

            $transaction = Transaction::create([
                'user_id' => $userId,
                'type' => 'withdraw',
                'amount' => $amount,
                'comment' => $comment,
            ]);

            return [
                'balance' => (float) $balance->fresh()->amount,
                'transaction_id' => $transaction->id,
            ];
        });
    }

    public function transfer(array $data): array
    {
        $fromUserId = (int) $data['from_user_id'];
        $toUserId   = (int) $data['to_user_id'];
        $amount     = (float) $data['amount'];
        $comment    = $data['comment'] ?? null;

        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('Unable to transfer funds to yourself');
        }

        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $comment) {

            $this->validateAmount($amount);

            User::findOrFail($fromUserId);
            User::findOrFail($toUserId);

            $ids = [$fromUserId, $toUserId];
            sort($ids, SORT_NUMERIC);

            $balances = Balance::whereIn('user_id', $ids)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            foreach ($ids as $id) {
                if (!$balances->has($id)) {
                    try {
                        Balance::create([
                            'user_id' => $id,
                            'amount' => 0,
                        ]);
                    } catch (QueryException $exception) {
                        $this->logger->warning('Balance create race during transfer', [
                            'user_id' => $id, 'err' => $exception->getMessage()
                        ]);
                    }
                }
            }

            $balances = Balance::whereIn('user_id', $ids)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $fromBalance = $balances->get($fromUserId);
            $toBalance = $balances->get($toUserId);

            if (!$fromBalance || $fromBalance->amount < $amount) {
                throw new InvalidArgumentException('Insufficient balance');
            }

            $fromBalance->decrement('amount', $amount);
            $toBalance->increment('amount', $amount);

            Transaction::create([
                'user_id' => $fromUserId,
                'type' => 'transfer_out',
                'amount' => $amount,
                'comment' => $comment,
                'related_user_id' => $toUserId,
            ]);

            Transaction::create([
                'user_id' => $toUserId,
                'type' => 'transfer_in',
                'amount' => $amount,
                'related_user_id' => $fromUserId,
                'comment' => $comment,
            ]);

            return [
                'from_user_balance' => (float) $fromBalance->fresh()->amount,
                'to_user_balance' => (float) $toBalance->fresh()->amount,
                'transferred_amount' => $amount,
            ];
        });
    }

    public function getBalance(int $userId): array
    {
        User::findOrFail($userId);

        $balance = Balance::where('user_id', $userId)->first();

        return [
            'user_id' => $userId,
            'balance' => $balance ? (float) $balance->amount : 0.0,
        ];
    }

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('The amount must be greater than 0');
        }

        if ($amount > 100000) {
            throw new InvalidArgumentException('The amount is too large');
        }
    }
}
