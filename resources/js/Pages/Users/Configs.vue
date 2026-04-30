<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    token: Object,
    is_password_correct: Boolean,
    password: String,
});

const form = useForm({
    password: props.password ?? '',
});

const submit = () => form.get(props.token.links.public_configs);
</script>

<template>
    <Head title="Конфиг" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Конфиг</h1>
                <p>Публичная ссылка больше не показывает общие гостевые страницы, только доступ к конкретному токену.</p>
            </div>
        </div>

        <div class="field">
            <label>Пользователь</label>
            <input :value="token.user?.telegram || token.user?.name" readonly>
        </div>

        <div v-if="is_password_correct" class="stack">
            <div class="flash flash--success" v-if="token.expires_at">
                Ссылка временная и доступна до {{ token.expires_at }}.
            </div>

            <div v-if="token.download_items.length" class="list">
                <div v-for="item in token.download_items" :key="item.id" class="item-row">
                    <div>{{ item.name }}</div>
                    <div class="actions">
                        <a class="button button--secondary" :href="item.qr_code_url" target="_blank">QR-Code</a>
                        <a class="button" :href="item.download_url">Скачать</a>
                    </div>
                </div>
            </div>
            <div v-else class="empty-state">Для этого токена нет доступных конфигов.</div>
        </div>

        <form v-else class="grid" @submit.prevent="submit">
            <label class="field">
                <span>Пароль</span>
                <input v-model="form.password" type="password" name="password">
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Открыть</button>
            </div>
        </form>
    </section>
</template>
