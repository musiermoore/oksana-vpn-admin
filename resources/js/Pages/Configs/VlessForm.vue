<script setup>
import { computed } from 'vue';
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
    user_id: props.config?.user?.id ?? props.users[0]?.id ?? '',
    server_id: props.config?.server?.id ?? selectedInbound?.server_id ?? '',
    inbound_id: selectedInbound?.inbound_id ?? '',
});

const userOptions = computed(() => props.users.map((user) => ({
    label: user.full_name,
    value: user.id,
})));
const inboundOptions = computed(() => props.available_inbounds.map((item) => ({
    label: item.label,
    value: `${item.server_id}:${item.inbound_id}`,
})));
const selectedInboundDetails = computed(() => props.available_inbounds.find(
    (item) => item.server_id === Number(form.server_id) && item.inbound_id === Number(form.inbound_id),
) ?? null);
const selectedInboundKey = computed({
    get: () => `${form.server_id}:${form.inbound_id}`,
    set: (value) => {
        const [serverId, inboundId] = value.split(':');
        form.server_id = Number(serverId);
        form.inbound_id = Number(inboundId);
    },
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование VLESS-конфига' : 'Создать VLESS-конфиг'" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>{{ mode === 'edit' ? 'Редактирование VLESS-конфига' : 'Создать VLESS-конфиг' }}</h1></div>
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

            <label v-if="mode === 'create' && available_inbounds.length === 0" class="field" style="grid-column: 1 / -1;">
                <span>Доступные входы</span>
                <AppInput value="Нет доступных VLESS-входов" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Тип</span>
                <AppInput :value="selectedInboundDetails?.type?.toUpperCase() ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Безопасность</span>
                <AppInput :value="selectedInboundDetails?.security?.toUpperCase() ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Сервер</span>
                <AppInput :value="selectedInboundDetails?.server_name ?? ''" readonly />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Код сервера</span>
                <AppInput :value="selectedInboundDetails?.server_code ?? ''" readonly />
            </label>

            <template v-else>
                <label class="field">
                    <span>Сервер</span>
                    <AppInput :value="`${config.server.name} (${config.server.ip})`" readonly />
                </label>

                <label class="field" style="grid-column: 1 / -1;">
                    <span>Ссылка</span>
                    <Textarea :value="config.link" readonly fluid auto-resize />
                </label>
            </template>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing || (mode === 'create' && available_inbounds.length === 0)">Сохранить</AppButton>
                <AppButton variant="secondary" href="/vless-configs">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
