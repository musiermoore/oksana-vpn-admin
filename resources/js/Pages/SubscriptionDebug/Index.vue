<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    created: {
        type: Object,
        default: null,
    },
    current: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    type: props.current?.type ?? 'json',
    body: props.current?.body ?? '',
});

const typeOptions = [
    { value: 'json', label: 'JSON' },
    { value: 'url', label: 'URI list / plain text' },
];

const bodyPlaceholder = computed(() => form.type === 'json'
    ? '{\n  "dns": {},\n  "outbounds": {}\n}'
    : 'vless://...\nvmess://...');

const submit = () => form.post('/subscription-debug', {
    preserveScroll: true,
});

const copyUrl = async () => {
    if (props.created?.url) {
        await navigator.clipboard.writeText(props.created.url);
    }
};
</script>

<template>
    <Head title="Subscription Debug" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Subscription Debug</h1>
                <p>Создайте временную публичную ссылку для произвольного JSON-профиля или списка URI.</p>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span>Тип подписки</span>
                <AppSelect v-model="form.type" :options="typeOptions" />
                <small class="muted">Тип влияет на Content-Type публичного ответа.</small>
                <small v-if="form.errors.type" class="form-error">{{ form.errors.type }}</small>
            </label>

            <label class="field">
                <span>Body</span>
                <AppTextarea v-model="form.body" :placeholder="bodyPlaceholder" :auto-resize="false" rows="22" spellcheck="false" />
                <small class="muted">Текст не сохраняется в базе данных или Redis.</small>
                <small v-if="form.errors.body" class="form-error">{{ form.errors.body }}</small>
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing || !form.body.trim()">Создать ссылку</AppButton>
            </div>
        </form>
    </section>

    <section v-if="created" class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Ссылка готова</h2>
                <p>Она будет доступна один час.</p>
            </div>
        </div>

        <div class="field">
            <span>UUID</span>
            <code class="code-block">{{ created.uuid }}</code>
        </div>

        <div class="field">
            <span>Public URL</span>
            <div class="actions">
                <InputText :model-value="created.url" readonly fluid />
                <AppButton type="button" variant="secondary" @click="copyUrl">Копировать</AppButton>
                <AppButton :href="created.url" target="_blank" variant="secondary">Открыть</AppButton>
            </div>
        </div>
    </section>
</template>
