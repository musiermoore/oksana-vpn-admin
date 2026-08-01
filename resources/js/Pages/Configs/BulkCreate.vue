<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    servers: Array,
});

const form = useForm({
    server_id: props.servers[0]?.id ?? '',
});

const serverOptions = props.servers.map((server) => ({
    label: `${server.name} (${server.type}, ${server.ip})`,
    value: server.id,
}));
</script>

<template>
    <Head title="Создать конфиги" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Массовое создание конфигов</h1>
                <p>Конфиг создаётся только если у пользователя ещё нет конфига на выбранном сервере.</p>
            </div>
        </div>

        <form class="grid" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Сервер</span>
                <AppSelect v-model="form.server_id" :options="serverOptions" />
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/configs">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
