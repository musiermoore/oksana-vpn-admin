<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
defineProps({ promo_codes: Object });

const archive = (promo) => {
    if (confirm(`Удалить промокод ${promo.code}?`)) {
        router.delete(promo.links.destroy);
    }
};
</script>

<template>
    <Head title="Промокоды" />
    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Промокоды</h1></div>
            <AppButton href="/promo-codes/create">Создать промокод</AppButton>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Код</th><th>Бонус</th><th>Кому доступен</th><th>Частота</th>
                    <th>Период</th><th>Статус</th><th>Использований</th><th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="promo in promo_codes.data" :key="promo.id">
                    <td><strong>{{ promo.code }}</strong></td>
                    <td>{{ promo.benefit_label }} · {{ promo.value }}</td>
                    <td>{{ promo.new_users_only ? 'Только новым' : 'Всем' }}</td>
                    <td>{{ promo.once_per_user ? 'Один раз на пользователя' : 'Каждая покупка' }}</td>
                    <td>{{ promo.starts_on ?? 'Без начала' }} · {{ promo.ends_on ?? 'Без окончания' }}</td>
                    <td>{{ promo.is_archived ? 'Удалён' : promo.is_active ? 'Включён' : 'Отключён' }}</td>
                    <td>{{ promo.redeemed_count }}</td>
                    <td>
                        <div class="actions">
                            <AppButton variant="secondary" :href="promo.links.show">История</AppButton>
                            <AppButton v-if="!promo.is_archived" variant="secondary" :href="promo.links.edit">Изменить</AppButton>
                            <AppButton v-if="!promo.is_archived" variant="danger" @click="archive(promo)">Удалить</AppButton>
                        </div>
                    </td>
                </tr>
                <tr v-if="promo_codes.data.length === 0"><td colspan="8">Промокодов пока нет.</td></tr>
            </tbody>
        </table>
    </section>
    <div class="actions">
        <AppButton v-if="promo_codes.links.prev" :href="promo_codes.links.prev" variant="secondary">Назад</AppButton>
        <AppButton v-if="promo_codes.links.next" :href="promo_codes.links.next" variant="secondary">Далее</AppButton>
    </div>
</template>
