<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Models\Transaction;
use Mostafax\PaymentEngine\Services\PaymentManager;

final class PaymentController extends Controller
{
    public function __construct(private readonly PaymentManager $manager) {}

    // POST /api/payment/initiate
    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway'     => 'required|string',
            'track_id'    => 'required|string|unique:' . config('payment-engine.tables.transactions', 'pe_transactions') . ',track_id',
            'amount'      => 'required|numeric|min:0.001',
            'currency'    => 'nullable|string|size:3',
            'success_url' => 'required|url',
            'error_url'   => 'required|url',
            'order_id'    => 'nullable|string',
            'description' => 'nullable|string|max:255',
            'metadata'    => 'nullable|array',
        ]);

        $dto    = InitiatePaymentDTO::fromArray($data);
        $result = $this->manager->initiate($dto);

        return response()->json(['data' => $result], 201);
    }

    // GET /api/payment/transactions
    public function index(Request $request): JsonResponse
    {
        $txns = Transaction::query()
            ->when($request->gateway,   fn($q) => $q->forGateway($request->gateway))
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->from,      fn($q) => $q->where('created_at', '>=', $request->from))
            ->when($request->to,        fn($q) => $q->where('created_at', '<=', $request->to))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($txns);
    }

    // GET /api/payment/transactions/{ulid}
    public function show(string $ulid): JsonResponse
    {
        $txn = Transaction::with('events')->where('ulid', $ulid)->firstOrFail();
        return response()->json(['data' => $txn]);
    }

    // POST /api/payment/inquire/{trackId}
    public function inquire(Request $request, string $trackId): JsonResponse
    {
        $request->validate(['gateway' => 'required|string']);

        $response = $this->manager->inquire($trackId, $request->gateway);
        return response()->json(['data' => $response->toArray()]);
    }
}
