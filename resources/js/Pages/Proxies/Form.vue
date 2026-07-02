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
    inbound_id: props.proxy?.inbound_id ?? '',
    is_https: props.proxy?.is_https ?? true,
    is_ready: props.proxy?.is_ready ?? false,
    description: props.proxy?.description ?? '',
    server_ids: props.proxy?.server_ids ?? [],
});

const submit = () => props.method === 'patch' ? form.patch(props.submit_url) : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование прокси' : 'Создание прокси' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Имя</span><input v-model="form.name" required></label>
            <label class="field"><span>Host</span><input v-model="form.host" required></label>
            <label class="field"><span>Port</span><input v-model="form.port" type="number" min="1" max="65535" required></label>
            <label class="field"><span>Inbound ID</span><input v-model="form.inbound_id" type="number" min="1" placeholder="Пусто = для всех inbound"></label>
            <label class="field"><span>HTTPS</span><input v-model="form.is_https" type="checkbox"></label>
            <label class="field"><span>Ready</span><input v-model="form.is_ready" type="checkbox"></label>
            <label class="field" style="grid-column: 1 / -1;">
                <span>Серверы</span>
                <select v-model="form.server_ids" multiple>
                    <option v-for="option in server_options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </label>
            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <textarea v-model="form.description" />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/proxies">Назад</Link>
            </div>
        </form>
    </section>
</template>
