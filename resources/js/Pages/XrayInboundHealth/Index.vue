<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';
import AppButton from '../../Shared/Admin/AppButton.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    inbounds: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    technicalUser: { type: Object, default: null },
    healthChecksEnabled: { type: Boolean, default: true },
    profileSummary: {
        type: Object,
        default: () => ({ total: 0, healthy: 0, warning: 0, unhealthy: 0, unsupported: 0, unknown: 0 }),
    },
    profileFlowSummary: {
        type: Object,
        default: () => ({ connect: 0, connectV2: 0 }),
    },
    summary: {
        type: Object,
        default: () => ({ total: 0, healthy: 0, warning: 0, unhealthy: 0, unsupported: 0, unknown: 0 }),
    },
    globalJsonSettings: {
        type: Object,
        default: () => ({ active: 0, applied_name: null }),
    },
});

const refreshTimer = ref(null);
const refreshAttempts = ref(0);

const statusLabel = (status) => ({
    healthy: 'Доступен',
    warning: 'Есть проблемы',
    unhealthy: 'Недоступен',
    unsupported: 'Не поддерживается',
    pending: 'В очереди',
    unknown: 'Не проверялся',
    included: 'Включён',
    hidden: 'Скрыт настройками узла',
    'no-config': 'Нет активного тестового клиента',
}[status] ?? status);

const statusClass = (status) => `health-status health-status--${status}`;

const matrixStatuses = ['unhealthy', 'warning', 'pending', 'healthy', 'unsupported', 'unknown'];
const matrixStatus = (statuses) => matrixStatuses.find((status) => statuses.includes(status)) ?? 'unknown';
const matrixTooltip = (row, column) => {
    const checks = row.checks[column];
    const services = new Map();
    checks.forEach((check) => Object.entries(check.results || {}).forEach(([name, result]) => {
        if (typeof result?.ok !== 'boolean') return;
        const previous = services.get(name);
        services.set(name, {
            ok: previous ? previous.ok && result.ok : result.ok,
            error: result.error || previous?.error || '',
        });
    }));

    const passed = [...services].filter(([, result]) => result.ok).map(([name]) => name);
    const failed = [...services].filter(([, result]) => !result.ok).map(([name, result]) => (
        result.error ? `${name} (${result.error})` : name
    ));
    const lines = [`Состояние: ${statusLabel(matrixStatus(checks.map((check) => check.status)))}`];
    if (passed.length) lines.push(`Прошли: ${passed.join(', ')}`);
    if (failed.length) lines.push(`Не прошли: ${failed.join(', ')}`);
    if (!passed.length && !failed.length) lines.push('Нет результатов по отдельным сервисам');
    return lines.join('\n');
};
const matrixRows = computed(() => {
    const rows = new Map();
    const ensureRow = (name) => {
        const label = String(name || 'Неизвестный узел').split(' • ')[0].trim();
        if (!rows.has(label)) rows.set(label, {
            name: label,
            checks: { direct: [], connect: [], connectV2: [], external: [], custom: [] },
        });
        return rows.get(label);
    };

    props.inbounds.forEach((inbound) => {
        const row = ensureRow(inbound.server);
        row.checks.direct.push({ status: inbound.status, results: inbound.results });
    });
    props.profiles.forEach((profile) => {
        const row = ensureRow(profile.name);
        const column = ({
            connect: 'connect',
            'connect-v2': 'connectV2',
            external: 'external',
            'external-source': 'external',
            'xray-custom': 'custom',
        })[profile.flow];
        if (column) row.checks[column].push({ status: profile.status, results: profile.results });
    });

    return [...rows.values()].map((row) => Object.fromEntries([
        ['name', row.name],
        ['checks', row.checks],
        ...Object.keys(row.checks).map((column) => [column, matrixStatus(row.checks[column].map((check) => check.status))]),
    ]));
});

const matrixEmoji = (status) => ({
    healthy: '✅', warning: '⚠️', unhealthy: '❌', pending: '⏳', unsupported: '➖', unknown: '—',
}[status] ?? '—');

const flowLabel = (flow) => ({
    connect: '/connect',
    'connect-v2': '/connect-v2',
    external: 'Внешняя подписка',
    'external-source': 'Синхронизация внешнего источника',
    'xray-custom': 'Пользовательская подписка Xray',
}[flow] ?? flow);

const formatDate = (value) => value
    ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';

const refreshResults = () => {
    router.reload({ only: ['inbounds', 'summary', 'profiles', 'profileSummary', 'profileFlowSummary', 'globalJsonSettings'], preserveScroll: true });
    refreshAttempts.value += 1;

    if (refreshAttempts.value < 12) {
        refreshTimer.value = window.setTimeout(refreshResults, 5000);
    }
};

const runChecks = () => router.post('/xray-inbound-health/run', {}, {
    preserveScroll: true,
    onSuccess: () => {
        window.clearTimeout(refreshTimer.value);
        refreshAttempts.value = 0;
        refreshTimer.value = window.setTimeout(refreshResults, 5000);
    },
});

const toggleHealthChecks = () => router.put('/xray-inbound-health/settings', {
    is_enabled: !props.healthChecksEnabled,
}, { preserveScroll: true });

onUnmounted(() => window.clearTimeout(refreshTimer.value));
</script>

<template>
    <Head title="Проверка узлов Xray" />

    <main class="page-stack inbound-health-page">
        <AppPageHeader
            eyebrow="Диагностика сети"
            title="Проверка узлов Xray"
            :description="`Проверка входящих узлов выполняется напрямую, без правил маршрутизации приложения. Также проверяются профили подписок и доступность Discord, Telegram, Google, ChatGPT и YouTube. ${props.globalJsonSettings.applied_name ? `Последняя активная общая настройка JSON: ${props.globalJsonSettings.applied_name}.` : 'Общие настройки JSON не применяются.'}`"
            :stats="[
                { label: 'Активные узлы', value: summary.total },
                { label: 'Доступны', value: summary.healthy },
                { label: 'Есть проблемы', value: summary.warning },
                { label: 'Недоступны', value: summary.unhealthy },
                { label: 'Не поддерживаются', value: summary.unsupported },
                { label: 'Не проверялись', value: summary.unknown },
                { label: 'Активные общие настройки JSON', value: props.globalJsonSettings.active },
            ]"
        >
            <template #actions>
                <AppButton variant="secondary" type="button" @click="toggleHealthChecks">
                    {{ healthChecksEnabled ? 'Приостановить проверки' : 'Возобновить проверки' }}
                </AppButton>
                <AppButton type="button" :disabled="!healthChecksEnabled" @click="runChecks">Запустить проверку</AppButton>
            </template>
        </AppPageHeader>

        <section class="panel-card health-check-setting" :class="{ 'health-check-setting--disabled': !healthChecksEnabled }">
            <strong>{{ healthChecksEnabled ? 'Проверки включены' : 'Проверки приостановлены' }}</strong>
            <p>{{ healthChecksEnabled ? 'Проверки выполняются по расписанию и вручную.' : 'Плановые проверки приостановлены. Ранее полученные результаты сохранены.' }}</p>
        </section>

        <section class="panel-card">
            <header class="profile-health-header">
                <div>
                    <h2>Сводка по узлам</h2>
                    <p>✅ доступен · ⚠️ нестабильно · ❌ ошибка · ➖ не поддерживается</p>
                </div>
            </header>
            <div v-if="!matrixRows.length" class="empty-state">
                <strong>Проверок пока нет</strong>
            </div>
            <div v-else class="table-scroll">
                <table class="data-table inbound-health-table health-matrix">
                    <thead><tr><th>Имя</th><th>Direct</th><th>/connect</th><th>/connect-v2</th><th>Внешние</th><th>Custom config</th></tr></thead>
                    <tbody>
                        <tr v-for="row in matrixRows" :key="row.name">
                            <th scope="row">{{ row.name }}</th>
                            <td v-for="column in ['direct', 'connect', 'connectV2', 'external', 'custom']" :key="column" :title="matrixTooltip(row, column)">{{ matrixEmoji(row[column]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <details class="health-details">
            <summary>Подробные результаты по узлам и профилям</summary>
        <section class="panel-card">
            <header class="profile-health-header">
                <div>
                    <h2>Входящие узлы</h2>
                    <p>Для проверки используется технический клиент, правила маршрутизации приложения обходятся.</p>
                </div>
            </header>
            <div v-if="!props.inbounds.length" class="empty-state">
                <strong>Нет активных входящих узлов</strong>
                <p>Здесь появятся активные узлы на готовых серверах.</p>
            </div>

            <div v-else class="table-scroll">
                <table class="data-table inbound-health-table">
                    <thead>
                        <tr>
                            <th>Сервер / узел</th>
                            <th>Протокол</th>
                            <th>/connect</th>
                            <th>/connect-v2</th>
                            <th>Состояние</th>
                            <th>Сайты</th>
                            <th>Время проверки</th>
                            <th>Подробности</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inbound in props.inbounds" :key="inbound.id">
                            <td>
                                <strong>{{ inbound.server }}</strong>
                                <small>{{ inbound.remark || `Inbound ${inbound.external_id}` }} · #{{ inbound.external_id }}</small>
                            </td>
                            <td>{{ inbound.protocol }}</td>
                            <td><span :class="statusClass(inbound.connect_inclusion)">{{ statusLabel(inbound.connect_inclusion) }}</span></td>
                            <td><span :class="statusClass(inbound.connect_v2_inclusion)">{{ statusLabel(inbound.connect_v2_inclusion) }}</span></td>
                            <td><span :class="statusClass(inbound.status)">{{ statusLabel(inbound.status) }}</span></td>
                            <td>
                                <div v-if="Object.keys(inbound.results).length" class="health-targets">
                                    <span v-for="(result, name) in inbound.results" :key="name" :class="result.ok ? 'target-ok' : 'target-failed'">
                                        {{ name }}<template v-if="result.status"> · {{ result.status }}</template>
                                    </span>
                                </div>
                                <span v-else class="muted">—</span>
                            </td>
                            <td>{{ formatDate(inbound.checked_at) }}</td>
                            <td class="health-error">
                                <span>{{ inbound.error || '—' }}</span>
                                <details v-if="Object.keys(inbound.results).length" class="health-result-details">
                                    <summary>Результаты проверки</summary>
                                    <ul>
                                        <li v-for="(result, name) in inbound.results" :key="name">
                                            <strong>{{ name }}:</strong>
                                            <span v-if="result.ok">HTTP {{ result.status }}</span>
                                            <span v-else>{{ result.error || `HTTP ${result.status ?? 'запрос не выполнен'}` }}</span>
                                        </li>
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel-card">
            <header class="profile-health-header">
                <div>
                    <h2>Проверка профилей подписок</h2>
                    <div>
                        <p>
                            Проверка сформированных профилей для пользователя {{ props.technicalUser?.name ?? '@OksanaVpnDebugBot' }}
                            (Telegram ID {{ props.technicalUser?.telegram_id ?? '000000000' }}).
                        </p>
                        <p class="flow-coverage">
                            /connect: {{ profileFlowSummary.connect }} профилей ·
                            /connect-v2: {{ profileFlowSummary.connectV2 }} профилей
                        </p>
                    </div>
                </div>
                <div class="profile-health-counts">
                    <span>Доступны: {{ profileSummary.healthy }}</span>
                    <span>Проблемы: {{ profileSummary.warning }}</span>
                    <span>Недоступны: {{ profileSummary.unhealthy }}</span>
                    <span>Не поддерживаются: {{ profileSummary.unsupported }}</span>
                </div>
            </header>
            <div v-if="!props.profiles.length" class="empty-state">
                <strong>Профили подписок ещё не проверялись</strong>
                <p>Запустите проверку, чтобы сформировать и проверить подписки технического пользователя.</p>
            </div>
            <div v-else class="table-scroll">
                <table class="data-table inbound-health-table">
                    <thead>
                        <tr>
                            <th>Сценарий</th>
                            <th>Профиль</th>
                            <th>Состояние</th>
                            <th>Сайты</th>
                            <th>Время проверки</th>
                            <th>Подробности</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="profile in props.profiles" :key="profile.id">
                            <td>{{ flowLabel(profile.flow) }}</td>
                            <td>{{ profile.name }}</td>
                            <td><span :class="statusClass(profile.status)">{{ statusLabel(profile.status) }}</span></td>
                            <td>
                                <div v-if="Object.keys(profile.results).length" class="health-targets">
                                    <span v-for="(result, name) in profile.results" :key="name" :class="result.ok ? 'target-ok' : 'target-failed'">
                                        {{ name }}<template v-if="result.status"> · {{ result.status }}</template>
                                    </span>
                                </div>
                                <span v-else class="muted">—</span>
                            </td>
                            <td>{{ formatDate(profile.checked_at) }}</td>
                            <td class="health-error">
                                <span>{{ profile.error || '—' }}</span>
                                <details v-if="Object.keys(profile.results).length" class="health-result-details">
                                    <summary>Результаты проверки</summary>
                                    <ul>
                                        <li v-for="(result, name) in profile.results" :key="name">
                                            <strong>{{ name }}:</strong>
                                            <span v-if="result.error">{{ result.error }}</span>
                                            <span v-else-if="result.last_synced_at">Синхронизировано {{ formatDate(result.last_synced_at) }}</span>
                                            <span v-else-if="result.ok">{{ result.status ? `HTTP ${result.status}` : 'OK' }}</span>
                                            <span v-else>HTTP {{ result.status ?? 'запрос не выполнен' }}</span>
                                        </li>
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        </details>

        <p class="inbound-health-note">
            В профилях применяется только последняя активная общая настройка JSON. Сохранено активных настроек: {{ props.globalJsonSettings.active }}.
            Полная проверка туннелей WireGuard и AmneziaWG пока не поддерживается. Такой результат не означает, что узел недоступен.
        </p>
    </main>
</template>

<style scoped>
.inbound-health-page { gap: 1.25rem; }
.health-details > summary { padding: .75rem 0; color: var(--text-muted, #64748b); cursor: pointer; font-size: .9rem; }
.health-details[open] { display: grid; gap: 1rem; }
.panel-card { overflow: hidden; border: 1px solid var(--border-color, #e5e7eb); border-radius: 1rem; background: var(--surface-card, #fff); }
.table-scroll { overflow-x: auto; }
.inbound-health-table { width: 100%; border-collapse: collapse; }
.health-matrix { min-width: 38rem; }
.health-matrix th:not(:first-child), .health-matrix td { text-align: center; }
.health-matrix tbody th { padding: .65rem 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); text-align: left; }
.health-matrix td { font-size: 1.1rem; }
.inbound-health-table th, .inbound-health-table td { padding: .9rem 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); text-align: left; vertical-align: top; }
.inbound-health-table th { color: var(--text-muted, #64748b); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
.inbound-health-table td strong, .inbound-health-table td small { display: block; }
.inbound-health-table td small { margin-top: .25rem; color: var(--text-muted, #64748b); }
.health-status { display: inline-flex; padding: .25rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
.health-status--healthy, .target-ok { color: #166534; background: #dcfce7; }
.health-status--unhealthy, .target-failed { color: #991b1b; background: #fee2e2; }
.health-status--warning { color: #854d0e; background: #fef3c7; }
.health-status--unsupported { color: #854d0e; background: #fef3c7; }
.health-status--pending { color: #1d4ed8; background: #dbeafe; }
.health-status--unknown { color: #475569; background: #e2e8f0; }
.health-status--included { color: #166534; background: #dcfce7; }
.health-status--hidden { color: #475569; background: #e2e8f0; }
.health-status--no-config { color: #854d0e; background: #fef3c7; }
.health-targets { display: flex; flex-wrap: wrap; gap: .35rem; }
.health-targets span { padding: .2rem .4rem; border-radius: .35rem; font-size: .75rem; white-space: nowrap; }
.health-error { min-width: 14rem; max-width: 25rem; color: #991b1b; font-size: .85rem; }
.health-result-details { margin-top: .4rem; color: var(--text-muted, #64748b); }
.health-result-details summary { cursor: pointer; color: var(--text-color, #334155); }
.health-result-details ul { margin: .4rem 0 0; padding-left: 1.1rem; }
.health-result-details li { margin-top: .2rem; overflow-wrap: anywhere; }
.muted, .inbound-health-note { color: var(--text-muted, #64748b); }
.inbound-health-note { margin: 0; font-size: .875rem; }
.profile-health-header { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color, #e5e7eb); }
.profile-health-header h2, .profile-health-header p { margin: 0; }
.profile-health-header p { margin-top: .25rem; color: var(--text-muted, #64748b); font-size: .875rem; }
.profile-health-header .flow-coverage { font-weight: 600; }
.profile-health-counts { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .8rem; color: var(--text-muted, #64748b); }
.empty-state { padding: 2.5rem; text-align: center; }
.empty-state p { margin-bottom: 0; color: var(--text-muted, #64748b); }
</style>
