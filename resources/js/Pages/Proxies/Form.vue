<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    method: String,
    proxy: Object,
    server_options: Array,
    inbound_options_by_server: Object,
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

const selectedServerId = computed(() => {
    if (form.server_id === '' || form.server_id === null || form.server_id === undefined) {
        return null;
    }

    return Number(form.server_id);
});

const inboundOptions = computed(() => {
    if (selectedServerId.value === null) {
        return [{ label: 'Сначала выберите сервер', value: '', disabled: true }];
    }

    return [
        { label: 'Все inbound', value: '' },
        ...(props.inbound_options_by_server?.[selectedServerId.value] ?? []),
    ];
});

watch(selectedServerId, (serverId) => {
    if (serverId === null) {
        form.inbound_id = '';

        return;
    }

    const allowedInboundValues = new Set(
        (props.inbound_options_by_server?.[serverId] ?? []).map((option) => String(option.value))
    );

    if (form.inbound_id !== '' && ! allowedInboundValues.has(String(form.inbound_id))) {
        form.inbound_id = '';
    }
});

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
            <label class="field"><span>Inbound</span>
                <AppSelect v-model="form.inbound_id" :options="inboundOptions" :disabled="selectedServerId === null" />
            </label>
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
