<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    servers: {
        type: Array,
        default: () => [],
    },
    presets: {
        type: Array,
        default: () => [],
    },
    initial_form: {
        type: Object,
        default: () => ({
            server_id: 0,
            preset: 'online-clients',
            method: 'POST',
            endpoint: '/panel/api/clients/onlines',
            encoding: 'form',
            payload: '{}',
        }),
    },
    result: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    server_id: props.initial_form.server_id,
    preset: props.initial_form.preset,
    method: props.initial_form.method,
    endpoint: props.initial_form.endpoint,
    encoding: props.initial_form.encoding,
    payload: props.initial_form.payload,
});

const presetMap = computed(() => Object.fromEntries(props.presets.map((preset) => [preset.value, preset])));
const currentPreset = computed(() => presetMap.value[form.preset] ?? null);
const selectedServer = computed(() => props.servers.find((server) => server.id === Number(form.server_id)) ?? null);

watch(
    () => form.preset,
    (presetValue) => {
        const preset = presetMap.value[presetValue];

        if (!preset) {
            return;
        }

        form.method = preset.method;
        form.endpoint = preset.endpoint;
        form.encoding = preset.encoding;
        form.payload = preset.payload;
    },
);

const submit = () => {
    form.post('/xui-debug', {
        preserveScroll: true,
    });
};

const stringify = (value) => JSON.stringify(value ?? null, null, 2);
</script>

<template>
    <Head title="3x-ui Debug" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>3x-ui Debug</h1>
                <p>Мини-Postman для панели 3x-ui c вашей авторизацией, cookie и CSRF из приложения.</p>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Запрос</h2>
                <p v-if="selectedServer">
                    {{ selectedServer.label }}<template v-if="selectedServer.panel_link"> · {{ selectedServer.panel_link }}</template>
                </p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <div class="grid grid--two">
                <label class="field">
                    <span>Сервер</span>
                    <select v-model="form.server_id">
                        <option v-for="server in servers" :key="server.id" :value="server.id">
                            {{ server.label }}
                        </option>
                    </select>
                    <small v-if="form.errors.server_id" class="field-error">{{ form.errors.server_id }}</small>
                </label>

                <label class="field">
                    <span>Action</span>
                    <select v-model="form.preset">
                        <option v-for="preset in presets" :key="preset.value" :value="preset.value">
                            {{ preset.label }}
                        </option>
                    </select>
                    <small v-if="form.errors.preset" class="field-error">{{ form.errors.preset }}</small>
                </label>

                <label class="field">
                    <span>HTTP Method</span>
                    <select v-model="form.method">
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                    </select>
                    <small v-if="form.errors.method" class="field-error">{{ form.errors.method }}</small>
                </label>

                <label class="field">
                    <span>Encoding</span>
                    <select v-model="form.encoding">
                        <option value="json">JSON</option>
                        <option value="form">Form</option>
                    </select>
                    <small v-if="form.errors.encoding" class="field-error">{{ form.errors.encoding }}</small>
                </label>
            </div>

            <label class="field">
                <span>Endpoint</span>
                <input v-model="form.endpoint" type="text" placeholder="/panel/api/clients/onlines">
                <small class="muted">Можно указать относительный путь или полный URL этого же panel host.</small>
                <small v-if="currentPreset?.value === 'client-ips'" class="muted">
                    Замените `your_email_here` на email клиента из 3x-ui.
                </small>
                <small v-if="currentPreset?.value === 'client-traffic'" class="muted">
                    Замените `your_email_here` на email клиента из 3x-ui.
                </small>
                <small v-if="form.errors.endpoint" class="field-error">{{ form.errors.endpoint }}</small>
            </label>

            <label class="field">
                <span>Payload JSON</span>
                <textarea v-model="form.payload" rows="10" spellcheck="false"></textarea>
                <small class="muted">Для GET обычно оставляйте `{}`. Для form-режима JSON должен быть объектом.</small>
                <small v-if="form.errors.payload" class="field-error">{{ form.errors.payload }}</small>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing || !servers.length">Выполнить</button>
            </div>
        </form>
    </section>

    <section v-if="result" class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Ответ</h2>
                <p>
                    <strong>{{ result.request?.method }}</strong>
                    {{ result.request?.path }}
                    <template v-if="result.status !== null"> · status {{ result.status }}</template>
                    <template v-if="result.ok !== undefined"> · {{ result.ok ? 'OK' : 'FAIL' }}</template>
                </p>
            </div>
        </div>

        <div class="grid grid--two">
            <article class="page-card stack">
                <h3 class="section-title">Request</h3>
                <pre class="code-block">{{ stringify(result.request) }}</pre>
            </article>

            <article class="page-card stack">
                <h3 class="section-title">Server</h3>
                <pre class="code-block">{{ stringify(result.server) }}</pre>
            </article>
        </div>

        <article v-if="result.exception" class="page-card stack">
            <h3 class="section-title">Exception</h3>
            <pre class="code-block">{{ stringify(result.exception) }}</pre>
        </article>

        <article class="page-card stack">
            <h3 class="section-title">Headers</h3>
            <pre class="code-block">{{ stringify(result.headers) }}</pre>
        </article>

        <article v-if="result.json" class="page-card stack">
            <h3 class="section-title">JSON</h3>
            <pre class="code-block">{{ stringify(result.json) }}</pre>
        </article>

        <article class="page-card stack">
            <h3 class="section-title">Raw Body</h3>
            <pre class="code-block">{{ result.body || '—' }}</pre>
        </article>
    </section>
</template>
