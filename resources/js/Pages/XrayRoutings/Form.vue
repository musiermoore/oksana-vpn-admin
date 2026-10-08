<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    submitUrl: {
        type: String,
        required: true,
    },
    cancelHref: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'post',
    },
    outboundOptions: {
        type: Array,
        default: () => [],
    },
    subscriptionTypeOptions: {
        type: Array,
        default: () => [],
    },
    targetTree: {
        type: Object,
        default: () => ({ servers: [], external_subscriptions: [], proxies: [] }),
    },
    initialRouting: {
        type: Object,
        default: () => ({
            name: '',
            description: '',
            outbound: 'proxy',
            rules: {},
            sort_order: 0,
            is_active: true,
            subscription_types: ['connect', 'connect_wl'],
            xray_inbound_ids: [],
            external_subscription_config_ids: [],
            proxy_ids: [],
        }),
    },
});

const pretty = (value) => JSON.stringify(value ?? {}, null, 2);

const form = useForm({
    name: props.initialRouting?.name ?? '',
    description: props.initialRouting?.description ?? '',
    outbound: props.initialRouting?.outbound ?? 'proxy',
    rules_json: pretty(props.initialRouting?.rules),
    sort_order: props.initialRouting?.sort_order ?? 0,
    is_active: props.initialRouting?.is_active ?? true,
    connect_enabled: (props.initialRouting?.subscription_types ?? ['connect', 'connect_wl']).includes('connect'),
    connect_wl_enabled: (props.initialRouting?.subscription_types ?? ['connect', 'connect_wl']).includes('connect_wl'),
    xray_inbound_ids: props.initialRouting?.xray_inbound_ids ?? [],
    external_subscription_config_ids: props.initialRouting?.external_subscription_config_ids ?? [],
    proxy_ids: props.initialRouting?.proxy_ids ?? [],
    is_global: props.initialRouting?.is_global ?? false,
});

const selectedSubscriptionTypes = () => [
    ...(form.connect_enabled ? ['connect'] : []),
    ...(form.connect_wl_enabled ? ['connect_wl'] : []),
];

const canSave = computed(() => selectedSubscriptionTypes().length > 0 && !form.processing);

const containsId = (items, id) => items.map(Number).includes(Number(id));

const toggleId = (field, id, checked) => {
    const current = form[field].map(Number);
    const value = Number(id);

    form[field] = checked
        ? [...new Set([...current, value])]
        : current.filter((item) => item !== value);
};

const allSelected = (field, ids) => ids.length > 0 && ids.every((id) => containsId(form[field], id));

const toggleGroup = (field, ids, checked) => {
    const current = form[field].map(Number);
    const normalizedIds = ids.map(Number);

    form[field] = checked
        ? [...new Set([...current, ...normalizedIds])]
        : current.filter((id) => !normalizedIds.includes(id));
};

const toggleAll = (field, ids) => {
    const normalizedIds = ids.map(Number);
    const shouldSelect = !allSelected(field, normalizedIds);

    toggleGroup(field, normalizedIds, shouldSelect);
};

const payload = (data) => ({
    name: data.name,
    description: data.description,
    outbound: data.outbound,
    rules_json: data.rules_json,
    sort_order: data.sort_order,
    is_active: data.is_active,
    subscription_types: selectedSubscriptionTypes(),
    xray_inbound_ids: data.xray_inbound_ids,
    external_subscription_config_ids: data.external_subscription_config_ids,
    proxy_ids: data.proxy_ids,
    is_global: data.is_global,
});

const submit = () => {
    const request = form.transform((data) => payload(data));

    if (props.method === 'put') {
        request.put(props.submitUrl);
        return;
    }

    request.post(props.submitUrl);
};
</script>

<template>
    <form class="grid grid--two" @submit.prevent="submit">
        <label class="field">
            <span>Название</span>
            <AppInput v-model="form.name" required />
            <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
        </label>

        <label class="field">
            <span>Outbound</span>
            <AppSelect v-model="form.outbound" :options="outboundOptions" required />
            <small v-if="form.errors.outbound" class="field-error">{{ form.errors.outbound }}</small>
        </label>

        <label class="field">
            <span>Порядок</span>
            <AppInput v-model="form.sort_order" type="number" min="0" required />
            <small v-if="form.errors.sort_order" class="field-error">{{ form.errors.sort_order }}</small>
        </label>

        <label class="field">
            <span>Активно</span>
            <AppCheckbox v-model="form.is_active" />
            <small v-if="form.errors.is_active" class="field-error">{{ form.errors.is_active }}</small>
        </label>

        <div class="field" style="grid-column: 1 / -1;">
            <span>Применять для</span>
            <label
                v-for="option in subscriptionTypeOptions"
                :key="option.value"
                class="field-row"
            >
                <AppCheckbox
                    v-if="option.value === 'connect'"
                    v-model="form.connect_enabled"
                />
                <AppCheckbox
                    v-else-if="option.value === 'connect_wl'"
                    v-model="form.connect_wl_enabled"
                />
                <span>{{ option.label }}</span>
            </label>
            <small v-if="selectedSubscriptionTypes().length === 0" class="field-error">Выберите хотя бы один тип подписки.</small>
            <small v-if="form.errors.subscription_types" class="field-error">{{ form.errors.subscription_types }}</small>
        </div>

        <div class="field" style="grid-column: 1 / -1;">
            <div class="field-heading-row">
                <span>Локальные xray inbounds</span>
                <AppButton
                    variant="secondary"
                    size="small"
                    type="button"
                    @click="toggleAll('xray_inbound_ids', (targetTree?.servers ?? []).flatMap((server) => server.inbounds.map((inbound) => inbound.id)))"
                >
                    {{ allSelected('xray_inbound_ids', (targetTree?.servers ?? []).flatMap((server) => server.inbounds.map((inbound) => inbound.id))) ? 'Снять выделение' : 'Выбрать все' }}
                </AppButton>
            </div>
            <label class="field-row">
                <input v-model="form.is_global" type="checkbox">
                <strong>Глобальное правило для всех простых JSON-конфигураций</strong>
            </label>
            <small>Отключите эту опцию, чтобы применить правило только к выбранному серверу/inbound ниже.</small>
            <div class="stack">
                <div v-for="server in targetTree?.servers ?? []" :key="server.id" class="stack">
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
                            :checked="containsId(form.xray_inbound_ids, inbound.id)"
                            @change="toggleId('xray_inbound_ids', inbound.id, $event.target.checked)"
                        >
                        <span>{{ inbound.label }}</span>
                    </label>
                </div>
            </div>
            <small v-if="form.errors.xray_inbound_ids" class="field-error">{{ form.errors.xray_inbound_ids }}</small>
        </div>

        <div class="field" style="grid-column: 1 / -1;">
            <div class="field-heading-row">
                <span>Внешние подписки</span>
                <AppButton
                    variant="secondary"
                    size="small"
                    type="button"
                    @click="toggleAll('external_subscription_config_ids', (targetTree?.external_subscriptions ?? []).flatMap((subscription) => subscription.configs.map((config) => config.id)))"
                >
                    {{ allSelected('external_subscription_config_ids', (targetTree?.external_subscriptions ?? []).flatMap((subscription) => subscription.configs.map((config) => config.id))) ? 'Снять выделение' : 'Выбрать все' }}
                </AppButton>
            </div>
            <div class="stack">
                <div v-for="subscription in targetTree?.external_subscriptions ?? []" :key="subscription.id" class="stack">
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
                            :checked="containsId(form.external_subscription_config_ids, config.id)"
                            @change="toggleId('external_subscription_config_ids', config.id, $event.target.checked)"
                        >
                        <span>{{ config.name }} · {{ config.protocol || 'unknown' }}</span>
                    </label>
                </div>
            </div>
            <small v-if="form.errors.external_subscription_config_ids" class="field-error">
                {{ form.errors.external_subscription_config_ids }}
            </small>
        </div>

        <div class="field" style="grid-column: 1 / -1;">
            <div class="field-heading-row">
                <span>Прокси</span>
                <AppButton
                    variant="secondary"
                    size="small"
                    type="button"
                    @click="toggleAll('proxy_ids', (targetTree?.proxies ?? []).map((proxy) => proxy.id))"
                >
                    {{ allSelected('proxy_ids', (targetTree?.proxies ?? []).map((proxy) => proxy.id)) ? 'Снять выделение' : 'Выбрать все' }}
                </AppButton>
            </div>
            <div class="stack">
                <label v-for="proxy in targetTree?.proxies ?? []" :key="proxy.id" class="field-row">
                    <input
                        type="checkbox"
                        :checked="containsId(form.proxy_ids, proxy.id)"
                        @change="toggleId('proxy_ids', proxy.id, $event.target.checked)"
                    >
                    <span>{{ proxy.server_name ? `${proxy.server_name} · ` : '' }}{{ proxy.name }}</span>
                </label>
            </div>
            <small v-if="form.errors.proxy_ids" class="field-error">{{ form.errors.proxy_ids }}</small>
        </div>

        <label class="field" style="grid-column: 1 / -1;">
            <span>Rules JSON</span>
            <AppTextarea v-model="form.rules_json" rows="10" required />
            <small v-if="form.errors.rules_json" class="field-error">{{ form.errors.rules_json }}</small>
        </label>

        <label class="field" style="grid-column: 1 / -1;">
            <span>Описание</span>
            <AppTextarea v-model="form.description" rows="3" />
            <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
        </label>

        <div class="actions" style="grid-column: 1 / -1;">
            <AppButton type="submit" :disabled="!canSave">Сохранить</AppButton>
            <AppButton variant="secondary" :href="cancelHref">Назад</AppButton>
        </div>
    </form>
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

.field-heading-row {
    align-items: center;
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
</style>
