<?php

namespace App\Http\Controllers;

use App\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function login(Request $request): mixed
    {
        if ($request->isMethod('post')) {
            $data = $request->validate(['password' => 'required|string']);
            if (! config('store.password') || ! hash_equals(config('store.password'), $data['password'])) {
                return back()->withErrors(['password' => 'كلمة المرور غير صحيحة أو لم يتم إعدادها.']);
            }
            $request->session()->regenerate();
            $request->session()->put('store_unlocked', true);

            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function logout(Request $request): mixed
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function index(Request $request, StockLedger $ledger): mixed
    {
        $medicines = $ledger->medicines();
        $filters = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'client' => 'nullable|in:broiler,layer', 'destination' => 'nullable|string|max:100']);
        $issues = DB::table('issues')->join('medicines', 'medicines.id', '=', 'issues.medicine_id')->select('issues.*', 'medicines.name');
        if ($request->filled('from')) {
            $issues->whereDate('issues.created_at', '>=', $filters['from']);
        }
        if ($request->filled('to')) {
            $issues->whereDate('issues.created_at', '<=', $filters['to']);
        }
        if ($request->filled('client')) {
            $issues->where('client', $filters['client']);
        }
        if ($request->filled('destination')) {
            $issues->where('destination', 'like', '%'.$filters['destination'].'%');
        }
        $total = (clone $issues)->whereNull('returned_at')->sum('total_cost');
        $issues = $issues->orderByDesc('issues.id')->paginate(20)->withQueryString();
        $receipts = DB::table('receipts')->join('medicines', 'medicines.id', '=', 'receipts.medicine_id')->select('receipts.*', 'medicines.name')->orderByDesc('receipts.id')->paginate(20, ['*'], 'receipts_page');

        return view('dashboard', compact('medicines', 'issues', 'receipts', 'total') + ['requestId' => (string) Str::uuid()]);
    }

    public function medicine(Request $request): mixed
    {
        $data = $request->validate(['name' => 'required|string|max:150|unique:medicines,name', 'minimum_stock' => 'required|integer|min:0|max:1000000']);
        DB::table('medicines')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'تمت إضافة الدواء.');
    }

    public function receive(Request $request, StockLedger $ledger): mixed
    {
        if ($request->filled('medicine_name')) {
            $request->merge(['medicine_name' => preg_replace('/\s+/u', ' ', trim($request->string('medicine_name')->toString()))]);
        }
        $data = $request->validate(['medicine_name' => 'required_without:medicine_id|nullable|string|max:150', 'medicine_id' => 'required_without:medicine_name|nullable|integer|exists:medicines,id', 'minimum_stock' => 'nullable|integer|min:0|max:1000000', 'request_id' => 'required|uuid', 'quantity' => 'required|integer|min:1|max:1000000', 'unit_price' => 'required|numeric|min:0|max:1000000|decimal:0,2', 'received_on' => 'required|date|before_or_equal:today', 'kind' => 'required|in:purchase,opening', 'supplier' => 'nullable|string|max:150']);
        DB::transaction(function () use ($data, $ledger): void {
            if (! empty($data['medicine_name'])) {
                $name = $data['medicine_name'];
                DB::table('medicines')->insertOrIgnore(['name' => $name, 'minimum_stock' => $data['minimum_stock'] ?? 0, 'created_at' => now(), 'updated_at' => now()]);
                $data['medicine_id'] = DB::table('medicines')->where('name', $name)->value('id');
            }
            $ledger->receive($data);
        }, 5);

        return redirect()->route('dashboard', ['tab' => 'purchases'])->with('status', 'تمت إضافة الوارد للمخزن.');
    }
}
