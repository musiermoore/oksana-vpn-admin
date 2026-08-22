<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    method: String,
    proxy: Object,
    server_options: Array,
});

const form = useForm({
    name: props.proxy?.name ?? '',
    host: props.proxy?.host ?? '',
    port: props.proxy?.port ?? 443,
    server_id: props.proxy?.server_id ?? '',
    inbound_id: props.proxy?.inbound_id ?? '',
    is_https: props.proxy?.is_https ?? true,
    is_ready: props.proxy?.is_ready ?? false,
    hide_main_node_name: props.proxy?.hide_main_node_name ?? false,
    description: props.proxy?.description ?? '',
});

const serverOptions = [
    { label: 'Выберите сервер', value: '', disabled: true },
    ...props.server_options,
];

const submit = () => props.method === 'patch' ? form.patch(props.submit_url) : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Имя</span><AppInput v-model="form.name" required /></label>
            <label class="field"><span>Host</span><AppInput v-model="form.host" required /></label>
            <label class="field"><span>Port</span><AppInput v-model="form.port" type="number" min="1" max="65535" required /></label>
            <label class="field"><span>Сервер</span>
                <AppSelect v-model="form.server_id" :options="serverOptions" required />
            </label>
            <label class="field"><span>Inbound ID</span><AppInput v-model="form.inbound_id" type="number" min="1" placeholder="Пусто = для всех inbound" /></label>
            <label class="field"><span>HTTPS</span><AppCheckbox v-model="form.is_https" /></label>
            <label class="field"><span>Ready</span><AppCheckbox v-model="form.is_ready" /></label>
            <label class="field"><span>Скрыть основную</span><AppCheckbox v-model="form.hide_main_node_name" /></label>
            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description"  />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/proxies">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
