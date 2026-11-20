<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
defineProps({ promo_code: Object, redemptions: Object });
</script>

<template>
    <Head :title="`История промокода ${promo_code.code}`" />
    <section class="page-card stack">
        <div class="page-header">
            <div><h1>История промокода {{ promo_code.code }}</h1></div>
            <AppButton variant="secondary" href="/promo-codes">Все промокоды</AppButton>
        </div>
    </section>
    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Пользователь</th><th>Код</th><th>Бонус</th><th>Статус</th>
                    <th>Начато</th><th>Использован</th><th>Транзакции</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="use in redemptions.data" :key="use.id">
                    <td>
                        <AppButton v-if="use.user_url" variant="ghost" :href="use.user_url">{{ use.user_name || use.user_id }}</AppButton>
                        <span v-else>{{ use.user_name || use.user_id }}</span>
                    </td>
                    <td>{{ use.code }}</td>
                    <td>
                        {{ use.benefit_label }} · {{ use.value }}
                        <small v-if="use.discount_amount > 0">({{ use.discount_amount }} ₽)</small>
                    </td>
                    <td>{{ use.status }}</td>
                    <td>{{ use.created_at }}</td>
                    <td>{{ use.redeemed_at ?? '—' }}</td>
                    <td>
                        <div class="actions">
                            <AppButton v-if="use.payment_transaction_id" variant="ghost" :href="`/transactions/${use.payment_transaction_id}/edit`">Оплата</AppButton>
                            <AppButton v-if="use.subscription_transaction_id" variant="ghost" :href="`/transactions/${use.subscription_transaction_id}/edit`">Подписка</AppButton>
                        </div>
                    </td>
                </tr>
                <tr v-if="redemptions.data.length === 0"><td colspan="7">История пока пуста.</td></tr>
            </tbody>
        </table>
    </section>
    <div class="actions">
        <AppButton v-if="redemptions.links.prev" :href="redemptions.links.prev" variant="secondary">Назад</AppButton>
        <AppButton v-if="redemptions.links.next" :href="redemptions.links.next" variant="secondary">Далее</AppButton>
    </div>
</template>
