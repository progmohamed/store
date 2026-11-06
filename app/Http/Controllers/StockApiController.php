<?php

namespace App\Http\Controllers;

use App\StockLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockApiController extends Controller
{
    public function index(StockLedger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->medicines()->filter(fn ($m) => $m->stock > 0)->values()]);
    }

    public function quote(Request $request, StockLedger $ledger): JsonResponse
    {
        $data = $request->validate(['medicine_id' => 'required|integer|exists:medicines,id', 'quantity' => 'required|integer|min:1|max:1000000']);

        return response()->json(['data' => $ledger->quote($data['medicine_id'], $data['quantity'])]);
    }

    public function store(Request $request, StockLedger $ledger): JsonResponse
    {
        $data = $request->validate(['medicine_id' => 'required|integer|exists:medicines,id', 'quantity' => 'required|integer|min:1|max:1000000', 'request_id' => 'required|uuid', 'destination_id' => 'required|string|max:100', 'destination' => 'required|string|max:255', 'expected_total' => 'required|integer|min:0']);

        return response()->json(['data' => $ledger->issue($request->attributes->get('store_client'), $data)]);
    }

    public function returns(Request $request, string $requestId, StockLedger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->returnIssue($request->attributes->get('store_client'), $requestId)]);
    }
}
