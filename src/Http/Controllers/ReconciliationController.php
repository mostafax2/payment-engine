<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mostafax\PaymentEngine\Jobs\ReconcileTransactionsJob;
use Mostafax\PaymentEngine\Models\ReconciliationReport;
use Mostafax\PaymentEngine\Services\ReconciliationEngine;

final class ReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationEngine $engine) {}

    // POST /api/payment/reconcile
    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gateway'  => 'required|string',
            'from'     => 'required|date',
            'to'       => 'required|date|after_or_equal:from',
            'async'    => 'nullable|boolean',
            'tenant_id'=> 'nullable|string',
        ]);

        if ($data['async'] ?? false) {
            ReconcileTransactionsJob::dispatch(
                $data['gateway'], $data['from'], $data['to'], $data['tenant_id'] ?? null,
            )->onQueue(config('payment-engine.queues.reconcile', 'payment-reconcile'));

            return response()->json(['message' => 'Reconciliation job dispatched.'], 202);
        }

        $result = $this->engine->run($data['gateway'], $data['from'], $data['to'], $data['tenant_id'] ?? null);
        return response()->json(['data' => $result->toArray()]);
    }

    // GET /api/payment/reconciliation/reports
    public function reports(Request $request): JsonResponse
    {
        $reports = ReconciliationReport::query()
            ->when($request->gateway, fn($q) => $q->where('gateway', $request->gateway))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($reports);
    }

    // GET /api/payment/reconciliation/reports/{ulid}
    public function show(string $ulid): JsonResponse
    {
        $report = ReconciliationReport::with('items')->where('ulid', $ulid)->firstOrFail();
        return response()->json(['data' => $report]);
    }
}
