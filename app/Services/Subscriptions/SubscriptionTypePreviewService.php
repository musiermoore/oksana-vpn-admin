<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Models\SubscriptionType;
use App\Models\SubscriptionTypeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class SubscriptionTypePreviewService
{
    public function __construct(private readonly SubscriptionTypeBuilderService $builder) {}

    /** @return array<string, mixed> */
    public function preview(SubscriptionType $savedType, array $data): array
    {
        $user = $this->resolveUser((string) $data['audience'], $data['user_id'] ?? null);
        $type = new SubscriptionType([
            'name' => $savedType->name,
            'slug' => $savedType->slug,
            'kind' => $data['kind'],
            'format' => $data['format'],
            'is_active' => true,
            'show_expired_placeholder' => (bool) $data['show_expired_placeholder'],
        ]);
        $items = collect($data['items'])->values()->map(fn (array $item, int $index): SubscriptionTypeItem => new SubscriptionTypeItem([
            'id' => $index + 1,
            'source_type' => $item['source_type'],
            'source_id' => (int) $item['source_id'],
            'sort_order' => (int) $item['sort_order'],
        ]));
        $type->setRelation('items', $items);

        $result = $this->builder->build($user, $type);
        $readableContent = $data['format'] === SubscriptionType::FORMAT_URI
            ? base64_decode($result->content, true)
            : false;

        return [
            'user' => $this->userSummary($user),
            'format' => $data['format'],
            'content_type' => $result->contentType,
            'content' => $result->content,
            'readable_content' => $readableContent === false ? $result->content : $readableContent,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function searchUsers(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        return $this->eligibleUsers()
            ->with('activeSubscription')
            ->where(function (Builder $userQuery) use ($search): void {
                $pattern = '%'.$search.'%';
                $userQuery
                    ->where('name', 'like', $pattern)
                    ->orWhere('telegram', 'like', $pattern)
                    ->orWhere('login', 'like', $pattern);

                if (ctype_digit($search)) {
                    $userQuery->orWhereKey((int) $search)
                        ->orWhere('telegram_id', $search);
                }
            })
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'name', 'telegram', 'telegram_id', 'is_admin', 'is_health_check_user', 'balance'])
            ->map(fn (User $user): array => $this->userSummary($user))
            ->values()
            ->all();
    }

    private function resolveUser(string $audience, mixed $userId): User
    {
        $users = $this->eligibleUsers()->with('activeSubscription');

        $user = match ($audience) {
            'active' => (clone $users)->whereHas('activeSubscription')->where('is_admin', false)->orderBy('id')->first(),
            'admin' => (clone $users)->where('is_admin', true)->orderBy('id')->first(),
            'inactive' => (clone $users)
                ->where(function (Builder $query): void {
                    $query->whereDoesntHave('activeSubscription')->orWhere('balance', '<', 0);
                })
                ->where('is_admin', false)
                ->orderBy('id')
                ->first(),
            'user' => (clone $users)->whereKey((int) $userId)->first(),
            default => null,
        };

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'audience' => 'Нет подходящего пользователя для этого варианта. Выберите конкретного пользователя или попробуйте другой вариант.',
            ]);
        }

        return $user;
    }

    private function eligibleUsers(): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->whereNull('is_health_check_user')->orWhere('is_health_check_user', false);
            });
    }

    /** @return array<string, int|float|string|bool|null> */
    private function userSummary(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'telegram' => $user->telegram !== null ? (string) $user->telegram : null,
            'telegram_id' => $user->telegram_id !== null ? (string) $user->telegram_id : null,
            'is_admin' => (bool) $user->is_admin,
            'has_active_subscription' => $user->hasActiveSubscription(),
            'has_active_access' => $user->hasActiveAccess(),
            'has_debt' => $user->hasDebt(),
            'debt_amount' => $user->getDebtAmount(),
        ];
    }
}
