<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PromoCodeBenefit;
use App\Http\Requests\PromoCode\SavePromoCodeRequest;
use App\Http\Resources\PromoCodeRedemptionResource;
use App\Http\Resources\PromoCodeResource;
use App\Models\PromoCode;
use App\Repositories\PromoCodeRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PromoCodeController extends Controller
{
    public function __construct(private readonly PromoCodeRepository $promoCodes) {}

    public function index(Request $request): Response
    {
        return $this->inertia('PromoCodes/Index', [
            'promo_codes' => PromoCodeResource::collection($this->promoCodes->paginate())->response($request)->getData(true),
        ]);
    }

    public function create(): Response
    {
        return $this->form();
    }

    public function store(SavePromoCodeRequest $request): RedirectResponse
    {
        $this->promoCodes->save($request->toData());

        return redirect()->route('promo-codes.index')->with('success', 'Промокод создан.');
    }

    public function show(Request $request, PromoCode $promoCode): Response
    {
        return $this->inertia('PromoCodes/History', [
            'promo_code' => (new PromoCodeResource($promoCode))->resolve($request),
            'redemptions' => PromoCodeRedemptionResource::collection($this->promoCodes->history($promoCode))->response($request)->getData(true),
        ]);
    }

    public function edit(PromoCode $promoCode): Response
    {
        return $this->form($promoCode);
    }

    public function update(SavePromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $this->promoCodes->save($request->toData(), $promoCode);

        return redirect()->route('promo-codes.index')->with('success', 'Промокод сохранён.');
    }

    public function destroy(PromoCode $promoCode): RedirectResponse
    {
        $promoCode->delete();

        return redirect()->route('promo-codes.index')->with('success', 'Промокод удалён.');
    }

    private function form(?PromoCode $promoCode = null): Response
    {
        return $this->inertia('PromoCodes/Form', [
            'promo_code' => $promoCode === null ? null : (new PromoCodeResource($promoCode))->resolve(),
            'submit_url' => $promoCode === null
                ? route('promo-codes.store')
                : route('promo-codes.update', $promoCode),
            'benefits' => array_map(
                fn (PromoCodeBenefit $benefit): array => ['value' => $benefit->value, 'label' => $benefit->label()],
                PromoCodeBenefit::cases(),
            ),
        ]);
    }
}
