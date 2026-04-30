<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    filters: Object,
    users: Array,
});

const destroyUser = (user) => {
    if (confirm(`Удалить участника ${user.full_name}?`)) {
        router.delete(user.links.destroy);
    }
};
</script>

<template>
    <Head title="Участники" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Участники ({{ users.length }})</h1>
                <p>Управление участниками, балансами и их активностью.</p>
            </div>

            <div class="actions">
                <Link class="button" href="/users/create">Добавить</Link>
            </div>
        </div>

        <div class="tabs">
            <Link class="chip" :class="{ 'is-active': filters.all }" href="/users?all=1">Все</Link>
            <Link class="chip" :class="{ 'is-active': !filters.all && !filters.inactive }" href="/users">Активные</Link>
            <Link class="chip" :class="{ 'is-active': filters.inactive }" href="/users?inactive=1">Неактивные</Link>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Telegram</th>
                    <th>Имя</th>
                    <th>Описание</th>
                    <th>Баланс</th>
                    <th>Долг</th>
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
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="user.links.edit">Изменить</Link>
                            <button class="button button--danger" type="button" @click="destroyUser(user)">Удалить</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
