<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    config: Object,
    users: Array,
    available_inbounds: Array,
});

const selectedInbound = props.available_inbounds[0] ?? null;

const form = useForm({
    protocol: props.config?.protocol ?? selectedInbound?.protocol ?? '',
    user_id: props.config?.user?.id ?? props.users[0]?.id ?? '',
    server_id: props.config?.server?.id ?? selectedInbound?.server_id ?? '',
    inbound_id: selectedInbound?.inbound_id ?? '',
});

const selectedInboundDetails = () => props.available_inbounds.find(
    (item) => item.protocol === form.protocol
        && item.server_id === Number(form.server_id)
        && item.inbound_id === Number(form.inbound_id)
);

const updateInboundSelection = (value) => {
    const [protocol, serverId, inboundId] = value.split(':');
    form.protocol = protocol;
    form.server_id = Number(serverId);
    form.inbound_id = Number(inboundId);
};

const submit = () => {
    if (props.mode === 'edit') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование Xray-конфига' : 'Создать Xray-конфиг'" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>{{ mode === 'edit' ? 'Редактирование Xray-конфига' : 'Создать Xray-конфиг' }}</h1></div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Участник</span>
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Вход</span>
                <select
                    :value="`${form.protocol}:${form.server_id}:${form.inbound_id}`"
                    @change="({ target }) => updateInboundSelection(target.value)"
                >
                    <option
                        v-for="item in available_inbounds"
                        :key="`${item.protocol}:${item.server_id}:${item.inbound_id}`"
                        :value="`${item.protocol}:${item.server_id}:${item.inbound_id}`"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </label>

            <label v-if="mode === 'create' && available_inbounds.length === 0" class="field" style="grid-column: 1 / -1;">
                <span>Доступные входы</span>
                <input value="Нет доступных Xray-входов" readonly>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Протокол</span>
                <input :value="selectedInboundDetails()?.protocol?.toUpperCase() ?? ''" readonly>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Тип</span>
                <input :value="selectedInboundDetails()?.type?.toUpperCase() ?? ''" readonly>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Безопасность</span>
                <input :value="selectedInboundDetails()?.security?.toUpperCase() ?? ''" readonly>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Сервер</span>
                <input :value="selectedInboundDetails()?.server_name ?? ''" readonly>
            </label>

            <label v-if="mode === 'create' && selectedInboundDetails()?.method" class="field">
                <span>Метод</span>
                <input :value="selectedInboundDetails()?.method ?? ''" readonly>
            </label>

            <template v-else>
                <label class="field">
                    <span>Протокол</span>
                    <input :value="config?.protocol_label ?? ''" readonly>
                </label>

                <label class="field">
                    <span>Сервер</span>
                    <input :value="config?.server ? `${config.server.name} (${config.server.ip})` : ''" readonly>
                </label>

                <label class="field" style="grid-column: 1 / -1;">
                    <span>Ссылка</span>
                    <textarea :value="config?.link ?? ''" readonly />
                </label>
            </template>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing || (mode === 'create' && available_inbounds.length === 0)">Сохранить</button>
                <Link class="button button--secondary" href="/xray-configs">Назад</Link>
            </div>
        </form>
    </section>
</template>
