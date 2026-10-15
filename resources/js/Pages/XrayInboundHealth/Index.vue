<script setup>
import { onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';
import AppButton from '../../Shared/Admin/AppButton.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    inbounds: { type: Array, default: () => [] },
    summary: {
        type: Object,
        default: () => ({ total: 0, healthy: 0, unhealthy: 0, unsupported: 0, unknown: 0 }),
    },
});

const refreshTimer = ref(null);
const refreshAttempts = ref(0);

const statusLabel = (status) => ({
    healthy: 'Healthy',
    unhealthy: 'Unhealthy',
    unsupported: 'Unsupported',
    unknown: 'Not checked',
}[status] ?? status);

const statusClass = (status) => `health-status health-status--${status}`;

const formatDate = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';

const refreshResults = () => {
    router.reload({ only: ['inbounds', 'summary'], preserveScroll: true });
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

onUnmounted(() => window.clearTimeout(refreshTimer.value));
</script>

<template>
    <Head title="Inbound Health" />

    <main class="page-stack inbound-health-page">
        <AppPageHeader
            eyebrow="Network diagnostics"
            title="Inbound health"
            description="A dedicated client per inbound builds the subscription profile, starts its client core, then tests Discord, Telegram, Google, ChatGPT, and YouTube through the tunnel."
            :stats="[
                { label: 'Active inbounds', value: summary.total },
                { label: 'Healthy', value: summary.healthy },
                { label: 'Unhealthy', value: summary.unhealthy },
                { label: 'Unsupported', value: summary.unsupported },
                { label: 'Not checked', value: summary.unknown },
            ]"
        >
            <template #actions>
                <AppButton type="button" @click="runChecks">Run checks</AppButton>
            </template>
        </AppPageHeader>

        <section class="panel-card">
            <div v-if="!props.inbounds.length" class="empty-state">
                <strong>No active inbounds</strong>
                <p>Active inbounds on ready servers will appear here.</p>
            </div>

            <div v-else class="table-scroll">
                <table class="data-table inbound-health-table">
                    <thead>
                        <tr>
                            <th>Server / inbound</th>
                            <th>Protocol</th>
                            <th>Status</th>
                            <th>Destinations</th>
                            <th>Last checked</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inbound in props.inbounds" :key="inbound.id">
                            <td>
                                <strong>{{ inbound.server }}</strong>
                                <small>{{ inbound.remark || `Inbound ${inbound.external_id}` }} · #{{ inbound.external_id }}</small>
                            </td>
                            <td>{{ inbound.protocol }}</td>
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
                                    <summary>Probe details</summary>
                                    <ul>
                                        <li v-for="(result, name) in inbound.results" :key="name">
                                            <strong>{{ name }}:</strong>
                                            <span v-if="result.ok">HTTP {{ result.status }}</span>
                                            <span v-else>{{ result.error || `HTTP ${result.status ?? 'request failed'}` }}</span>
                                        </li>
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="inbound-health-note">
            WireGuard and AmneziaWG full-tunnel checks are marked unsupported until the runner can safely establish those tunnel types.
            An unsupported result does not mean the inbound is down.
        </p>
    </main>
</template>

<style scoped>
.inbound-health-page { gap: 1.25rem; }
.panel-card { overflow: hidden; border: 1px solid var(--border-color, #e5e7eb); border-radius: 1rem; background: var(--surface-card, #fff); }
.table-scroll { overflow-x: auto; }
.inbound-health-table { width: 100%; border-collapse: collapse; }
.inbound-health-table th, .inbound-health-table td { padding: .9rem 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); text-align: left; vertical-align: top; }
.inbound-health-table th { color: var(--text-muted, #64748b); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
.inbound-health-table td strong, .inbound-health-table td small { display: block; }
.inbound-health-table td small { margin-top: .25rem; color: var(--text-muted, #64748b); }
.health-status { display: inline-flex; padding: .25rem .55rem; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; }
.health-status--healthy, .target-ok { color: #166534; background: #dcfce7; }
.health-status--unhealthy, .target-failed { color: #991b1b; background: #fee2e2; }
.health-status--unsupported { color: #854d0e; background: #fef3c7; }
.health-status--unknown { color: #475569; background: #e2e8f0; }
.health-targets { display: flex; flex-wrap: wrap; gap: .35rem; }
.health-targets span { padding: .2rem .4rem; border-radius: .35rem; font-size: .75rem; white-space: nowrap; }
.health-error { min-width: 14rem; max-width: 25rem; color: #991b1b; font-size: .85rem; }
.health-result-details { margin-top: .4rem; color: var(--text-muted, #64748b); }
.health-result-details summary { cursor: pointer; color: var(--text-color, #334155); }
.health-result-details ul { margin: .4rem 0 0; padding-left: 1.1rem; }
.health-result-details li { margin-top: .2rem; overflow-wrap: anywhere; }
.muted, .inbound-health-note { color: var(--text-muted, #64748b); }
.inbound-health-note { margin: 0; font-size: .875rem; }
.empty-state { padding: 2.5rem; text-align: center; }
.empty-state p { margin-bottom: 0; color: var(--text-muted, #64748b); }
</style>
