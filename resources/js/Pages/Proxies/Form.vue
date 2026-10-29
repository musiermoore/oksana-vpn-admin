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
    hosts: props.proxy?.hosts?.length
        ? props.proxy.hosts.map((host) => ({
            id: props.mode === 'edit' ? host.id : null,
            host: host.host,
            port: host.port,
            sort: host.sort,
            is_active: host.is_active,
        }))
        : [{ id: null, host: props.proxy?.host ?? '', port: props.proxy?.port ?? 443, sort: 0, is_active: true }],
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
const addHost = () => form.hosts.push({
    id: null,
    host: '',
    port: 443,
    sort: Math.max(-1, ...form.hosts.map((host) => Number(host.sort) || 0)) + 1,
    is_active: true,
});
const removeHost = (index) => {
    if (form.hosts.length > 1) form.hosts.splice(index, 1);
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Имя</span><AppInput v-model="form.name" required /></label>
            <div class="field" style="grid-column: 1 / -1;">
                <div class="page-header">
                    <div>
                        <span>Цепочка proxy hosts</span>
                        <p class="hint">Первый активный host используется для подключения пользователей. Остальные сохранены для настройки каскада.</p>
                    </div>
                    <AppButton variant="secondary" type="button" @click="addHost">Добавить host</AppButton>
                </div>
                <div v-for="(host, index) in form.hosts" :key="index" class="host-row">
                    <label class="field"><span>{{ index === 0 ? 'Host' : `Cascade host ${index}` }}</span><AppInput v-model="host.host" required /></label>
                    <label class="field"><span>Port</span><AppInput v-model="host.port" type="number" min="1" max="65535" required /></label>
                    <label class="field"><span>Порядок</span><AppInput v-model="host.sort" type="number" min="0" required /></label>
                    <label class="field"><span>Active</span><AppCheckbox v-model="host.is_active" /></label>
                    <AppButton variant="danger" type="button" :disabled="form.hosts.length <= 1" @click="removeHost(index)">Удалить</AppButton>
                </div>
            </div>
            <label class="field"><span>Сервер</span>
                <AppSelect v-model="form.server_id" :options="serverOptions" required />
            </label>
            <label class="field" style="grid-column: 1 / -1;"><span>Inbound</span>
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

<style scoped>
.host-row {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(100px, 1fr) minmax(80px, 0.5fr) auto auto;
    gap: 12px;
    align-items: end;
    margin-top: 12px;
}
</style>
