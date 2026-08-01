<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    config: Object,
    selected_user_id: Number,
    users: Array,
    available_inbounds: Array,
});

const isUserAllowedForInbound = (item, userId) => {
    const user = props.users.find((candidate) => Number(candidate.id) === Number(userId));

    if (!user) {
        return false;
    }

    return item.is_public || user.is_admin;
};

const filteredInbounds = () => props.available_inbounds.filter((item) => isUserAllowedForInbound(item, form.user_id));

const selectedInbound = props.available_inbounds[0] ?? null;

const form = useForm({
    protocol: props.config?.protocol ?? selectedInbound?.protocol ?? '',
    user_id: props.config?.user?.id ?? props.selected_user_id ?? props.users[0]?.id ?? '',
    server_id: props.config?.server?.id ?? selectedInbound?.server_id ?? '',
    inbound_id: selectedInbound?.inbound_id ?? '',
});

const selectedInboundDetails = () => props.available_inbounds.find(
    (item) => item.protocol === form.protocol
        && item.server_id === Number(form.server_id)
        && item.inbound_id === Number(form.inbound_id)
);
const userOptions = computed(() => props.users.map((user) => ({
    label: user.full_name,
    value: user.id,
})));
const inboundOptions = computed(() => filteredInbounds().map((item) => ({
    label: item.label,
    value: `${item.protocol}:${item.server_id}:${item.inbound_id}`,
})));
const selectedInboundKey = computed({
    get: () => `${form.protocol}:${form.server_id}:${form.inbound_id}`,
    set: (value) => updateInboundSelection(value),
});

const updateInboundSelection = (value) => {
    const [protocol, serverId, inboundId] = value.split(':');
    form.protocol = protocol;
    form.server_id = Number(serverId);
    form.inbound_id = Number(inboundId);
};

const syncInboundSelection = () => {
    const available = filteredInbounds();
    const selected = selectedInboundDetails();

    if (selected && isUserAllowedForInbound(selected, form.user_id)) {
        return;
    }

    const nextInbound = available[0] ?? null;

    form.protocol = nextInbound?.protocol ?? '';
    form.server_id = nextInbound?.server_id ?? '';
    form.inbound_id = nextInbound?.inbound_id ?? '';
};

watch(() => form.user_id, syncInboundSelection, { immediate: true });

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
                <AppSelect v-model="form.user_id" :options="userOptions" />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Вход</span>
                <AppSelect v-model="selectedInboundKey" :options="inboundOptions" />
            </label>

            <label v-if="mode === 'create' && filteredInbounds().length === 0" class="field" style="grid-column: 1 / -1;">
                <span>Доступные входы</span>
                <AppInput value="Нет доступных Xray-входов" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Протокол</span>
                <AppInput :value="selectedInboundDetails()?.protocol?.toUpperCase() ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Тип</span>
                <AppInput :value="selectedInboundDetails()?.type?.toUpperCase() ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Безопасность</span>
                <AppInput :value="selectedInboundDetails()?.security?.toUpperCase() ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Сервер</span>
                <AppInput :value="selectedInboundDetails()?.server_name ?? ''" readonly />
            </label>

            <label v-if="mode === 'create' && selectedInboundDetails()?.method" class="field">
                <span>Метод</span>
                <AppInput :value="selectedInboundDetails()?.method ?? ''" readonly />
            </label>

            <template v-else>
                <label class="field">
                    <span>Протокол</span>
                    <AppInput :value="config?.protocol_label ?? ''" readonly />
                </label>

                <label class="field">
                    <span>Сервер</span>
                    <AppInput :value="config?.server ? `${config.server.name} (${config.server.ip})` : ''" readonly />
                </label>

                <label class="field" style="grid-column: 1 / -1;">
                    <span>Ссылка</span>
                    <Textarea :value="config?.link ?? ''" readonly fluid auto-resize />
                </label>
            </template>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing || (mode === 'create' && filteredInbounds().length === 0)">Сохранить</AppButton>
                <AppButton variant="secondary" href="/xray-configs">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
