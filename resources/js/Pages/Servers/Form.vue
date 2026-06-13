<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    method: String,
    server: Object,
    inbound_options: Array,
});

const form = useForm({
    name: props.server?.name ?? '',
    code: props.server?.code ?? '',
    ip: props.server?.ip ?? '',
    type: props.server?.type ?? 'wireguard-old',
    is_https: props.server?.is_https ?? false,
    link_host: props.server?.link_host ?? '',
    panel_link: props.server?.panel_link ?? '',
    panel_username: props.server?.panel_username ?? '',
    panel_password: props.server?.panel_password ?? '',
    panel_api_version: props.server?.panel_api_version ?? 'v2.9.*',
    app_path: props.server?.app_path ?? '',
    ssh_private_key: '',
    ssh_public_key: props.server?.ssh_public_key ?? '',
    is_ready: props.server?.is_ready ?? false,
    hide_configs_for_non_admins: props.server?.hide_configs_for_non_admins ?? false,
    allowed_inbound_ids: props.server?.allowed_inbound_ids ?? [],
});

const submit = () => props.method === 'patch' ? form.patch(props.submit_url) : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование сервера' : 'Создание сервера'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование сервера' : 'Создание сервера' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Имя</span><input v-model="form.name" required></label>
            <label class="field"><span>Сокращение</span><input v-model="form.code" required></label>
            <label class="field"><span>IP</span><input v-model="form.ip" required></label>
            <label class="field"><span>Тип</span>
                <select v-model="form.type">
                    <option value="wireguard-old">WireGuard (legacy)</option>
                    <option value="wireguard">WireGuard Agent API</option>
                    <option value="vless">VLESS</option>
                </select>
            </label>
            <label class="field"><span>Is HTTPS</span><input v-model="form.is_https" type="checkbox"></label>
            <label class="field"><span>Link Host</span><input v-model="form.link_host"></label>
            <label class="field"><span>Panel Link</span><input v-model="form.panel_link"></label>
            <label class="field"><span>Panel Username</span><input v-model="form.panel_username"></label>
            <label class="field"><span>Panel API Version</span>
                <select v-model="form.panel_api_version">
                    <option value="v2.9.*">v2.9.*</option>
                    <option value="v3.2.8">v3.2.8</option>
                </select>
            </label>
            <label class="field" style="grid-column: 1 / -1;"><span>Panel Password</span><input v-model="form.panel_password" type="password"></label>
            <label class="field" style="grid-column: 1 / -1;"><span>Путь до приложения</span><input v-model="form.app_path" required></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Private Key</span><textarea v-model="form.ssh_private_key" /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Public Key</span><textarea v-model="form.ssh_public_key" /></label>
            <label class="field"><span>Is Ready</span><input v-model="form.is_ready" type="checkbox"></label>
            <label class="field"><span>Hide configs for non-admins</span><input v-model="form.hide_configs_for_non_admins" type="checkbox"></label>
            <label class="field" style="grid-column: 1 / -1;">
                <span>Allowed Inbound IDs</span>
                <select v-model="form.allowed_inbound_ids" multiple :disabled="form.type !== 'vless' || inbound_options.length === 0">
                    <option v-for="option in inbound_options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <small v-if="form.type === 'vless' && inbound_options.length === 0">
                    Inbounds appear here after the server has panel access configured and saved.
                </small>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/servers">Назад</Link>
            </div>
        </form>
    </section>
</template>
