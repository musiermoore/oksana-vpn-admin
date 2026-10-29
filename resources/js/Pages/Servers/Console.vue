<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import '@xterm/xterm/css/xterm.css';
import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from '../../Shared/Admin/AppButton.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ server: Object, templates: Array, links: Object });
const terminalElement = ref(null);
const selectedTemplate = ref('');
const sessionUuid = ref('');
const status = ref('connecting');
const busy = ref(false);
const error = ref('');
let terminal;
let fitAddon;
let channel;
let pendingInput = '';
let inputInFlight = false;
let inputTimer;
let resizeTimer;
let inputSubscription;
let resizeSubscription;

const isActive = computed(() => status.value === 'active');
const sessionUrl = (link) => link.replace('__SESSION__', encodeURIComponent(sessionUuid.value));

const sendInput = async () => {
    inputTimer = undefined;
    if (inputInFlight || !pendingInput || !sessionUuid.value || !isActive.value) return;
    const input = pendingInput;
    pendingInput = '';
    inputInFlight = true;
    try {
        await axios.post(sessionUrl(props.links.input), { input });
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Could not send terminal input.';
    } finally {
        inputInFlight = false;
    }
    if (pendingInput && !inputTimer) inputTimer = window.setTimeout(sendInput, 100);
};

const scheduleResize = () => {
    if (resizeTimer) window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(() => {
        if (!sessionUuid.value || !isActive.value || !terminal) return;
        axios.post(sessionUrl(props.links.resize), {
            columns: terminal.cols,
            rows: terminal.rows,
        }).catch(() => {});
    }, 200);
};

const mountTerminal = () => {
    terminal = new Terminal({
        cursorBlink: true,
        convertEol: true,
        fontSize: 13,
        fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace',
        scrollback: 5000,
        theme: { background: '#0b1220', foreground: '#d1fae5', cursor: '#d1fae5' },
    });
    fitAddon = new FitAddon();
    terminal.loadAddon(fitAddon);
    terminal.open(terminalElement.value);
    fitAddon.fit();
    terminal.options.disableStdin = true;
    inputSubscription = terminal.onData((data) => {
        pendingInput += data;
        if (!inputTimer) inputTimer = window.setTimeout(sendInput, 100);
    });
    resizeSubscription = terminal.onResize(scheduleResize);
    window.addEventListener('resize', fitTerminal);
};

const fitTerminal = () => {
    if (fitAddon) fitAddon.fit();
};

const connect = async () => {
    try {
        const { data } = await axios.post(props.links.start);
        sessionUuid.value = data.uuid;
        status.value = data.status;
        channel = window.Echo.private(`server-console.${sessionUuid.value}`);
        channel.listen('.console.output', (event) => {
            if (event.output) terminal.write(event.output);
            if (event.status) {
                status.value = event.status;
                terminal.options.disableStdin = event.status !== 'active';
                if (event.status === 'active') {
                    fitTerminal();
                    scheduleResize();
                    terminal.focus();
                }
            }
            if (typeof event.busy === 'boolean') busy.value = event.busy;
            if (event.error) {
                error.value = event.error;
                terminal.writeln(`\r\n\x1b[31m${event.error}\x1b[0m`);
            }
        });
        terminal.focus();
    } catch (exception) {
        status.value = 'failed';
        error.value = exception.response?.data?.message ?? 'Could not start the SSH session.';
        terminal.options.disableStdin = true;
        terminal.writeln(`\r\n\x1b[31m${error.value}\x1b[0m`);
    }
};

const runTemplate = async () => {
    if (!sessionUuid.value || busy.value || !isActive.value || !selectedTemplate.value) return;
    error.value = '';
    const template = props.templates.find((item) => item.id === Number(selectedTemplate.value));
    try {
        await axios.post(sessionUrl(props.links.commands), { template_id: Number(selectedTemplate.value) });
        terminal.writeln(`\r\n\x1b[36m[Running template: ${template?.name ?? 'saved template'}]\x1b[0m`);
        selectedTemplate.value = '';
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Could not queue the template.';
    }
};

const closeSession = async () => {
    if (sessionUuid.value && ['active', 'queued'].includes(status.value)) {
        try { await axios.delete(sessionUrl(props.links.close)); } catch { /* Session may already be gone. */ }
    }
    disconnect();
    router.visit(props.links.servers);
};

const disconnect = () => {
    if (channel && window.Echo) window.Echo.leave(`server-console.${sessionUuid.value}`);
    channel = null;
};

onMounted(() => {
    mountTerminal();
    connect();
});

onBeforeUnmount(() => {
    if (sessionUuid.value && ['active', 'queued'].includes(status.value)) {
        axios.delete(sessionUrl(props.links.close)).catch(() => {});
    }
    if (inputTimer) window.clearTimeout(inputTimer);
    if (resizeTimer) window.clearTimeout(resizeTimer);
    window.removeEventListener('resize', fitTerminal);
    inputSubscription?.dispose();
    resizeSubscription?.dispose();
    disconnect();
    terminal?.dispose();
});
</script>

<template>
    <Head :title="`Консоль · ${server.name}`" />
    <AppPageHeader :title="`Консоль · ${server.name}`" :description="`${server.ip} · Сеанс SSH (до 15 минут)`">
        <template #actions>
            <AppButton variant="secondary" :href="links.templates">Шаблоны команд</AppButton>
            <AppButton variant="secondary" :href="links.servers">К списку серверов</AppButton>
            <AppButton variant="danger" type="button" @click="closeSession">Отключиться</AppButton>
        </template>
    </AppPageHeader>

    <section class="section-block">
        <div class="section-block__header">
            <div class="section-block__title">
                <h2>Сеанс SSH <span class="console-status">{{ status }}<span v-if="busy"> · выполняется команда</span></span></h2>
                <p>Вводите команды в терминале. Поддерживаются интерактивные запросы, управляющие клавиши, ANSI-вывод и изменение размера терминала.</p>
            </div>
        </div>
        <div ref="terminalElement" class="server-console-terminal" aria-label="Терминал SSH" />
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <div v-if="templates.length" class="actions" style="margin-top: .75rem;">
            <select v-model="selectedTemplate" class="form-control" :disabled="!isActive || busy">
                <option value="">Запустить сохранённый шаблон…</option>
                <option v-for="template in templates" :key="template.id" :value="String(template.id)">{{ template.name }} · {{ template.format }}</option>
            </select>
            <AppButton type="button" variant="secondary" :disabled="!isActive || busy || !selectedTemplate" @click="runTemplate">Запустить шаблон</AppButton>
        </div>
    </section>
</template>

<style scoped>
.server-console-terminal {
    height: min(65vh, 46rem);
    min-height: 24rem;
    overflow: hidden;
    padding: .75rem;
    border: 1px solid var(--border-color, #334155);
    border-radius: .5rem;
    background: #0b1220;
}
.server-console-terminal :deep(.xterm) { height: 100%; }
.server-console-terminal :deep(.xterm-viewport) { overflow-y: auto !important; }
.console-status { margin-left: .5rem; font-size: .85rem; font-weight: 500; opacity: .75; }
</style>
