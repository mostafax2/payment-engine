<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Mostafax\PaymentEngine\Services\PaymentManager;
use Mostafax\PaymentEngine\Services\WebhookProcessor;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookProcessor $processor,
        private readonly PaymentManager   $manager,
    ) {}

    // ANY /payment/{gateway}/success   (KNET / PayTabs)
    public function success(Request $request, string $gateway): mixed
    {
        $transaction = $this->manager->handleCallback($gateway, $request->all());

        if (request()->expectsJson()) {
            return response()->json(['status' => 'ok', 'ulid' => $transaction->ulid]);
        }

        return view('payment-engine::success', compact('transaction'));
    }

    // ANY /payment/{gateway}/error
    public function error(Request $request, string $gateway): mixed
    {
        $transaction = $this->manager->handleCallback($gateway, $request->all());

        if (request()->expectsJson()) {
            return response()->json(['status' => 'failed', 'ulid' => $transaction->ulid]);
        }

        return view('payment-engine::error', compact('transaction'));
    }

    // POST /api/payment/webhook/{gateway}   (Stripe, PayPal, Tap, MyFatoorah push webhooks)
    public function receive(Request $request, string $gateway): Response
    {
        $webhook = $this->processor->persist($request, $gateway);
        $this->processor->queueForProcessing($webhook);

        return response('', 200);
    }
}
