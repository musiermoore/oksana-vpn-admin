<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    logs: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    timezone_stats: {
        type: Array,
        default: () => [],
    },
    top_users: {
        type: Array,
        default: () => [],
    },
    overview: {
        type: Object,
        default: () => ({
            total: 0,
            unique_users: 0,
            timezone_count: 0,
        }),
    },
    actions: {
        type: Array,
        default: () => [],
    },
    endpoints: {
        type: Array,
        default: () => [],
    },
    methods: {
        type: Array,
        default: () => [],
    },
    viewer_timezone: {
        type: String,
        default: '',
    },
});

const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';

const form = reactive({
    search: props.filters.search ?? '',
    action: props.filters.action ?? '',
    endpoint: props.filters.endpoint ?? '',
    method: props.filters.method ?? '',
    datetime_from: props.filters.datetime_from ?? '',
    datetime_to: props.filters.datetime_to ?? '',
    viewer_timezone: props.filters.viewer_timezone || props.viewer_timezone || browserTimezone,
});

const activeFilterCount = computed(() =>
    ['search', 'action', 'endpoint', 'method', 'datetime_from', 'datetime_to']
        .filter((key) => Boolean(form[key]))
        .length,
);

const applyFilters = () => {
    form.viewer_timezone = browserTimezone;

    router.get('/api-request-logs', form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    form.search = '';
    form.action = '';
    form.endpoint = '';
    form.method = '';
    form.datetime_from = '';
    form.datetime_to = '';
    applyFilters();
};

const stringifyParams = (params) => {
    if (!params) {
        return '—';
    }

    return JSON.stringify(params, null, 2);
};

const formatTimestamp = (isoString) => {
    if (!isoString) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZone: form.viewer_timezone || browserTimezone || undefined,
    }).format(new Date(isoString));
};

const logsData = computed(() => props.logs?.data ?? []);
const paginationLinks = computed(() => props.logs?.meta?.links ?? []);
</script>

<template>
    <Head title="API лог" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>API лог</h1>
                <p>Запросы по всем маршрутам из API с параметрами, пользователями и часовыми поясами.</p>
            </div>
        </div>

        <div class="stat-grid">
            <article class="stat-card stack">
                <p class="muted">Всего событий</p>
                <h3>{{ overview.total }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Уникальных пользователей</p>
                <h3>{{ overview.unique_users }}</h3>
            </article>
            <article class="stat-card stack">
                <p class="muted">Уникальных timezone запросов</p>
                <h3>{{ overview.timezone_count }}</h3>
            </article>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Топ пользователей</h2>
                <p>Помогает быстро заметить пользователей с необычно большим числом запросов.</p>
            </div>
        </div>

        <div v-if="top_users.length" class="list">
            <div v-for="item in top_users" :key="item.user_id" class="item-row">
                <div>
                    <Link v-if="item.user?.edit_url" :href="item.user.edit_url">{{ item.user.full_name }}</Link>
                    <span v-else>Пользователь #{{ item.user_id }}</span>
                    <div class="muted">{{ item.user?.telegram || 'Telegram не указан' }}</div>
                </div>
                <span class="badge">{{ item.hits }}</span>
            </div>
        </div>
        <div v-else class="empty-state">Пока нет данных по пользователям.</div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Фильтры</h2>
                <p>Активно фильтров: {{ activeFilterCount }}<template v-if="form.viewer_timezone"> · Время: {{ form.viewer_timezone }}</template></p>
            </div>

            <div class="actions">
                <button class="button" type="button" @click="applyFilters">Применить</button>
                <button class="button button--secondary" type="button" @click="resetFilters">Сбросить</button>
            </div>
        </div>

        <div class="grid grid--filters">
            <div class="field">
                <label for="search">Поиск</label>
                <input id="search" v-model="form.search" type="text" placeholder="user_id, telegram, action..." @keyup.enter="applyFilters">
            </div>

            <div class="field">
                <label for="method">Метод</label>
                <select id="method" v-model="form.method">
                    <option value="">Все</option>
                    <option v-for="method in methods" :key="method" :value="method">{{ method }}</option>
                </select>
            </div>

            <div class="field">
                <label for="action">Action</label>
                <select id="action" v-model="form.action">
                    <option value="">Все</option>
                    <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                </select>
            </div>

            <div class="field">
                <label for="endpoint">Endpoint</label>
                <select id="endpoint" v-model="form.endpoint">
                    <option value="">Все</option>
                    <option v-for="endpoint in endpoints" :key="endpoint" :value="endpoint">{{ endpoint }}</option>
                </select>
            </div>

            <div class="field">
                <label for="datetime_from">С даты и времени</label>
                <input id="datetime_from" v-model="form.datetime_from" type="datetime-local">
            </div>

            <div class="field">
                <label for="datetime_to">По дату и время</label>
                <input id="datetime_to" v-model="form.datetime_to" type="datetime-local">
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Timezone</h2>
                <p>Топ распределения по часовым поясам самих запросов. Времена ниже показаны в вашем часовом поясе.</p>
            </div>
        </div>

        <div v-if="timezone_stats.length" class="list">
            <div v-for="item in timezone_stats" :key="item.timezone" class="item-row">
                <strong>{{ item.timezone }}</strong>
                <span class="badge">{{ item.hits }}</span>
            </div>
        </div>
        <div v-else class="empty-state">Пока нет данных по часовым поясам.</div>
    </section>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Когда</th>
                    <th>Пользователь</th>
                    <th>Action</th>
                    <th>Endpoint</th>
                    <th>Timezone запроса</th>
                    <th>Params</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logsData" :key="log.id">
                    <td>
                        <strong>{{ formatTimestamp(log.created_at) }}</strong>
                        <div class="muted">HTTP {{ log.method }} · {{ log.response_status ?? '—' }}</div>
                    </td>
                    <td>
                        <Link v-if="log.user?.edit_url" :href="log.user.edit_url">{{ log.user.full_name }}</Link>
                        <span v-else>—</span>
                        <div class="muted">ID: {{ log.user?.id ?? '—' }}</div>
                    </td>
                    <td>{{ log.action }}</td>
                    <td>
                        <div>{{ log.endpoint }}</div>
                        <div class="muted">IP: {{ log.ip_address || 'не определен' }}</div>
                        <div class="muted">Forwarded: {{ log.forwarded_for || 'нет' }}</div>
                        <div class="muted">{{ log.user_agent || 'User-Agent не определен' }}</div>
                    </td>
                    <td>
                        <div>{{ log.request_timezone || 'Не указана' }}</div>
                        <div class="muted">
                            offset:
                            {{ log.request_timezone_offset ?? '—' }}
                        </div>
                    </td>
                    <td>
                        <pre class="code-block">{{ stringifyParams(log.params) }}</pre>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="!logsData.length" class="empty-state">По текущим фильтрам ничего не найдено.</div>
    </section>

    <section v-if="paginationLinks.length > 3" class="page-card">
        <div class="actions">
            <template v-for="link in paginationLinks" :key="link.label">
                <Link
                    v-if="link.url"
                    class="button button--secondary"
                    :class="{ 'is-active': link.active }"
                    :href="link.url"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="button button--ghost"
                    :class="{ 'is-active': link.active }"
                    v-html="link.label"
                />
            </template>
        </div>
    </section>
</template>
