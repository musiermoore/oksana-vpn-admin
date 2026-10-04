<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    method: { type: String, default: 'post' },
    config: Object,
    dns_settings: { type: Array, default: () => [] },
    geodata: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    targets: { type: Object, default: () => ({}) },
});

const initial = props.config || {};
const pretty = (value) => JSON.stringify(value || {}, null, 2);
const form = useForm({
    name: initial.name || '',
    slug: initial.slug || '',
    description: initial.description || '',
    dns_settings_id: initial.dns_settings_id || '',
    geodata_id: initial.geodata_id || '',
    xray_inbound_ids: initial.xray_inbound_ids || [],
    external_subscription_config_ids: initial.external_subscription_config_ids || [],
    proxy_ids: initial.proxy_ids || [],
    xray_routing_ids: initial.xray_routing_ids || [],
    base_settings_json: pretty(initial.base_settings),
    outbound_groups_json: pretty(initial.outbound_groups || []),
    routes_json: pretty(initial.routes || []),
    is_active: initial.is_active ?? true,
});
const previewMode = ref('all');
const previewUserId = ref(props.users[0]?.id || '');
const previewContent = ref(null);
const previewing = ref(false);

const ids = (field) => (form[field] || []).map(Number);
const toggle = (field, id, checked) => {
    const current = ids(field);
    form[field] = checked ? [...new Set([...current, Number(id)])] : current.filter((value) => value !== Number(id));
};
const checked = (field, id) => ids(field).includes(Number(id));
const submit = () => {
    const request = form.transform((data) => ({ ...data, dns_settings_id: data.dns_settings_id || null, geodata_id: data.geodata_id || null }));
    props.method === 'put' ? request.put(props.submit_url) : request.post(props.submit_url);
};
const preview = async () => {
    previewing.value = true;
    const previewUrl = props.config ? `${props.submit_url}/preview` : '/xray-custom-configs/preview';
    const response = await fetch(previewUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' },
        body: JSON.stringify({
            name: form.name,
            slug: form.slug || 'preview',
            description: form.description,
            dns_settings_id: form.dns_settings_id || null,
            geodata_id: form.geodata_id || null,
            xray_inbound_ids: form.xray_inbound_ids,
            external_subscription_config_ids: form.external_subscription_config_ids,
            proxy_ids: form.proxy_ids,
            xray_routing_ids: form.xray_routing_ids,
            base_settings_json: form.base_settings_json,
            outbound_groups_json: form.outbound_groups_json,
            routes_json: form.routes_json,
            is_active: form.is_active,
            user_id: previewMode.value === 'user' ? Number(previewUserId.value) : undefined,
        }),
    });
    previewContent.value = await response.json();
    previewing.value = false;
};
</script>

<template>
    <Head title="Xray Custom Config" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ props.config ? 'Изменение' : 'Создание' }} Xray Custom Config</h1></div></div>
        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Название</span><AppInput v-model="form.name" required /></label>
            <label class="field"><span>Slug</span><AppInput v-model="form.slug" required /></label>
            <label class="field"><span>DNS settings</span><AppSelect v-model="form.dns_settings_id" :options="props.dns_settings.map((item) => ({ value: item.id, label: item.name }))" /></label>
            <label class="field"><span>Geodata</span><AppSelect v-model="form.geodata_id" :options="props.geodata.map((item) => ({ value: item.id, label: item.name }))" /></label>

            <div class="field" style="grid-column: 1 / -1;"><span>Servers / inbounds</span>
                <div v-for="server in props.targets.servers || []" :key="server.id" class="stack">
                    <strong>{{ server.name }}</strong>
                    <label v-for="inbound in server.xray_inbounds || []" :key="inbound.id" class="field-row field-row--child">
                        <input type="checkbox" :checked="checked('xray_inbound_ids', inbound.id)" @change="toggle('xray_inbound_ids', inbound.id, $event.target.checked)">
                        <span>Inbound #{{ inbound.external_id }}</span>
                    </label>
                </div>
            </div>

            <div class="field" style="grid-column: 1 / -1;"><span>External subscriptions</span>
                <label v-for="subscription in props.targets.external_subscriptions || []" :key="subscription.id" class="field-row">
                    <input v-for="config in subscription.configs || []" :key="config.id" type="checkbox" :checked="checked('external_subscription_config_ids', config.id)" @change="toggle('external_subscription_config_ids', config.id, $event.target.checked)">
                    <span>{{ subscription.name }}: {{ (subscription.configs || []).map((item) => item.name).join(', ') }}</span>
                </label>
            </div>

            <div class="field" style="grid-column: 1 / -1;"><span>Proxies</span>
                <label v-for="proxy in props.targets.proxies || []" :key="proxy.id" class="field-row">
                    <input type="checkbox" :checked="checked('proxy_ids', proxy.id)" @change="toggle('proxy_ids', proxy.id, $event.target.checked)">
                    <span>{{ proxy.server?.name || '' }} · {{ proxy.name }}</span>
                </label>
            </div>

            <div class="field" style="grid-column: 1 / -1;"><span>Xray routings</span>
                <label v-for="routing in props.targets.routings || []" :key="routing.id" class="field-row">
                    <input type="checkbox" :checked="checked('xray_routing_ids', routing.id)" @change="toggle('xray_routing_ids', routing.id, $event.target.checked)">
                    <span>{{ routing.name }}{{ routing.is_active ? '' : ' (disabled)' }}</span>
                </label>
            </div>

            <label class="field" style="grid-column: 1 / -1;"><span>Base Xray settings JSON</span><AppTextarea v-model="form.base_settings_json" rows="12" required /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>Outbound groups JSON</span><AppTextarea v-model="form.outbound_groups_json" rows="12" required /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>Routes JSON</span><AppTextarea v-model="form.routes_json" rows="12" required /></label>
            <label class="field"><span>Активно</span><AppCheckbox v-model="form.is_active" /></label>
            <label class="field"><span>Описание</span><AppTextarea v-model="form.description" rows="3" /></label>
            <div class="actions" style="grid-column: 1 / -1;"><AppButton type="submit" :disabled="form.processing">Сохранить</AppButton><AppButton variant="secondary" href="/xray-custom-configs">Назад</AppButton></div>
        </form>
    </section>

    <section class="page-card stack">
        <h2>Preview</h2>
        <label class="field-row"><input v-model="previewMode" type="radio" value="all"> All available nodes</label>
        <label class="field-row"><input v-model="previewMode" type="radio" value="user"> Specific user</label>
        <AppSelect v-if="previewMode === 'user'" v-model="previewUserId" :options="props.users.map((user) => ({ value: user.id, label: user.full_name || user.telegram_id }))" />
        <AppButton variant="secondary" type="button" :disabled="previewing" @click="preview">{{ previewing ? 'Загрузка…' : 'Preview JSON' }}</AppButton>
        <pre v-if="previewContent">{{ JSON.stringify(previewContent, null, 2) }}</pre>
    </section>
</template>
