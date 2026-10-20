<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;
use App\Models\PaymentProvider;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Models\PayoutRequest;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use App\Enums\UserRole;

#[Group('Owner: finance', "Balans, tranzaksiyalar, pul yechish va to'ldirish (ega).")]
class OwnerFinanceController extends Controller
{
    use ApiResponse;

    /**
     * Get store balance details and recent financial transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        // Get recent ledger transactions (debt/commission)
        $ledgerTransactions = StoreTransaction::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get recent wallet transactions (online settlements/withdrawals)
        $walletTransactions = StoreWalletTransaction::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Get recent payout requests
        $payoutRequests = PayoutRequest::where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $this->success([
            'debt_balance' => (float) $store->debt_balance,
            'wallet_balance' => (float) $store->wallet_balance,
            'credit_limit' => (float) $store->credit_limit,
            'min_payout_reserve' => (float) ($store->min_payout_reserve ?? 100000.00),
            'payment_account_type' => $store->payment_account_type,
            'commission_rate' => (float) $store->commission_rate,
            'status' => $store->status->value ?? $store->status,
            'is_order_locked' => $store->is_order_locked,
            'ledger_transactions' => $ledgerTransactions,
            'wallet_transactions' => $walletTransactions,
            'payout_requests' => $payoutRequests,
        ]);
    }

    /**
     * Create a new payout request.
     */
    public function requestPayout(Request $request, LedgerService $ledger): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        if (is_string($request->input('card_number'))) {
            $request->merge(['card_number' => preg_replace('/\s+/', '', $request->input('card_number'))]);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'card_number' => 'required|string|digits:16',
            'notes' => 'nullable|string|max:500',
        ], [
            'card_number.digits' => 'Karta raqami 16 ta raqamdan iborat bo\'lishi kerak.',
        ]);

        try {
            $payout = $ledger->requestPayout($store, Money::of($data['amount']), $data['card_number'], $data['notes'] ?? null);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success($payout, 'Payout so\'rovi muvaffaqiyatli yuborildi.');
    }

    /**
     * Initiate a store balance top-up payment.
     */
    public function initiateTopup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== UserRole::OWNER) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $store = $user->store;

        if (!$store) {
            return $this->error('Do\'kon topilmadi.', [], 404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'provider' => 'required|string|in:click,payme',
        ]);

        $amount = Money::of($data['amount']);
        $provider = $data['provider'];

        // Create a pending top-up request
        $topupRequest = \App\Models\StoreTopupRequest::create([
            'store_id' => $store->id,
            'amount' => $amount->toString(),
            'provider' => $provider,
            'status' => 'pending',
        ]);

        // Generate payment URL using platform credentials
        $paymentUrl = '';
        $returnUrl = url('/admin'); // Return to platform admin/app dashboard

        if ($provider === 'click') {
            // Platform click credentials (top-ups are always paid to the platform)
            $serviceId = PaymentProvider::platformCredential('click', 'service_id');
            $merchantId = PaymentProvider::platformCredential('click', 'merchant_id');

            $paymentUrl = "https://my.click.uz/services/pay" .
                   "?service_id={$serviceId}" .
                   "&merchant_id={$merchantId}" .
                   "&amount={$amount->toFloat()}" .
                   "&transaction_param=topup_{$topupRequest->uuid}" .
                   "&return_url=" . urlencode($returnUrl);
        } elseif ($provider === 'payme') {
            // Platform payme credentials (top-ups are always paid to the platform)
            $merchantId = PaymentProvider::platformCredential('payme', 'merchant_id');

            $tiyinAmount = $amount->toTiyin();
            $params = "m={$merchantId};ac.order_id=topup_{$topupRequest->uuid};a={$tiyinAmount};c=" . urlencode($returnUrl);
            $paymentUrl = "https://checkout.paycom.uz/" . base64_encode($params);
        }

        return $this->success([
            'topup_uuid' => $topupRequest->uuid,
            'payment_url' => $paymentUrl,
        ], 'Top-up initiated successfully.');
    }
}
