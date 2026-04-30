<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    user_tokens: Array,
});

const destroyToken = (token) => confirm('Удалить токен?') && router.delete(token.links.destroy);
</script>

<template>
    <Head title="Токены" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Токены</h1></div>
            <div class="actions">
                <Link class="button" href="/user-tokens/create">Создать токен</Link>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Пользователь</th>
                    <th>Истекает</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="token in user_tokens" :key="token.id">
                    <td>{{ token.user?.name }}</td>
                    <td>{{ token.expires_at || 'Не ограничен' }}</td>
                    <td>
                        <div class="actions">
                            <Link class="button button--secondary" :href="token.links.show">Открыть</Link>
                            <button class="button button--danger" type="button" @click="destroyToken(token)">Удалить</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
