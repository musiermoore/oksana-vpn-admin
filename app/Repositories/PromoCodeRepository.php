<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\PromoCode\PromoCodeData;
use App\DTOs\PromoCode\PromoCodeQuote;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PromoCodeRepository
{
    public function paginate(): LengthAwarePaginator
    {
        return PromoCode::withTrashed()
            ->withCount(['redemptions as redeemed_count' => fn (Builder $query) => $query->whereNotNull('redeemed_at')])
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function history(PromoCode $promoCode): LengthAwarePaginator
    {
        return $promoCode->redemptions()->with('user')->orderByDesc('id')->paginate(50);
    }

    public function save(PromoCodeData $data, ?PromoCode $promoCode = null): void
    {
        ($promoCode ?? new PromoCode)->fill($data->toArray())->save();
    }

    public function findByCode(string $code): ?PromoCode
    {
        return PromoCode::query()->where('code', PromoCode::normalize($code))->first();
    }

    public function hasNonTrialSubscription(User $user): bool
    {
        return $user->subscriptions()->where('source', '!=', 'trial')->exists();
    }

    public function hasUse(PromoCode $promoCode, User $user): bool
    {
        return $promoCode->redemptions()
            ->where('user_id', $user->id)
            ->whereNull('canceled_at')
            ->where(fn (Builder $query) => $query->whereNotNull('redeemed_at')->orWhereNotNull('payment_transaction_id'))
            ->exists();
    }

    public function hasPendingWelcomePromo(User $user): bool
    {
        return PromoCodeRedemption::query()
            ->where('user_id', $user->id)
            ->where('new_users_only', true)
            ->whereNotNull('payment_transaction_id')
            ->whereNull('redeemed_at')
            ->whereNull('canceled_at')
            ->exists();
    }

    public function reserve(PromoCodeQuote $quote, User $user, ?Transaction $payment = null): PromoCodeRedemption
    {
        $promoCode = $quote->promoCode;

        return $promoCode->redemptions()->create([
            'user_id' => $user->id,
            'payment_transaction_id' => $payment?->id,
            'code' => $promoCode->code,
            'benefit' => $promoCode->benefit,
            'value' => $promoCode->value,
            'discount_amount' => $quote->discountAmount,
            'new_users_only' => $promoCode->new_users_only,
        ]);
    }

    public function lockForPayment(Transaction $payment): PromoCodeRedemption
    {
        return PromoCodeRedemption::query()
            ->where('payment_transaction_id', $payment->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function cancelPendingPayment(Transaction $payment): void
    {
        PromoCodeRedemption::query()
            ->where('payment_transaction_id', $payment->id)
            ->whereNull('redeemed_at')
            ->whereNull('canceled_at')
            ->update(['canceled_at' => now()]);
    }
}
