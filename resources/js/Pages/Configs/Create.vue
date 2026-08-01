<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    users: Array,
    servers: Array,
});

const form = useForm({
    user_id: props.users[0]?.id ?? '',
    configs: [{ server_id: props.servers[0]?.id ?? '', description: '' }],
});

const addRow = () => form.configs.push({ server_id: props.servers[0]?.id ?? '', description: '' });
const removeRow = (index) => form.configs.splice(index, 1);
const submit = () => form.post(props.submit_url);
const userOptions = props.users.map((user) => ({
    label: user.full_name,
    value: user.id,
}));
const serverOptions = props.servers.map((server) => ({
    label: `${server.name} (${server.type})`,
    value: server.id,
}));
</script>

<template>
    <Head title="Создать конфиг" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Создать конфиг</h1></div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span>Участник</span>
                <AppSelect v-model="form.user_id" :options="userOptions" />
            </label>

            <div class="stack">
                <div v-for="(config, index) in form.configs" :key="index" class="panel grid">
                    <label class="field">
                        <span>Сервер</span>
                        <AppSelect v-model="config.server_id" :options="serverOptions" />
                    </label>

                    <label class="field">
                        <span>Описание (необязательно)</span>
                        <AppTextarea v-model="config.description"  />
                    </label>

                    <div class="actions" style="grid-column: 1 / -1;">
                        <AppButton v-if="form.configs.length > 1" variant="danger" type="button" @click="removeRow(index)">Убрать</AppButton>
                    </div>
                </div>
            </div>

            <div class="actions">
                <AppButton variant="secondary" type="button" @click="addRow">Добавить ещё</AppButton>
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="ghost" href="/configs">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
