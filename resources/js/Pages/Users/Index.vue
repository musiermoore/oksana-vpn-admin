<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filters: Object,
    users: Array,
});

const activeUsersCount = computed(() => props.users.filter((user) => user.balance >= 0).length);
const debtorsCount = computed(() => props.users.filter((user) => user.balance < 0).length);

const destroyUser = (user) => {
    if (confirm(`Удалить участника ${user.full_name}?`)) {
        router.delete(user.links.destroy);
    }
};
</script>

<template>
    <Head title="Участники" />

    <AppPageHeader
        title="Участники"
        description="Единая зона для работы с профилями, балансами, задолженностью и ручными действиями по пользователям."
        :stats="[
            { label: 'Всего в выдаче', value: users.length },
            { label: 'Без долга', value: activeUsersCount },
            { label: 'С задолженностью', value: debtorsCount },
        ]"
    >
        <template #actions>
            <AppButton href="/users/create">Добавить участника</AppButton>
        </template>
    </AppPageHeader>

    <section class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Сегменты пользователей</h2>
                <p>Фильтрация списка по текущему состоянию. Это быстрый уровень навигации перед самой таблицей.</p>
            </div>
        </div>

        <div class="tabs">
            <Link class="chip" :class="{ 'is-active': filters.all }" href="/users?all=1">Все</Link>
            <Link class="chip" :class="{ 'is-active': !filters.all && !filters.inactive }" href="/users">Активные</Link>
            <Link class="chip" :class="{ 'is-active': filters.inactive }" href="/users?inactive=1">Неактивные</Link>
        </div>
    </section>

    <section class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Реестр участников</h2>
                <p>Основной список для перехода в профиль пользователя, ручной корректировки и контроля баланса.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Telegram</th>
                        <th>Имя</th>
                        <th>Описание</th>
                        <th>Баланс</th>
                        <th>Долг</th>
                        <th>Устройства</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id">
                        <td>{{ user.telegram }}</td>
                        <td>{{ user.name }}</td>
                        <td>{{ user.description || '—' }}</td>
                        <td>{{ Math.max(0, user.balance) }}</td>
                        <td>{{ Math.max(0, -user.balance) }}</td>
                        <td>{{ user.max_devices || '∞' }}</td>
                        <td>
                            <div class="actions">
                                <AppButton variant="secondary" :href="user.links.edit">Открыть</AppButton>
                                <AppButton variant="danger" type="button" @click="destroyUser(user)">Удалить</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
