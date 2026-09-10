<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    import_url: String,
    create_url: String,
    active_settings: Object,
    routings: Array,
    outbound_options: Array,
    subscription_type_options: Array,
    target_tree: Object,
});

const form = useForm({
    settings_json: '',
});

const submit = () => form.post(props.import_url);

const pretty = (value) => JSON.stringify(value ?? {}, null, 2);

const editingRouting = ref(null);
const editorMode = ref('');

const editForm = useForm({
    name: '',
    description: '',
    outbound: 'proxy',
    rules_json: '{}',
    sort_order: 0,
    is_active: true,
    connect_enabled: true,
    connect_wl_enabled: true,
    xray_inbound_ids: [],
    external_subscription_config_ids: [],
});

const resetEditorDefaults = (overrides = {}) => {
    editForm.defaults({
        name: '',
        description: '',
        outbound: 'proxy',
        rules_json: '{}',
        sort_order: 0,
        is_active: true,
        connect_enabled: true,
        connect_wl_enabled: true,
        xray_inbound_ids: [],
        external_subscription_config_ids: [],
        ...overrides,
    });
    editForm.reset();
    editForm.clearErrors();
};

const startCreate = () => {
    editingRouting.value = null;
    editorMode.value = 'create';
    resetEditorDefaults({
        sort_order: props.routings?.length ?? 0,
    });
};

const startEdit = (routing) => {
    editingRouting.value = routing;
    editorMode.value = 'edit';
    resetEditorDefaults({
        name: routing.name ?? '',
        description: routing.description ?? '',
        outbound: routing.outbound ?? 'proxy',
        rules_json: pretty(routing.rules),
        sort_order: routing.sort_order ?? 0,
        is_active: routing.is_active ?? true,
        connect_enabled: (routing.subscription_types ?? []).includes('connect'),
        connect_wl_enabled: (routing.subscription_types ?? []).includes('connect_wl'),
        xray_inbound_ids: routing.xray_inbound_ids ?? [],
        external_subscription_config_ids: routing.external_subscription_config_ids ?? [],
    });
};

const closeEdit = () => {
    editingRouting.value = null;
    editorMode.value = '';
    editForm.clearErrors();
};

const selectedSubscriptionTypes = () => [
    ...(editForm.connect_enabled ? ['connect'] : []),
    ...(editForm.connect_wl_enabled ? ['connect_wl'] : []),
];

const canSaveEdit = computed(() => selectedSubscriptionTypes().length > 0 && !editForm.processing);

const formPayload = (data) => ({
    name: data.name,
    description: data.description,
    outbound: data.outbound,
    rules_json: data.rules_json,
    sort_order: data.sort_order,
    is_active: data.is_active,
    subscription_types: selectedSubscriptionTypes(),
    xray_inbound_ids: data.xray_inbound_ids,
    external_subscription_config_ids: data.external_subscription_config_ids,
});

const saveRouting = () => {
    const request = editForm
        .transform((data) => ({
            ...formPayload(data),
        }));

    if (editorMode.value === 'create') {
        request.post(props.create_url, {
            preserveScroll: true,
            onSuccess: closeEdit,
        });

        return;
    }

    if (editingRouting.value) {
        request.put(editingRouting.value.links.update, {
            preserveScroll: true,
            onSuccess: closeEdit,
        });
    }
};

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

const containsId = (items, id) => items.map(Number).includes(Number(id));

const toggleId = (field, id, checked) => {
    const current = editForm[field].map(Number);
    const value = Number(id);

    editForm[field] = checked
        ? [...new Set([...current, value])]
        : current.filter((item) => item !== value);
};

const allSelected = (field, ids) => ids.length > 0 && ids.every((id) => containsId(editForm[field], id));

const toggleGroup = (field, ids, checked) => {
    const current = editForm[field].map(Number);
    const normalizedIds = ids.map(Number);

    editForm[field] = checked
        ? [...new Set([...current, ...normalizedIds])]
        : current.filter((id) => !normalizedIds.includes(id));
};

const targetLabel = (routing) => {
    const localCount = routing.xray_inbound_ids?.length ?? 0;
    const externalCount = routing.external_subscription_config_ids?.length ?? 0;

    if (localCount === 0 && externalCount === 0) {
        return 'nowhere';
    }

    return [
        localCount > 0 ? `${localCount} inbound` : '',
        externalCount > 0 ? `${externalCount} external` : '',
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
        </div>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span>Settings JSON</span>
                <AppTextarea v-model="form.settings_json" rows="18" required />
                <small v-if="form.errors.settings_json" class="field-error">{{ form.errors.settings_json }}</small>
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Import</AppButton>
            </div>
        </form>
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
                <AppButton type="button" @click="startCreate">Создать правило</AppButton>
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
                    <tr v-for="routing in routings" :key="routing.id">
                        <td>{{ routing.sort_order }}</td>
                        <td>{{ routing.name }}</td>
                        <td>{{ routing.outbound }}</td>
                        <td>{{ subscriptionsLabel(routing) }}</td>
                        <td>{{ targetLabel(routing) }}</td>
                        <td>{{ routing.is_active ? 'Active' : 'Disabled' }}</td>
                        <td><pre>{{ pretty(routing.rules) }}</pre></td>
                        <td>
                            <AppButton variant="secondary" type="button" @click="startEdit(routing)">Изменить</AppButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section v-if="editorMode" class="page-card stack">
        <div class="page-header">
            <div>
                <h2>{{ editorMode === 'create' ? 'Создание правила' : 'Редактирование правила' }}</h2>
                <p>{{ editingRouting?.name }}</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="saveRouting">
            <label class="field">
                <span>Название</span>
                <AppInput v-model="editForm.name" required />
                <small v-if="editForm.errors.name" class="field-error">{{ editForm.errors.name }}</small>
            </label>

            <label class="field">
                <span>Outbound</span>
                <AppSelect v-model="editForm.outbound" :options="outbound_options" required />
                <small v-if="editForm.errors.outbound" class="field-error">{{ editForm.errors.outbound }}</small>
            </label>

            <label class="field">
                <span>Порядок</span>
                <AppInput v-model="editForm.sort_order" type="number" min="0" required />
                <small v-if="editForm.errors.sort_order" class="field-error">{{ editForm.errors.sort_order }}</small>
            </label>

            <label class="field">
                <span>Активно</span>
                <AppCheckbox v-model="editForm.is_active" />
                <small v-if="editForm.errors.is_active" class="field-error">{{ editForm.errors.is_active }}</small>
            </label>

            <div class="field" style="grid-column: 1 / -1;">
                <span>Применять для</span>
                <label class="field-row">
                    <AppCheckbox v-model="editForm.connect_enabled" />
                    <span>Стандартная подписка (connect)</span>
                </label>
                <label class="field-row">
                    <AppCheckbox v-model="editForm.connect_wl_enabled" />
                    <span>Белые списки (connect-wl)</span>
                </label>
                <small v-if="selectedSubscriptionTypes().length === 0" class="field-error">Выберите хотя бы один тип подписки.</small>
                <small v-if="editForm.errors.subscription_types" class="field-error">{{ editForm.errors.subscription_types }}</small>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <span>Локальные xray inbounds</span>
                <div class="stack">
                    <div v-for="server in target_tree?.servers ?? []" :key="server.id" class="stack">
                        <label class="field-row">
                            <input
                                type="checkbox"
                                :checked="allSelected('xray_inbound_ids', server.inbounds.map((inbound) => inbound.id))"
                                @change="toggleGroup('xray_inbound_ids', server.inbounds.map((inbound) => inbound.id), $event.target.checked)"
                            >
                            <strong>{{ server.name }}</strong>
                        </label>
                        <label v-for="inbound in server.inbounds" :key="inbound.id" class="field-row field-row--child">
                            <input
                                type="checkbox"
                                :checked="containsId(editForm.xray_inbound_ids, inbound.id)"
                                @change="toggleId('xray_inbound_ids', inbound.id, $event.target.checked)"
                            >
                            <span>{{ inbound.label }}</span>
                        </label>
                    </div>
                </div>
                <small v-if="editForm.errors.xray_inbound_ids" class="field-error">{{ editForm.errors.xray_inbound_ids }}</small>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <span>Внешние подписки</span>
                <div class="stack">
                    <div v-for="subscription in target_tree?.external_subscriptions ?? []" :key="subscription.id" class="stack">
                        <label class="field-row">
                            <input
                                type="checkbox"
                                :checked="allSelected('external_subscription_config_ids', subscription.configs.map((config) => config.id))"
                                @change="toggleGroup('external_subscription_config_ids', subscription.configs.map((config) => config.id), $event.target.checked)"
                            >
                            <strong>{{ subscription.name }}</strong>
                        </label>
                        <label v-for="config in subscription.configs" :key="config.id" class="field-row field-row--child">
                            <input
                                type="checkbox"
                                :checked="containsId(editForm.external_subscription_config_ids, config.id)"
                                @change="toggleId('external_subscription_config_ids', config.id, $event.target.checked)"
                            >
                            <span>{{ config.name }} · {{ config.protocol || 'unknown' }}</span>
                        </label>
                    </div>
                </div>
                <small v-if="editForm.errors.external_subscription_config_ids" class="field-error">
                    {{ editForm.errors.external_subscription_config_ids }}
                </small>
            </div>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Rules JSON</span>
                <AppTextarea v-model="editForm.rules_json" rows="10" required />
                <small v-if="editForm.errors.rules_json" class="field-error">{{ editForm.errors.rules_json }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="editForm.description" rows="3" />
                <small v-if="editForm.errors.description" class="field-error">{{ editForm.errors.description }}</small>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="!canSaveEdit">Сохранить</AppButton>
                <AppButton variant="secondary" type="button" @click="closeEdit">Отмена</AppButton>
            </div>
        </form>
    </section>
</template>

<style scoped>
.field-row {
    align-items: center;
    display: flex;
    gap: 0.5rem;
}

.field-row--child {
    margin-left: 1.5rem;
}
</style>
