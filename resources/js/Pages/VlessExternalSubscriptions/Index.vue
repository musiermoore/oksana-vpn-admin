<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    subscriptions: Array,
});

const destroySubscription = (subscription) => {
    if (confirm(`Удалить внешнюю подписку ${subscription.name}?`)) {
        router.delete(`/vless-external-subscriptions/${subscription.id}`);
    }
};

const syncSubscription = (subscription) => {
    router.post(subscription.links.sync);
};
</script>

<template>
    <Head title="VLESS Белые списки" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>VLESS Белые списки</h1>
                <p>Внешние подписки и прямые конфиги для белых списков.</p>
            </div>

            <div class="actions">
                <Link class="button" href="/vless-external-subscriptions/create">Создать</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Тип</th>
                    <th>Активна</th>
                    <th>Ready</th>
                    <th>Конфигов</th>
                    <th>Последняя синхронизация</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="subscription in subscriptions" :key="subscription.id">
                    <td>{{ subscription.id }}</td>
                    <td>
                        <strong>{{ subscription.name }}</strong>
                        <div v-if="subscription.description">{{ subscription.description }}</div>
                        <div v-if="subscription.last_sync_error" class="field-error">{{ subscription.last_sync_error }}</div>
                    </td>
                    <td>{{ subscription.type === 'subscription' ? 'Подписка' : 'Прямая ссылка' }}</td>
                    <td>{{ subscription.is_active ? 'Да' : 'Нет' }}</td>
                    <td>{{ subscription.is_ready ? 'Да' : 'Только админ' }}</td>
                    <td>{{ subscription.configs_count ?? 0 }}</td>
                    <td>{{ subscription.last_synced_at || '—' }}</td>
                    <td>
                        <div class="actions">
                            <button class="button" type="button" @click="syncSubscription(subscription)">Синхронизировать</button>
                            <Link class="button button--secondary" :href="subscription.links.edit">Изменить</Link>
                            <button class="button button--danger" type="button" @click="destroySubscription(subscription)">Удалить</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
