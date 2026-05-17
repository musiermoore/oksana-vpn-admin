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
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </label>

            <div class="stack">
                <div v-for="(config, index) in form.configs" :key="index" class="panel grid">
                    <label class="field">
                        <span>Сервер</span>
                        <select v-model="config.server_id">
                            <option v-for="server in servers" :key="server.id" :value="server.id">{{ server.name }}</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Описание (необязательно)</span>
                        <textarea v-model="config.description" />
                    </label>

                    <div class="actions" style="grid-column: 1 / -1;">
                        <button v-if="form.configs.length > 1" class="button button--danger" type="button" @click="removeRow(index)">Убрать</button>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button class="button button--secondary" type="button" @click="addRow">Добавить ещё</button>
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--ghost" href="/configs">Назад</Link>
            </div>
        </form>
    </section>
</template>
