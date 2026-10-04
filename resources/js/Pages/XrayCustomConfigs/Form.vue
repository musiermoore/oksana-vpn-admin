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
    create_dns_url: String,
    create_geodata_url: String,
});

const initial = props.config || {};
const initialBase = initial.base_settings || {};
const form = useForm({
    name: initial.name || '', slug: initial.slug || '', description: initial.description || '',
    dns_settings_id: initial.dns_settings_id || '', geodata_id: initial.geodata_id || '',
    xray_inbound_ids: initial.xray_inbound_ids || [], external_subscription_config_ids: initial.external_subscription_config_ids || [],
    proxy_ids: initial.proxy_ids || [], xray_routing_ids: initial.xray_routing_ids || [], is_active: initial.is_active ?? true,
});
const settings = ref({ domainStrategy: initialBase.routing?.domainStrategy || 'AsIs', loglevel: initialBase.log?.loglevel || 'warning', extra: initialBase });
const groups = ref((initial.outbound_groups || []).map((group) => ({ ...group, xray_inbound_ids: group.xray_inbound_ids || [], external_subscription_config_ids: group.external_subscription_config_ids || [], proxy_ids: group.proxy_ids || [] })));
const routes = ref((initial.routes || []).map((route) => {
    const rules = route.rules || {};
    const matchType = Object.keys(rules).find((key) => ['domain', 'ip', 'port', 'network'].includes(key)) || 'domain';
    return { ...route, match_type: matchType, match_values: Array.isArray(rules[matchType]) ? rules[matchType].join(', ').replaceAll('domain:', '') : String(rules[matchType] || ''), target_type: route.target_type || 'balancer', target_tag: route.target_tag || '' };
}));
const dnsOptions = ref([...props.dns_settings]);
const geodataOptions = ref([...props.geodata]);
const previewMode = ref('admin');
const previewUserId = ref(props.users[0]?.id || '');
const previewContent = ref(null);
const previewing = ref(false);
const modal = ref(null);
const modalForm = ref({});
const modalError = ref('');

const strategyOptions = [
    { value: 'roundRobin', label: 'По очереди (roundRobin)' }, { value: 'leastPing', label: 'Минимальный пинг (leastPing)' },
    { value: 'leastLoad', label: 'Минимальная нагрузка (leastLoad)' }, { value: 'random', label: 'Случайный (random)' },
];
const targetTypeOptions = [{ value: 'balancer', label: 'Балансировщик' }, { value: 'direct', label: 'Напрямую' }, { value: 'block', label: 'Заблокировать' }];
const matchTypeOptions = [{ value: 'domain', label: 'Домены' }, { value: 'ip', label: 'IP-адреса' }, { value: 'port', label: 'Порты' }, { value: 'network', label: 'Сеть' }];
const groupOptions = computed(() => groups.value.map((group) => ({ value: group.tag, label: group.name || group.tag })));

const ids = (field) => (form[field] || []).map(Number);
const checked = (field, id) => ids(field).includes(Number(id));
const toggle = (field, id, value) => { const current = ids(field); form[field] = value ? [...new Set([...current, Number(id)])] : current.filter((item) => item !== Number(id)); };
const groupChecked = (group, field, id) => (group[field] || []).map(Number).includes(Number(id));
const toggleGroup = (group, field, id, value) => { const current = (group[field] || []).map(Number); group[field] = value ? [...new Set([...current, Number(id)])] : current.filter((item) => item !== Number(id)); };
const externalIds = (subscription) => (subscription.configs || []).map((item) => Number(item.id));
const subscriptionChecked = (subscription) => externalIds(subscription).length > 0 && externalIds(subscription).every((id) => checked('external_subscription_config_ids', id));
const toggleSubscription = (subscription, value) => externalIds(subscription).forEach((id) => toggle('external_subscription_config_ids', id, value));
const addGroup = () => groups.value.push({ name: `Группа ${groups.value.length + 1}`, tag: `group-${groups.value.length + 1}`, strategy: 'roundRobin', fallback_group_tag: '', xray_inbound_ids: [], external_subscription_config_ids: [], proxy_ids: [], is_active: true });
const removeGroup = (index) => groups.value.splice(index, 1);
const addRoute = () => routes.value.push({ name: `Маршрут ${routes.value.length + 1}`, match_type: 'domain', match_values: '', target_type: 'balancer', target_tag: groups.value[0]?.tag || '', is_active: true });
const removeRoute = (index) => routes.value.splice(index, 1);

const buildBaseSettings = () => ({ ...settings.value.extra, log: { ...(settings.value.extra.log || {}), loglevel: settings.value.loglevel }, routing: { ...(settings.value.extra.routing || {}), domainStrategy: settings.value.domainStrategy } });
const buildGroups = () => groups.value.map((group, index) => ({ ...group, sort_order: index }));
const buildRoutes = () => routes.value.map((route, index) => {
    const values = String(route.match_values || '').split(',').map((value) => value.trim()).filter(Boolean);
    const rules = route.match_type === 'domain' ? { domain: values.map((value) => value.includes(':') ? value : `domain:${value}`) } : { [route.match_type]: values };
    return { ...route, rules, sort_order: index };
});
const requestPayload = () => ({ base_settings_json: JSON.stringify(buildBaseSettings()), outbound_groups_json: JSON.stringify(buildGroups()), routes_json: JSON.stringify(buildRoutes()) });
const submit = () => { const request = form.transform((data) => ({ ...data, ...requestPayload(), dns_settings_id: data.dns_settings_id || null, geodata_id: data.geodata_id || null })); props.method === 'put' ? request.put(props.submit_url) : request.post(props.submit_url); };
const preview = async () => {
    previewing.value = true;
    const url = props.config ? `${props.submit_url}/preview` : '/xray-custom-configs/preview';
    const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' }, body: JSON.stringify({ ...form.data(), ...requestPayload(), preview_mode: previewMode.value, user_id: previewMode.value === 'user' ? Number(previewUserId.value) : undefined }) });
    previewContent.value = await response.json(); previewing.value = false;
};
const openResourceModal = (type) => { modal.value = type; modalError.value = ''; modalForm.value = type === 'dns' ? { name: '', description: '', servers: '', query_strategy: 'UseIPv4', enable_parallel_query: false } : { name: '', description: '', geoip_url: '', geosite_url: '' }; };
const closeResourceModal = () => { modal.value = null; };
const saveResource = async () => {
    const dns = modal.value === 'dns';
    const response = await fetch(dns ? props.create_dns_url : props.create_geodata_url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' }, body: JSON.stringify({ ...modalForm.value, ...(dns ? { servers: modalForm.value.servers.split(',').map((item) => item.trim()).filter(Boolean) } : {}) }) });
    const payload = await response.json();
    if (!response.ok) { modalError.value = payload.message || 'Не удалось сохранить настройку.'; return; }
    if (dns) { dnsOptions.value.unshift(payload.resource); form.dns_settings_id = payload.resource.id; } else { geodataOptions.value.unshift(payload.resource); form.geodata_id = payload.resource.id; }
    closeResourceModal();
};
</script>

<template>
    <Head title="Пользовательская конфигурация Xray" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ props.config ? 'Редактирование' : 'Создание' }} конфигурации Xray</h1></div></div>
        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Название</span><AppInput v-model="form.name" required /></label><label class="field"><span>Slug</span><AppInput v-model="form.slug" required /></label>
            <div class="field"><span>Настройки DNS</span><div class="field-row"><AppSelect v-model="form.dns_settings_id" :options="dnsOptions.map((item) => ({ value: item.id, label: item.name }))" /><AppButton variant="secondary" type="button" @click="openResourceModal('dns')">Добавить</AppButton></div></div>
            <div class="field"><span>Геоданные</span><div class="field-row"><AppSelect v-model="form.geodata_id" :options="geodataOptions.map((item) => ({ value: item.id, label: item.name }))" /><AppButton variant="secondary" type="button" @click="openResourceModal('geodata')">Добавить</AppButton></div></div>
            <div class="field" style="grid-column: 1 / -1;"><span>Базовые настройки Xray</span><div class="grid grid--two"><label class="field"><span>Стратегия доменов</span><AppSelect v-model="settings.domainStrategy" :options="[{ value: 'AsIs', label: 'Как указано' }, { value: 'IPIfNonMatch', label: 'IP, если домен не найден' }, { value: 'IPOnDemand', label: 'Всегда определять IP' }]" /></label><label class="field"><span>Уровень логирования</span><AppSelect v-model="settings.loglevel" :options="[{ value: 'none', label: 'Нет' }, { value: 'error', label: 'Ошибки' }, { value: 'warning', label: 'Предупреждения' }, { value: 'info', label: 'Информация' }, { value: 'debug', label: 'Отладка' }]" /></label></div></div>
            <div class="field" style="grid-column: 1 / -1;"><span>Серверы и входящие подключения</span><div v-for="server in props.targets.servers || []" :key="server.id" class="stack"><strong>{{ server.name }}</strong><label v-for="inbound in server.xray_inbounds || []" :key="inbound.id" class="field-row field-row--child"><input type="checkbox" :checked="checked('xray_inbound_ids', inbound.id)" @change="toggle('xray_inbound_ids', inbound.id, $event.target.checked)"><span>Inbound #{{ inbound.external_id }}</span></label></div></div>
            <div class="field" style="grid-column: 1 / -1;"><span>Внешние подписки</span><div v-for="subscription in props.targets.external_subscriptions || []" :key="subscription.id" class="stack"><label class="field-row"><input type="checkbox" :checked="subscriptionChecked(subscription)" @change="toggleSubscription(subscription, $event.target.checked)"><strong>{{ subscription.name }}</strong></label><label v-for="config in subscription.configs || []" :key="config.id" class="field-row field-row--child"><input type="checkbox" :checked="checked('external_subscription_config_ids', config.id)" @change="toggle('external_subscription_config_ids', config.id, $event.target.checked)"><span>{{ config.name }}</span></label></div></div>
            <div class="field" style="grid-column: 1 / -1;"><span>Прокси</span><label v-for="proxy in props.targets.proxies || []" :key="proxy.id" class="field-row"><input type="checkbox" :checked="checked('proxy_ids', proxy.id)" @change="toggle('proxy_ids', proxy.id, $event.target.checked)"><span>{{ proxy.server?.name || '' }} · {{ proxy.name }}</span></label></div>
            <div class="field" style="grid-column: 1 / -1;"><span>Готовые правила маршрутизации</span><label v-for="routing in props.targets.routings || []" :key="routing.id" class="field-row"><input type="checkbox" :checked="checked('xray_routing_ids', routing.id)" @change="toggle('xray_routing_ids', routing.id, $event.target.checked)"><span>{{ routing.name }}{{ routing.is_active ? '' : ' (отключено)' }}</span></label></div>
            <div class="field" style="grid-column: 1 / -1;"><div class="page-header"><span>Группы исходящих подключений</span><AppButton variant="secondary" type="button" @click="addGroup">Добавить группу</AppButton></div><div v-for="(group, index) in groups" :key="index" class="page-card stack"><div class="grid grid--two"><label class="field"><span>Название группы</span><AppInput v-model="group.name" /></label><label class="field"><span>Тег</span><AppInput v-model="group.tag" /></label><label class="field"><span>Балансировка</span><AppSelect v-model="group.strategy" :options="strategyOptions" /></label><label class="field"><span>Резервная группа</span><AppSelect v-model="group.fallback_group_tag" :options="[{ value: '', label: 'Нет' }, ...groupOptions.filter((item) => item.value !== group.tag)]" /></label></div><strong>Серверы и подключения</strong><div v-for="server in props.targets.servers || []" :key="`g${index}s${server.id}`"><span>{{ server.name }}</span><label v-for="inbound in server.xray_inbounds || []" :key="`g${index}i${inbound.id}`" class="field-row field-row--child"><input type="checkbox" :checked="groupChecked(group, 'xray_inbound_ids', inbound.id)" @change="toggleGroup(group, 'xray_inbound_ids', inbound.id, $event.target.checked)"><span>Inbound #{{ inbound.external_id }}</span></label></div><strong>Внешние подписки</strong><div v-for="subscription in props.targets.external_subscriptions || []" :key="`g${index}e${subscription.id}`"><span>{{ subscription.name }}</span><label v-for="config in subscription.configs || []" :key="`g${index}c${config.id}`" class="field-row field-row--child"><input type="checkbox" :checked="groupChecked(group, 'external_subscription_config_ids', config.id)" @change="toggleGroup(group, 'external_subscription_config_ids', config.id, $event.target.checked)"><span>{{ config.name }}</span></label></div><strong>Прокси</strong><label v-for="proxy in props.targets.proxies || []" :key="`g${index}p${proxy.id}`" class="field-row"><input type="checkbox" :checked="groupChecked(group, 'proxy_ids', proxy.id)" @change="toggleGroup(group, 'proxy_ids', proxy.id, $event.target.checked)"><span>{{ proxy.server?.name || '' }} · {{ proxy.name }}</span></label><AppButton variant="secondary" type="button" @click="removeGroup(index)">Удалить группу</AppButton></div></div>
            <div class="field" style="grid-column: 1 / -1;"><div class="page-header"><span>Маршруты сайтов</span><AppButton variant="secondary" type="button" @click="addRoute">Добавить маршрут</AppButton></div><div v-for="(route, index) in routes" :key="index" class="page-card"><div class="grid grid--two"><label class="field"><span>Название</span><AppInput v-model="route.name" /></label><label class="field"><span>Тип совпадения</span><AppSelect v-model="route.match_type" :options="matchTypeOptions" /></label><label class="field"><span>Значения через запятую</span><AppInput v-model="route.match_values" placeholder="youtube.com, chatgpt.com" /></label><label class="field"><span>Назначение</span><AppSelect v-model="route.target_type" :options="targetTypeOptions" /></label><label v-if="route.target_type === 'balancer'" class="field"><span>Группа</span><AppSelect v-model="route.target_tag" :options="groupOptions" /></label></div><AppButton variant="secondary" type="button" @click="removeRoute(index)">Удалить маршрут</AppButton></div></div>
            <label class="field" style="grid-column: 1 / -1;"><span>Описание</span><AppTextarea v-model="form.description" rows="3" /></label><label class="field" style="grid-column: 1 / -1;"><span>Активна</span><AppCheckbox v-model="form.is_active" /></label><div class="actions" style="grid-column: 1 / -1;"><AppButton type="submit" :disabled="form.processing">Сохранить</AppButton><AppButton variant="secondary" href="/xray-custom-configs">Назад</AppButton></div>
        </form>
    </section>
    <section class="page-card stack"><h2>Предпросмотр</h2><label class="field-row"><input v-model="previewMode" type="radio" value="admin"> От имени администратора</label><label class="field-row"><input v-model="previewMode" type="radio" value="user"> От имени пользователя</label><AppSelect v-if="previewMode === 'user'" v-model="previewUserId" :options="props.users.map((user) => ({ value: user.id, label: user.full_name || user.telegram_id }))" /><AppButton variant="secondary" type="button" :disabled="previewing" @click="preview">{{ previewing ? 'Загрузка…' : 'Предпросмотр JSON' }}</AppButton><pre v-if="previewContent">{{ JSON.stringify(previewContent, null, 2) }}</pre></section>
    <div v-if="modal" class="resource-modal" @click.self="closeResourceModal"><section class="page-card stack resource-modal__card"><h2>{{ modal === 'dns' ? 'Новые настройки DNS' : 'Новые геоданные' }}</h2><label class="field"><span>Название</span><AppInput v-model="modalForm.name" /></label><label class="field"><span>Описание</span><AppTextarea v-model="modalForm.description" rows="2" /></label><template v-if="modal === 'dns'"><label class="field"><span>DNS-серверы через запятую</span><AppInput v-model="modalForm.servers" placeholder="8.8.8.8, 1.1.1.1" /></label><label class="field"><span>Стратегия запросов</span><AppSelect v-model="modalForm.query_strategy" :options="[{ value: 'UseIPv4', label: 'Только IPv4' }, { value: 'UseIPv6', label: 'Только IPv6' }, { value: 'UseIP', label: 'IPv4 и IPv6' }, { value: 'AsIs', label: 'Без изменения' }]" /></label><label class="field-row"><input v-model="modalForm.enable_parallel_query" type="checkbox"> Параллельные DNS-запросы</label></template><template v-else><label class="field"><span>URL geoip.dat</span><AppInput v-model="modalForm.geoip_url" /></label><label class="field"><span>URL geosite.dat</span><AppInput v-model="modalForm.geosite_url" /></label></template><p v-if="modalError" class="form-error">{{ modalError }}</p><div class="actions"><AppButton type="button" @click="saveResource">Сохранить</AppButton><AppButton variant="secondary" type="button" @click="closeResourceModal">Отмена</AppButton></div></section></div>
</template>

<style scoped>
.resource-modal { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / 45%); }
.resource-modal__card { width: min(36rem, 100%); max-height: 90vh; overflow: auto; }
</style>
