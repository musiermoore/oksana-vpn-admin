<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    import_url: String,
    create_page_url: String,
    active_settings: Object,
    routings: {
        type: Object,
        default: () => ({ data: [], links: [], total: 0 }),
    },
});

const page = usePage();

const importForm = useForm({
    settings_json: '',
});

const isImportModalOpen = ref(Boolean(page.props.errors?.settings_json));

const openImportModal = () => {
    isImportModalOpen.value = true;
};

const closeImportModal = () => {
    isImportModalOpen.value = false;
    importForm.clearErrors();
};

const submitImport = () => importForm.post(props.import_url, {
    preserveScroll: true,
    onError: openImportModal,
    onSuccess: () => {
        importForm.reset();
        closeImportModal();
    },
});

const pretty = (value) => JSON.stringify(value ?? {}, null, 2);

const routingItems = computed(() => props.routings?.data ?? []);
const paginationLinks = computed(() => props.routings?.links ?? []);

const subscriptionsLabel = (routing) => {
    const types = routing.subscription_types ?? [];

    if (types.includes('connect') && types.includes('connect_wl')) {
        return 'connect + connect-wl';
    }

    if (types.includes('connect')) {
        return 'connect';
    }

    if (types.includes('connect_wl')) {
        return 'connect-wl';
    }

    return 'all';
};

const targetLabel = (routing) => {
    const localCount = routing.xray_inbound_ids?.length ?? 0;
    const externalCount = routing.external_subscription_config_ids?.length ?? 0;
    const proxyCount = routing.proxy_ids?.length ?? 0;

    if (localCount === 0 && externalCount === 0 && proxyCount === 0) {
        return 'nowhere';
    }

    return [
        localCount > 0 ? `${localCount} inbound` : '',
        externalCount > 0 ? `${externalCount} external` : '',
        proxyCount > 0 ? `${proxyCount} proxy` : '',
    ].filter(Boolean).join(', ');
};
</script>

<template>
    <Head title="Xray Routing" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Xray Routing</h1>
                <p>JSON routing settings for connect subscriptions.</p>
            </div>
            <div class="actions">
                <AppButton variant="secondary" type="button" @click="openImportModal">Импорт</AppButton>
                <AppButton :href="create_page_url">Создать правило</AppButton>
            </div>
        </div>
    </section>

    <section class="stack">
        <div class="page-card stack">
            <h2>Active Settings</h2>
            <div v-if="active_settings" class="stack">
                <div>
                    <strong>{{ active_settings.name }}</strong>
                    <div>{{ active_settings.imported_at || active_settings.created_at }}</div>
                </div>
                <pre>{{ pretty({
                    dns: active_settings.dns,
                    routing: active_settings.routing,
                    geodata: active_settings.geodata,
                }) }}</pre>
            </div>
            <p v-else>No active settings.</p>
        </div>

        <div class="page-card stack">
            <div class="page-header">
                <div>
                    <h2>Routing Rules</h2>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Name</th>
                        <th>Outbound</th>
                        <th>Subscriptions</th>
                        <th>Targets</th>
                        <th>Status</th>
                        <th>Rules</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="routing in routingItems" :key="routing.id">
                        <td>{{ routing.sort_order }}</td>
                        <td>{{ routing.name }}</td>
                        <td>{{ routing.outbound }}</td>
                        <td>{{ subscriptionsLabel(routing) }}</td>
                        <td>{{ targetLabel(routing) }}</td>
                        <td>{{ routing.is_active ? 'Active' : 'Disabled' }}</td>
                        <td><pre>{{ pretty(routing.rules) }}</pre></td>
                        <td>
                            <AppButton variant="secondary" :href="routing.links.edit">Изменить</AppButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!routingItems.length" class="empty-state">Правил пока нет.</div>

            <div v-if="paginationLinks.length > 3" class="actions">
                <template v-for="link in paginationLinks" :key="link.label">
                    <AppButton
                        v-if="link.url"
                        variant="secondary"
                        :class="{ 'is-active': link.active }"
                        :href="link.url"
                    >
                        <span v-html="link.label" />
                    </AppButton>
                    <span
                        v-else
                        class="pagination-pill"
                        :class="{ 'is-active': link.active }"
                    >
                        <span v-html="link.label" />
                    </span>
                </template>
            </div>
        </div>
    </section>

    <div v-if="isImportModalOpen" class="routing-modal" @click.self="closeImportModal">
        <section class="page-card stack routing-modal__card">
            <div class="page-header">
                <div>
                    <h2>Импорт настроек</h2>
                    <p>Вставьте JSON routing settings для connect subscriptions.</p>
                </div>
                <AppButton variant="secondary" type="button" @click="closeImportModal">Закрыть</AppButton>
            </div>

            <form class="stack" @submit.prevent="submitImport">
                <label class="field">
                    <span>Settings JSON</span>
                    <AppTextarea v-model="importForm.settings_json" rows="18" required />
                    <small v-if="importForm.errors.settings_json" class="field-error">{{ importForm.errors.settings_json }}</small>
                </label>

                <div class="actions">
                    <AppButton type="submit" :disabled="importForm.processing">Импортировать</AppButton>
                    <AppButton variant="secondary" type="button" @click="closeImportModal">Отмена</AppButton>
                </div>
            </form>
        </section>
    </div>
</template>

<style scoped>
.routing-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.42);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    z-index: 30;
}

.routing-modal__card {
    width: min(900px, 100%);
    max-height: calc(100vh - 48px);
    overflow: auto;
}
</style>
