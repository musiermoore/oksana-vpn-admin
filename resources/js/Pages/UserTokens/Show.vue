<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    user_token: Object,
});
</script>

<template>
    <Head title="Токен" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Токен</h1>
                <p>Гостевые страницы скрыты; доступ остался только по этой одноразовой ссылке и паролю.</p>
            </div>
        </div>

        <div class="grid grid--two">
            <label class="field">
                <span>Пользователь</span>
                <input :value="`${user_token.user?.telegram} (${user_token.user?.name})`" readonly>
            </label>

            <label class="field">
                <span>Token</span>
                <input :value="user_token.token" readonly>
            </label>

            <label class="field">
                <span>Password</span>
                <input :value="user_token.password" readonly>
            </label>

            <label class="field">
                <span>Link</span>
                <input :value="user_token.links.public_configs" readonly>
            </label>
        </div>

        <div v-if="user_token.download_items.length" class="list">
            <div v-for="item in user_token.download_items" :key="item.id" class="item-row">
                <div>{{ item.name }}</div>
                <div class="actions">
                    <a class="button button--secondary" :href="item.qr_code_url" target="_blank">QR-Code</a>
                    <a class="button" :href="item.download_url">Скачать</a>
                </div>
            </div>
        </div>

        <div class="actions">
            <Link class="button button--secondary" href="/user-tokens">Назад</Link>
        </div>
    </section>
</template>
