<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            date_from: '',
            date_to: '',
            range_label: '',
        }),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    financial_segments: {
        type: Array,
        default: () => [],
    },
    invoice_tax_status_segments: {
        type: Array,
        default: () => [],
    },
    invoice_state_segments: {
        type: Array,
        default: () => [],
    },
    server_costs: {
        type: Array,
        default: () => [],
    },
    top_servers: {
        type: Array,
        default: () => [],
    },
    monthly_trend: {
        type: Array,
        default: () => [],
    },
});

const filterForm = useForm({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

const submitFilters = () => {
    filterForm.get('/reports', {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterForm.date_from = '';
    filterForm.date_to = '';
    submitFilters();
};

const formatMoney = (value) => new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}).format(Number(value ?? 0));

const formatPercent = (value) => new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 1,
}).format(Number(value ?? 0));

const formatCompactMoney = (value) => {
    const amount = Number(value ?? 0);

    if (Math.abs(amount) >= 1000) {
        return `${new Intl.NumberFormat('ru-RU', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 1,
        }).format(amount / 1000)}k`;
    }

    return formatMoney(amount);
};

const buildChart = (segments) => {
    const normalized = (segments ?? []).filter((segment) => Number(segment.value) > 0);
    const total = normalized.reduce((sum, segment) => sum + Number(segment.value), 0);

    if (!total) {
        return {
            total: 0,
            style: { background: 'conic-gradient(#e2e8f0 0deg 360deg)' },
            segments: [],
        };
    }

    let cursor = 0;
    const stops = normalized.map((segment) => {
        const degrees = (Number(segment.value) / total) * 360;
        const start = cursor;
        const end = cursor + degrees;
        cursor = end;

        return {
            ...segment,
            percent: (Number(segment.value) / total) * 100,
            start,
            end,
        };
    });

    return {
        total,
        style: {
            background: `conic-gradient(${stops.map((segment) => `${segment.color} ${segment.start}deg ${segment.end}deg`).join(', ')})`,
        },
        segments: stops,
    };
};

const financeChart = computed(() => buildChart(props.financial_segments));
const taxChart = computed(() => buildChart(props.invoice_tax_status_segments));
const invoiceStateChart = computed(() => buildChart(props.invoice_state_segments));

const trendMax = computed(() => Math.max(
    ...props.monthly_trend.flatMap((item) => [Number(item.revenue ?? 0), Number(item.server_costs ?? 0), Number(item.net_profit ?? 0)]),
    1,
));

const highlightCards = computed(() => [
    {
        label: 'Выручка',
        value: props.summary.revenue,
        tone: 'emerald',
        note: `${props.summary.paid_invoices_count ?? 0} оплаченных инвойсов`,
    },
    {
        label: 'Расходы на серверы',
        value: props.summary.total_server_costs,
        tone: 'blue',
        note: `${props.server_costs.length} серверов в учете`,
    },
    {
        label: 'Налоги 4%',
        value: props.summary.estimated_taxes,
        tone: 'amber',
        note: 'Оценка от оплаченных инвойсов',
    },
    {
        label: 'Чистый результат',
        value: props.summary.net_profit,
        tone: props.summary.net_profit >= 0 ? 'rose' : 'slate',
        note: `Маржа ${formatPercent(props.summary.margin_percent)}%`,
    },
]);
</script>

<template>
    <Head title="Отчеты" />

    <section class="reports-hero">
        <div class="reports-hero__content">
            <p class="reports-kicker">Финансовая аналитика</p>
            <h1>Отчеты по доходам, расходам и динамике подписок</h1>
            <p class="reports-lead">
                Сводка собирает выручку по инвойсам, затраты по истории цен серверов и добавляет примерный налог 4%,
                чтобы быстрее понимать реальную картину по проекту.
            </p>

            <div class="reports-hero__meta">
                <span class="reports-pill">Период: {{ filters.range_label }}</span>
                <span class="reports-pill reports-pill--muted">Активных подписчиков: {{ summary.active_subscribers }}</span>
                <span class="reports-pill reports-pill--muted">Новых пользователей: {{ summary.new_users }}</span>
            </div>
        </div>

        <form class="reports-filter" @submit.prevent="submitFilters">
            <label class="field">
                <span>Дата от</span>
                <AppInput v-model="filterForm.date_from" type="date" />
            </label>

            <label class="field">
                <span>Дата до</span>
                <AppInput v-model="filterForm.date_to" type="date" />
            </label>

            <div class="reports-filter__actions">
                <AppButton type="submit" :disabled="filterForm.processing">Обновить</AppButton>
                <AppButton variant="secondary" type="button" :disabled="filterForm.processing" @click="resetFilters">
                    Сбросить
                </AppButton>
            </div>
        </form>
    </section>

    <section class="reports-grid reports-grid--cards">
        <article
            v-for="card in highlightCards"
            :key="card.label"
            class="metric-card"
            :class="`metric-card--${card.tone}`"
        >
            <p>{{ card.label }}</p>
            <h2>{{ formatMoney(card.value) }} ₽</h2>
            <span>{{ card.note }}</span>
        </article>
    </section>

    <section class="reports-grid reports-grid--main">
        <article class="panel panel--spotlight">
            <div class="panel__header">
                <div>
                    <p class="panel__eyebrow">Баланс периода</p>
                    <h3>Куда уходят деньги</h3>
                </div>
                <strong>{{ formatMoney(summary.net_profit) }} ₽</strong>
            </div>

            <div class="donut-layout">
                <div class="donut-card">
                    <div class="donut" :style="financeChart.style">
                        <div class="donut__center">
                            <span>Выручка</span>
                            <strong>{{ formatMoney(summary.revenue) }} ₽</strong>
                        </div>
                    </div>
                </div>

                <div class="legend">
                    <div
                        v-for="segment in financeChart.segments"
                        :key="segment.label"
                        class="legend__row"
                    >
                        <span class="legend__swatch" :style="{ backgroundColor: segment.color }" />
                        <div>
                            <strong>{{ segment.label }}</strong>
                            <p>{{ formatMoney(segment.value) }} ₽ · {{ formatPercent(segment.percent) }}%</p>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <article class="panel">
            <div class="panel__header">
                <div>
                    <p class="panel__eyebrow">Налоги и статусы</p>
                    <h3>Оплаченные инвойсы</h3>
                </div>
            </div>

            <div class="mini-donuts">
                <div class="mini-donut-card">
                    <div class="donut donut--small" :style="taxChart.style">
                        <div class="donut__center donut__center--small">
                            <strong>{{ summary.paid_invoices_count }}</strong>
                            <span>в налоговой</span>
                        </div>
                    </div>

                    <div class="legend legend--compact">
                        <div v-for="segment in taxChart.segments" :key="segment.label" class="legend__row">
                            <span class="legend__swatch" :style="{ backgroundColor: segment.color }" />
                            <div>
                                <strong>{{ segment.label }}</strong>
                                <p>{{ segment.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mini-donut-card">
                    <div class="donut donut--small" :style="invoiceStateChart.style">
                        <div class="donut__center donut__center--small">
                            <strong>{{ summary.paid_invoices_count }}</strong>
                            <span>оплачено</span>
                        </div>
                    </div>

                    <div class="legend legend--compact">
                        <div v-for="segment in invoiceStateChart.segments" :key="segment.label" class="legend__row">
                            <span class="legend__swatch" :style="{ backgroundColor: segment.color }" />
                            <div>
                                <strong>{{ segment.label }}</strong>
                                <p>{{ segment.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="reports-grid reports-grid--secondary">
        <article class="panel">
            <div class="panel__header">
                <div>
                    <p class="panel__eyebrow">Операционные затраты</p>
                    <h3>Серверы с самым дорогим периодом</h3>
                </div>
            </div>

            <div class="server-list">
                <div v-for="server in top_servers" :key="server.id" class="server-list__item">
                    <div>
                        <strong>{{ server.name }}</strong>
                        <p>
                            {{ server.code }}
                            <span v-if="server.is_deleted" class="server-list__deleted">soft deleted</span>
                        </p>
                    </div>
                    <div class="server-list__value">
                        <strong>{{ formatMoney(server.total_cost) }} ₽</strong>
                        <span>{{ server.price_points }} ценовых точек</span>
                    </div>
                </div>

                <div v-if="!top_servers.length" class="empty-state">Для выбранного периода пока нет расходов по серверам.</div>
            </div>
        </article>

        <article class="panel">
            <div class="panel__header">
                <div>
                    <p class="panel__eyebrow">Подписки и пользователи</p>
                    <h3>Пульс продукта</h3>
                </div>
            </div>

            <div class="pulse-grid">
                <div class="pulse-card">
                    <span>Стартовало подписок</span>
                    <strong>{{ summary.subscriptions_started }}</strong>
                    <p>{{ formatMoney(summary.subscriptions_revenue) }} ₽ номинальной стоимости</p>
                </div>
                <div class="pulse-card">
                    <span>Средний чек</span>
                    <strong>{{ formatMoney(summary.average_invoice) }} ₽</strong>
                    <p>По оплаченных инвойсам за период</p>
                </div>
                <div class="pulse-card">
                    <span>Активных подписчиков</span>
                    <strong>{{ summary.active_subscribers }}</strong>
                    <p>На конец выбранного периода</p>
                </div>
                <div class="pulse-card">
                    <span>Новых пользователей</span>
                    <strong>{{ summary.new_users }}</strong>
                    <p>Добавились за выбранный интервал</p>
                </div>
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel__header">
            <div>
                <p class="panel__eyebrow">Тренд по месяцам</p>
                <h3>Последние шесть месяцев</h3>
            </div>
        </div>

        <div class="trend-grid">
            <div v-for="month in monthly_trend" :key="month.label" class="trend-card">
                <div class="trend-card__head">
                    <strong>{{ month.label }}</strong>
                    <span :class="month.net_profit >= 0 ? 'trend-chip trend-chip--positive' : 'trend-chip trend-chip--negative'">
                        {{ month.net_profit >= 0 ? '+' : '' }}{{ formatCompactMoney(month.net_profit) }} ₽
                    </span>
                </div>

                <div class="trend-bars">
                    <div class="trend-bar">
                        <span>Выручка</span>
                        <div class="trend-bar__track">
                            <div class="trend-bar__fill trend-bar__fill--revenue" :style="{ width: `${(Number(month.revenue) / trendMax) * 100}%` }" />
                        </div>
                        <strong>{{ formatCompactMoney(month.revenue) }} ₽</strong>
                    </div>
                    <div class="trend-bar">
                        <span>Серверы</span>
                        <div class="trend-bar__track">
                            <div class="trend-bar__fill trend-bar__fill--costs" :style="{ width: `${(Number(month.server_costs) / trendMax) * 100}%` }" />
                        </div>
                        <strong>{{ formatCompactMoney(month.server_costs) }} ₽</strong>
                    </div>
                    <div class="trend-bar">
                        <span>Налоги</span>
                        <div class="trend-bar__track">
                            <div class="trend-bar__fill trend-bar__fill--taxes" :style="{ width: `${(Number(month.estimated_taxes) / trendMax) * 100}%` }" />
                        </div>
                        <strong>{{ formatCompactMoney(month.estimated_taxes) }} ₽</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.reports-hero,
.panel,
.metric-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(125, 211, 252, 0.18), transparent 32%),
        radial-gradient(circle at bottom left, rgba(253, 186, 116, 0.12), transparent 28%),
        rgba(255, 255, 255, 0.96);
    box-shadow: 0 28px 80px rgba(15, 23, 42, 0.08);
}

.reports-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(320px, 0.9fr);
    gap: 24px;
    padding: 32px;
}

.reports-kicker,
.panel__eyebrow {
    margin: 0 0 10px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #0f766e;
}

.reports-hero h1,
.panel h3 {
    margin: 0;
    color: #0f172a;
}

.reports-hero h1 {
    max-width: 12ch;
    font-size: clamp(2rem, 4vw, 3.4rem);
    line-height: 0.98;
}

.reports-lead {
    max-width: 68ch;
    margin: 18px 0 0;
    color: #475569;
    line-height: 1.65;
}

.reports-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 24px;
}

.reports-pill {
    display: inline-flex;
    align-items: center;
    min-height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    background: #0f172a;
    color: #f8fafc;
    font-size: 0.92rem;
}

.reports-pill--muted {
    background: rgba(226, 232, 240, 0.8);
    color: #0f172a;
}

.reports-filter {
    display: grid;
    gap: 16px;
    padding: 22px;
    border-radius: 24px;
    background: rgba(15, 23, 42, 0.95);
    color: #f8fafc;
}

.reports-filter__actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.field {
    display: grid;
    gap: 8px;
    font-size: 0.92rem;
}

.field input {
    min-height: 48px;
    padding: 0 14px;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.08);
    color: inherit;
}

.reports-grid {
    display: grid;
    gap: 20px;
}

.reports-grid--cards {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.reports-grid--main {
    grid-template-columns: minmax(0, 1.2fr) minmax(380px, 0.9fr);
}

.reports-grid--secondary {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.metric-card {
    padding: 24px;
}

.metric-card p,
.metric-card span {
    margin: 0;
    color: #475569;
}

.metric-card h2 {
    margin: 14px 0 10px;
    font-size: clamp(1.75rem, 3vw, 2.5rem);
    color: #0f172a;
}

.metric-card--emerald::before,
.metric-card--blue::before,
.metric-card--amber::before,
.metric-card--rose::before,
.metric-card--slate::before {
    content: '';
    position: absolute;
    inset: 0 auto auto 0;
    width: 100%;
    height: 6px;
}

.metric-card--emerald::before { background: linear-gradient(90deg, #10b981, #34d399); }
.metric-card--blue::before { background: linear-gradient(90deg, #2563eb, #38bdf8); }
.metric-card--amber::before { background: linear-gradient(90deg, #f59e0b, #fb923c); }
.metric-card--rose::before { background: linear-gradient(90deg, #e11d48, #fb7185); }
.metric-card--slate::before { background: linear-gradient(90deg, #334155, #94a3b8); }

.panel {
    padding: 28px;
}

.panel--spotlight {
    background:
        radial-gradient(circle at top right, rgba(59, 130, 246, 0.2), transparent 34%),
        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.14), transparent 28%),
        rgba(255, 255, 255, 0.98);
}

.panel__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 20px;
}

.panel__header strong {
    font-size: 1.6rem;
    color: #0f172a;
}

.donut-layout,
.mini-donuts {
    display: grid;
    gap: 20px;
}

.donut-layout {
    grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
    align-items: center;
}

.mini-donuts {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.donut-card,
.mini-donut-card {
    display: grid;
    gap: 18px;
}

.donut {
    position: relative;
    display: grid;
    place-items: center;
    width: min(100%, 290px);
    aspect-ratio: 1;
    margin: 0 auto;
    border-radius: 50%;
}

.donut::after {
    content: '';
    position: absolute;
    inset: 16%;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.16);
}

.donut--small {
    width: min(100%, 190px);
}

.donut__center {
    position: relative;
    z-index: 1;
    display: grid;
    gap: 4px;
    text-align: center;
}

.donut__center span {
    color: #64748b;
    font-size: 0.9rem;
}

.donut__center strong {
    color: #0f172a;
    font-size: 1.35rem;
}

.donut__center--small strong {
    font-size: 1.5rem;
}

.legend {
    display: grid;
    gap: 14px;
}

.legend--compact {
    gap: 10px;
}

.legend__row {
    display: grid;
    grid-template-columns: 14px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.legend__row p,
.legend__row strong,
.server-list__item p,
.pulse-card p {
    margin: 0;
}

.legend__row p,
.server-list__item p,
.pulse-card p {
    color: #64748b;
}

.legend__swatch {
    width: 14px;
    height: 14px;
    margin-top: 4px;
    border-radius: 999px;
}

.server-list,
.pulse-grid,
.trend-grid {
    display: grid;
    gap: 14px;
}

.server-list__item,
.pulse-card,
.trend-card {
    padding: 16px 18px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    border-radius: 20px;
    background: rgba(248, 250, 252, 0.92);
}

.server-list__item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.server-list__value {
    text-align: right;
}

.server-list__value strong,
.pulse-card strong,
.trend-card strong {
    color: #0f172a;
}

.server-list__deleted {
    display: inline-flex;
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.9);
    color: #475569;
    font-size: 0.74rem;
    text-transform: uppercase;
}

.pulse-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.pulse-card span {
    color: #0f766e;
    font-size: 0.9rem;
    font-weight: 600;
}

.pulse-card strong {
    display: block;
    margin: 10px 0 8px;
    font-size: 2rem;
}

.trend-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.trend-card__head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.trend-chip {
    display: inline-flex;
    align-items: center;
    min-height: 32px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 0.85rem;
}

.trend-chip--positive {
    background: rgba(220, 252, 231, 0.95);
    color: #166534;
}

.trend-chip--negative {
    background: rgba(254, 226, 226, 0.95);
    color: #991b1b;
}

.trend-bars {
    display: grid;
    gap: 12px;
}

.trend-bar {
    display: grid;
    grid-template-columns: 72px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    font-size: 0.9rem;
}

.trend-bar__track {
    position: relative;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.9);
}

.trend-bar__fill {
    height: 100%;
    border-radius: inherit;
}

.trend-bar__fill--revenue { background: linear-gradient(90deg, #0f766e, #34d399); }
.trend-bar__fill--costs { background: linear-gradient(90deg, #1d4ed8, #60a5fa); }
.trend-bar__fill--taxes { background: linear-gradient(90deg, #ea580c, #fb923c); }

.empty-state {
    padding: 16px 18px;
    border-radius: 18px;
    background: rgba(248, 250, 252, 0.9);
    color: #64748b;
}

@media (max-width: 1200px) {
    .reports-grid--cards,
    .reports-grid--secondary,
    .trend-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .reports-grid--main,
    .reports-hero,
    .donut-layout,
    .mini-donuts {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .reports-hero,
    .panel,
    .metric-card {
        padding: 20px;
        border-radius: 22px;
    }

    .reports-grid--cards,
    .reports-grid--secondary,
    .pulse-grid,
    .trend-grid {
        grid-template-columns: 1fr;
    }

    .server-list__item,
    .trend-card__head {
        display: grid;
    }

    .server-list__value {
        text-align: left;
    }

    .trend-bar {
        grid-template-columns: 1fr;
    }
}
</style>
