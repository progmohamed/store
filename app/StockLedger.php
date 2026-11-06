<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockLedger
{
    public function medicines(): Collection
    {
        return DB::table('medicines')->orderBy('name')->get()->map(function ($m) {
            $m->stock = (int) DB::table('receipts')->where('medicine_id', $m->id)->sum('remaining');
            $m->value = (int) DB::table('receipts')->where('medicine_id', $m->id)->selectRaw('COALESCE(SUM(remaining * unit_cost),0) as total')->value('total');
            $m->unit = 'وحدة';

            return $m;
        });
    }

    public function receive(array $data): void
    {
        DB::transaction(function () use ($data): void {
            DB::table('medicines')->where('id', $data['medicine_id'])->lockForUpdate()->firstOrFail();
            $old = DB::table('receipts')->where('request_id', $data['request_id'])->first();
            $cost = (int) round(((float) $data['unit_price']) * 100);
            if ($old) {
                abort_unless($old->medicine_id == $data['medicine_id'] && $old->quantity == $data['quantity'] && $old->unit_cost === $cost, 409);

                return;
            }
            DB::table('receipts')->insert(['medicine_id' => $data['medicine_id'], 'request_id' => $data['request_id'], 'received_on' => $data['received_on'], 'kind' => $data['kind'], 'quantity' => $data['quantity'], 'remaining' => $data['quantity'], 'unit_cost' => $cost, 'supplier' => $data['supplier'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        }, 5);
    }

    public function quote(int $medicine, int $quantity): array
    {
        $remaining = $quantity;
        $total = 0;
        $parts = [];
        foreach (DB::table('receipts')->where('medicine_id', $medicine)->where('remaining', '>', 0)->orderBy('received_on')->orderBy('id')->get() as $receipt) {
            $take = min($remaining, $receipt->remaining);
            $total += $take * $receipt->unit_cost;
            $parts[] = ['receipt_id' => $receipt->id, 'quantity' => $take, 'unit_cost' => $receipt->unit_cost];
            $remaining -= $take;
            if ($remaining === 0) {
                break;
            }
        }
        if ($remaining > 0 || $quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'الكمية المطلوبة غير متاحة في المخزن.']);
        }

        return ['total_cost' => $total, 'quantity' => $quantity, 'allocations' => $parts];
    }

    public function issue(string $client, array $data): object
    {
        return DB::transaction(function () use ($client, $data): object {
            DB::table('medicines')->where('id', $data['medicine_id'])->lockForUpdate()->firstOrFail();
            $old = DB::table('issues')->where('client', $client)->where('request_id', $data['request_id'])->first();
            if ($old) {
                abort_unless($old->medicine_id == $data['medicine_id'] && $old->quantity == $data['quantity'] && $old->destination_id === $data['destination_id'] && $old->total_cost == $data['expected_total'], 409, 'الطلب مسجل ببيانات مختلفة.');

                return $old;
            }
            $quote = $this->quote($data['medicine_id'], $data['quantity']);
            if ($quote['total_cost'] != $data['expected_total']) {
                throw ValidationException::withMessages(['quantity' => 'تغيرت تكلفة المخزون. راجع التكلفة وأكد الطلب من جديد.']);
            }
            $id = DB::table('issues')->insertGetId(['client' => $client, 'request_id' => $data['request_id'], 'medicine_id' => $data['medicine_id'], 'destination_id' => $data['destination_id'], 'destination' => $data['destination'], 'quantity' => $data['quantity'], 'total_cost' => $quote['total_cost'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($quote['allocations'] as $part) {
                DB::table('allocations')->insert(['issue_id' => $id, ...$part]);
                $changed = DB::table('receipts')->where('id', $part['receipt_id'])->where('remaining', '>=', $part['quantity'])->decrement('remaining', $part['quantity']);
                abort_unless($changed === 1, 409, 'تغير رصيد المخزن. أعد المحاولة.');
            }

            return DB::table('issues')->find($id);
        }, 5);
    }

    public function returnIssue(string $client, string $requestId): object
    {
        return DB::transaction(function () use ($client, $requestId): object {
            $issue = DB::table('issues')->where('client', $client)->where('request_id', $requestId)->firstOrFail();
            DB::table('medicines')->where('id', $issue->medicine_id)->lockForUpdate()->firstOrFail();
            $issue = DB::table('issues')->where('id', $issue->id)->lockForUpdate()->first();
            if (! $issue->returned_at) {
                foreach (DB::table('allocations')->where('issue_id', $issue->id)->get() as $part) {
                    DB::table('receipts')->where('id', $part->receipt_id)->increment('remaining', $part->quantity);
                }
                DB::table('issues')->where('id', $issue->id)->update(['returned_at' => now(), 'updated_at' => now()]);
            }

            return DB::table('issues')->find($issue->id);
        }, 5);
    }
}
