<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
const slugify = (value) => String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
const form = useForm({
    name: initial.name || '', slug: initial.slug || slugify(initial.name), description: initial.description || '',
    dns_settings_id: initial.dns_settings_id || '', geodata_id: initial.geodata_id || '',
    xray_inbound_ids: initial.xray_inbound_ids || [], external_subscription_config_ids: initial.external_subscription_config_ids || [], external_subscription_ids: initial.external_subscription_ids || [],
    proxy_ids: initial.proxy_ids || [], xray_routing_ids: initial.xray_routing_ids || [], is_active: initial.is_active ?? true, sort_order: initial.sort_order ?? 0,
});
const slugManuallyEdited = ref(Boolean(initial.slug && initial.slug !== slugify(initial.name)));
watch(() => form.name, (name) => {
    if (!slugManuallyEdited.value) {
        form.slug = slugify(name);
    }
});
const settings = ref({ domainStrategy: initialBase.routing?.domainStrategy || 'AsIs', loglevel: initialBase.log?.loglevel || 'warning' });
const baseSettingsJson = ref(JSON.stringify(initialBase, null, 2));
const baseSettingsError = ref('');
const groupSlug = (value) => `group-${slugify(value) || 'new-group'}`;
const groups = ref((initial.outbound_groups || []).map((group) => ({ ...group, xray_inbound_ids: group.xray_inbound_ids || [], external_subscription_config_ids: group.external_subscription_config_ids || [], external_subscription_ids: group.external_subscription_ids || [], proxy_ids: group.proxy_ids || [], _tagManuallyEdited: Boolean(group.tag && group.tag !== groupSlug(group.name)) })));
const expandedGroups = ref(new Set(groups.value.length ? [0] : []));
const groupMemberPickerIndex = ref(null);
const groupMemberSearch = ref('');
const groupMemberType = ref('all');
const groupMemberCountry = ref('');
const groupMemberExpandedServers = ref(new Set());
const groupMemberExpandedSubscriptions = ref(new Set());
const groupMemberSnapshot = ref(null);
const splitRouteValues = (value) => String(value || '').split(/[\n,]/).map((item) => item.trim()).filter(Boolean);
const routes = ref((initial.routes || []).map((route) => {
    const rules = route.rules || {};
    const matchType = Object.keys(rules).find((key) => ['domain', 'ip', 'port', 'network', 'protocol'].includes(key)) || 'domain';
    const values = Array.isArray(rules[matchType]) ? rules[matchType].map((value) => String(value).replace(/^domain:/, '')) : splitRouteValues(rules[matchType]);
    return { ...route, match_type: matchType, match_values: values.join(', '), match_values_list: values, value_input: '', target_type: route.target_type || 'balancer', target_tag: route.target_tag || '', _menuOpen: false };
}));
const dnsOptions = ref([...props.dns_settings]);
const geodataOptions = ref([...props.geodata]);
const previewMode = ref('admin');
const previewUserId = ref(props.users[0]?.id || '');
const previewContent = ref(null);
const previewing = ref(false);
const previewExpanded = ref(true);
const previewCopied = ref(false);
const previewRefreshScheduled = ref(false);
let previewRefreshTimer = null;
const modal = ref(null);
const modalForm = ref({});
const modalError = ref('');
const hasUnsavedChanges = ref(false);
const expandedRoutes = ref(new Set(routes.value.length ? [0] : []));
const draggedRouteIndex = ref(null);
const localSearch = ref('');
const localCountry = ref('');
const proxySearch = ref('');
const proxyCountry = ref('');
const proxyStatus = ref('');
const subscriptionSearch = ref('');
const expandedServers = ref(new Set());
const expandedSubscriptions = ref(new Set());

const strategyOptions = [
    { value: 'roundRobin', label: 'Round robin' }, { value: 'leastPing', label: 'Least ping' },
    { value: 'leastLoad', label: 'Least load' }, { value: 'random', label: 'Random' },
];
const domainStrategyOptions = [
    { value: 'AsIs', label: 'AsIs — use domains as provided' },
    { value: 'IPIfNonMatch', label: 'IPIfNonMatch — resolve when no routing rule matches' },
    { value: 'IPOnDemand', label: 'IPOnDemand — resolve when required' },
];
const logLevelOptions = [
    { value: 'debug', label: 'Debug' },
    { value: 'info', label: 'Info' },
    { value: 'warning', label: 'Warning' },
    { value: 'error', label: 'Error' },
    { value: 'none', label: 'None' },
];
const targetTypeOptions = [{ value: 'balancer', label: 'Outbound group / Balancer' }, { value: 'direct', label: 'Direct' }, { value: 'block', label: 'Block' }];
const matchTypeOptions = [{ value: 'domain', label: 'Domains' }, { value: 'ip', label: 'IP addresses / CIDRs' }, { value: 'port', label: 'Ports' }, { value: 'network', label: 'Networks' }, { value: 'protocol', label: 'Protocols' }];
const routeMatchLabels = { domain: 'Domains', ip: 'IP addresses / CIDRs', port: 'Ports', network: 'Networks', protocol: 'Protocols' };
const groupOptions = computed(() => groups.value.map((group) => ({ value: group.tag, label: group.name || group.tag })));
const strategyLabel = (value) => strategyOptions.find((option) => option.value === value)?.label || value;

const ids = (field) => (form[field] || []).map(Number);
const checked = (field, id) => ids(field).includes(Number(id));
const toggle = (field, id, value) => { const current = ids(field); form[field] = value ? [...new Set([...current, Number(id)])] : current.filter((item) => item !== Number(id)); };
const normalized = (value) => String(value || '').toLocaleLowerCase();
const countryFlag = (value) => ({ Finland: '🇫🇮', Germany: '🇩🇪', Netherlands: '🇳🇱', Sweden: '🇸🇪', France: '🇫🇷', Poland: '🇵🇱', UnitedKingdom: '🇬🇧', 'United Kingdom': '🇬🇧' }[String(value)] || '');
const serverMatches = (server) => {
    const query = normalized(localSearch.value).trim();
    const countryMatches = !localCountry.value || server.name === localCountry.value;
    if (!countryMatches) return false;
    if (!query) return true;
    return normalized(server.name).includes(query) || (server.xray_inbounds || []).some((inbound) => normalized(`inbound #${inbound.external_id}`).includes(query));
};
const visibleServers = computed(() => (props.targets.servers || []).filter(serverMatches));
const visibleInbounds = (server) => {
    const query = normalized(localSearch.value).trim();
    if (!query || normalized(server.name).includes(query)) return server.xray_inbounds || [];
    return (server.xray_inbounds || []).filter((inbound) => normalized(`inbound #${inbound.external_id}`).includes(query));
};
const countries = computed(() => [...new Set((props.targets.servers || []).map((server) => server.name).filter(Boolean))]);
const serverSelectedCount = (server) => (server.xray_inbounds || []).filter((inbound) => checked('xray_inbound_ids', inbound.id)).length;
const serverState = (server) => {
    const total = (server.xray_inbounds || []).length;
    const selected = serverSelectedCount(server);
    return { total, selected, checked: total > 0 && selected === total, indeterminate: selected > 0 && selected < total };
};
const toggleServer = (server, value) => (server.xray_inbounds || []).forEach((inbound) => toggle('xray_inbound_ids', inbound.id, value));
const selectVisibleInbounds = (value) => visibleServers.value.flatMap((server) => visibleInbounds(server)).forEach((inbound) => toggle('xray_inbound_ids', inbound.id, value));
const isServerExpanded = (id) => expandedServers.value.has(Number(id));
const toggleServerExpanded = (id) => {
    const next = new Set(expandedServers.value);
    next.has(Number(id)) ? next.delete(Number(id)) : next.add(Number(id));
    expandedServers.value = next;
};
const subscriptionMatches = (subscription) => {
    const query = normalized(subscriptionSearch.value).trim();
    if (!query) return true;
    return normalized(subscription.name).includes(query) || (subscription.configs || []).some((config) => normalized(config.name).includes(query));
};
const visibleSubscriptions = computed(() => (props.targets.external_subscriptions || []).filter(subscriptionMatches));
const visibleConfigs = (subscription) => {
    const query = normalized(subscriptionSearch.value).trim();
    if (!query || normalized(subscription.name).includes(query)) return subscription.configs || [];
    return (subscription.configs || []).filter((config) => normalized(config.name).includes(query));
};
const subscriptionSelectedCount = (subscription) => checked('external_subscription_ids', subscription.id) ? subscription.configs.length : 0;
const subscriptionState = (subscription) => {
    const total = (subscription.configs || []).length;
    const selected = subscriptionSelectedCount(subscription);
    return { total, selected, checked: total > 0 && selected === total, indeterminate: selected > 0 && selected < total };
};
const selectVisibleSubscriptions = (value) => visibleSubscriptions.value.forEach((subscription) => toggle('external_subscription_ids', subscription.id, value));
const isSubscriptionExpanded = (id) => expandedSubscriptions.value.has(Number(id));
const toggleSubscriptionExpanded = (id) => {
    const next = new Set(expandedSubscriptions.value);
    next.has(Number(id)) ? next.delete(Number(id)) : next.add(Number(id));
    expandedSubscriptions.value = next;
};
const proxyMatches = (proxy) => {
    const query = normalized(proxySearch.value).trim();
    const countryMatches = !proxyCountry.value || proxy.server?.name === proxyCountry.value;
    const statusMatches = !proxyStatus.value || (proxyStatus.value === 'available' ? proxy.is_ready : !proxy.is_ready);
    if (!countryMatches || !statusMatches) return false;
    return !query || normalized(`${proxy.server?.name || ''} ${proxy.name}`).includes(query);
};
const visibleProxies = computed(() => (props.targets.proxies || []).filter(proxyMatches));
const proxyCountries = computed(() => [...new Set((props.targets.proxies || []).map((proxy) => proxy.server?.name).filter(Boolean))]);
const selectVisibleProxies = (value) => visibleProxies.value.forEach((proxy) => toggle('proxy_ids', proxy.id, value));
const totalSourcesSelected = computed(() => ids('xray_inbound_ids').length + ids('external_subscription_ids').length + ids('proxy_ids').length);
const localSelectedCount = computed(() => ids('xray_inbound_ids').length);
const externalSelectedCount = computed(() => ids('external_subscription_ids').length);
const proxySelectedCount = computed(() => ids('proxy_ids').length);
const groupChecked = (group, field, id) => (group[field] || []).map(Number).includes(Number(id));
const toggleGroup = (group, field, id, value) => { const current = (group[field] || []).map(Number); group[field] = value ? [...new Set([...current, Number(id)])] : current.filter((item) => item !== Number(id)); };
const externalIds = (subscription) => (subscription.configs || []).map((item) => Number(item.id));
const subscriptionChecked = (subscription) => checked('external_subscription_ids', subscription.id);
const toggleSubscription = (subscription, value) => toggle('external_subscription_ids', subscription.id, value);
const availableServers = computed(() => (props.targets.servers || []).map((server) => ({ ...server, xray_inbounds: (server.xray_inbounds || []).filter((inbound) => checked('xray_inbound_ids', inbound.id)) })).filter((server) => server.xray_inbounds.length));
const availableSubscriptions = computed(() => (props.targets.external_subscriptions || []).filter((subscription) => checked('external_subscription_ids', subscription.id)));
const availableProxies = computed(() => (props.targets.proxies || []).filter((proxy) => checked('proxy_ids', proxy.id)));
const groupMemberCount = (group) => idsForGroup(group).length;
const idsForGroup = (group) => [...(group.xray_inbound_ids || []), ...(group.external_subscription_ids || []), ...(group.proxy_ids || [])].map(Number);
const groupSelectedInbounds = (group, server) => (server.xray_inbounds || []).filter((inbound) => groupChecked(group, 'xray_inbound_ids', inbound.id));
const groupSelectedConfigs = (group, subscription) => groupChecked(group, 'external_subscription_ids', subscription.id) ? (subscription.configs || []) : [];
const groupSelectedProxies = (group) => availableProxies.value.filter((proxy) => groupChecked(group, 'proxy_ids', proxy.id));
const groupInvalidMemberCount = (group) => idsForGroup(group).filter((id) => ![
    ...availableServers.value.flatMap((server) => server.xray_inbounds.map((inbound) => Number(inbound.id))),
    ...availableSubscriptions.value.flatMap((subscription) => subscription.configs.map((config) => Number(config.id))),
    ...availableProxies.value.map((proxy) => Number(proxy.id)),
].includes(id)).length;
const isGroupExpanded = (index) => expandedGroups.value.has(index);
const toggleGroupExpanded = (index) => {
    const next = new Set(expandedGroups.value);
    next.has(index) ? next.delete(index) : next.add(index);
    expandedGroups.value = next;
};
const onGroupNameInput = (group) => { if (!group._tagManuallyEdited) group.tag = groupSlug(group.name); };
const fallbackCreatesCycle = (group, candidateTag) => {
    let tag = candidateTag;
    const visited = new Set();
    while (tag) {
        if (tag === group.tag) return true;
        if (visited.has(tag)) return true;
        visited.add(tag);
        tag = groups.value.find((item) => item.tag === tag)?.fallback_group_tag || '';
    }
    return false;
};
const fallbackOptions = (group) => [{ value: '', label: 'No fallback' }, ...groupOptions.value.filter((item) => item.value !== group.tag && !fallbackCreatesCycle(group, item.value))];
const fallbackChain = (group) => {
    const chain = [group.name || group.tag];
    let nextTag = group.fallback_group_tag;
    const visited = new Set();
    while (nextTag && !visited.has(nextTag)) {
        visited.add(nextTag);
        const next = groups.value.find((item) => item.tag === nextTag);
        if (!next) break;
        chain.push(next.name || next.tag);
        nextTag = next.fallback_group_tag;
    }
    return chain;
};
const addGroup = () => { groups.value.push({ name: `Group ${groups.value.length + 1}`, tag: groupSlug(`Group ${groups.value.length + 1}`), strategy: 'roundRobin', fallback_group_tag: '', xray_inbound_ids: [], external_subscription_config_ids: [], external_subscription_ids: [], proxy_ids: [], is_active: true, _tagManuallyEdited: false }); expandedGroups.value = new Set([...expandedGroups.value, groups.value.length - 1]); };
const duplicateGroup = (group, index) => { const copy = JSON.parse(JSON.stringify(group)); copy.name = `${group.name || 'Group'} copy`; copy.tag = groupSlug(copy.name); copy._tagManuallyEdited = false; copy.fallback_group_tag = ''; groups.value.splice(index + 1, 0, copy); expandedGroups.value = new Set([...expandedGroups.value, index + 1]); };
const removeGroup = (group, index) => {
    const dependentRoutes = routes.value.some((route) => route.target_tag === group.tag);
    const dependentGroups = groups.value.some((item, itemIndex) => itemIndex !== index && item.fallback_group_tag === group.tag);
    const dependencyMessage = dependentRoutes || dependentGroups ? ' This group is referenced by another group or route; those references will be cleared.' : '';
    if (!window.confirm(`Delete outbound group “${group.name || group.tag}”?${dependencyMessage}`)) return;
    routes.value.forEach((route) => { if (route.target_tag === group.tag) route.target_tag = ''; });
    groups.value.forEach((item) => { if (item.fallback_group_tag === group.tag) item.fallback_group_tag = ''; });
    groups.value.splice(index, 1);
};
const openGroupMemberPicker = (index) => { groupMemberPickerIndex.value = index; groupMemberSnapshot.value = JSON.parse(JSON.stringify({ xray_inbound_ids: groups.value[index].xray_inbound_ids, external_subscription_config_ids: groups.value[index].external_subscription_config_ids, external_subscription_ids: groups.value[index].external_subscription_ids, proxy_ids: groups.value[index].proxy_ids })); groupMemberSearch.value = ''; groupMemberType.value = 'all'; groupMemberCountry.value = ''; };
const closeGroupMemberPicker = (save = false) => { if (!save && activeGroup.value && groupMemberSnapshot.value) Object.assign(activeGroup.value, groupMemberSnapshot.value); groupMemberSnapshot.value = null; groupMemberPickerIndex.value = null; };
const activeGroup = computed(() => groupMemberPickerIndex.value === null ? null : groups.value[groupMemberPickerIndex.value]);
const memberMatches = (value) => normalized(value).includes(normalized(groupMemberSearch.value).trim());
const memberServerMatches = (server) => (!groupMemberCountry.value || server.name === groupMemberCountry.value) && (memberMatches(server.name) || server.xray_inbounds.some((inbound) => memberMatches(`inbound #${inbound.external_id}`)));
const memberVisibleServers = computed(() => !['subscription', 'proxy'].includes(groupMemberType.value) && availableServers.value.filter(memberServerMatches));
const memberVisibleSubscriptions = computed(() => groupMemberType.value !== 'local' && groupMemberType.value !== 'proxy' && availableSubscriptions.value.filter((subscription) => memberMatches(subscription.name) || subscription.configs.some((config) => memberMatches(config.name))));
const memberVisibleProxies = computed(() => groupMemberType.value !== 'local' && groupMemberType.value !== 'subscription' && availableProxies.value.filter((proxy) => (!groupMemberCountry.value || proxy.server?.name === groupMemberCountry.value) && memberMatches(`${proxy.server?.name || ''} ${proxy.name}`)));
const memberVisibleInbounds = (server) => memberMatches(server.name) ? server.xray_inbounds : server.xray_inbounds.filter((inbound) => memberMatches(`inbound #${inbound.external_id}`));
const memberVisibleConfigs = (subscription) => memberMatches(subscription.name) ? subscription.configs : subscription.configs.filter((config) => memberMatches(config.name));
const memberServerState = (server) => { const items = server.xray_inbounds; const selected = items.filter((inbound) => activeGroup.value && groupChecked(activeGroup.value, 'xray_inbound_ids', inbound.id)).length; return { total: items.length, selected, checked: items.length > 0 && selected === items.length, indeterminate: selected > 0 && selected < items.length }; };
const memberSubscriptionState = (subscription) => { const selected = activeGroup.value && groupChecked(activeGroup.value, 'external_subscription_ids', subscription.id); return { total: subscription.configs.length, selected: selected ? subscription.configs.length : 0, checked: selected, indeterminate: false }; };
const memberCountries = computed(() => [...new Set([...availableServers.value.map((server) => server.name), ...availableProxies.value.map((proxy) => proxy.server?.name)].filter(Boolean))]);
const isMemberServerExpanded = (id) => groupMemberExpandedServers.value.has(Number(id));
const toggleMemberServerExpanded = (id) => { const next = new Set(groupMemberExpandedServers.value); next.has(Number(id)) ? next.delete(Number(id)) : next.add(Number(id)); groupMemberExpandedServers.value = next; };
const isMemberSubscriptionExpanded = (id) => groupMemberExpandedSubscriptions.value.has(Number(id));
const toggleMemberSubscriptionExpanded = (id) => { const next = new Set(groupMemberExpandedSubscriptions.value); next.has(Number(id)) ? next.delete(Number(id)) : next.add(Number(id)); groupMemberExpandedSubscriptions.value = next; };
const toggleAllGroupMembers = (field, members, value) => members.forEach((member) => activeGroup.value && toggleGroup(activeGroup.value, field, member.id, value));
const toggleMemberServer = (server, value) => server.xray_inbounds.forEach((inbound) => activeGroup.value && toggleGroup(activeGroup.value, 'xray_inbound_ids', inbound.id, value));
const toggleMemberSubscription = (subscription, value) => activeGroup.value && toggleGroup(activeGroup.value, 'external_subscription_ids', subscription.id, value);
const buildGroups = () => groups.value.map(({ _tagManuallyEdited, _menuOpen, ...group }, index) => ({ ...group, xray_inbound_ids: group.xray_inbound_ids.filter((id) => checked('xray_inbound_ids', id)), external_subscription_config_ids: [], external_subscription_ids: (group.external_subscription_ids || []).filter((id) => checked('external_subscription_ids', id)), proxy_ids: group.proxy_ids.filter((id) => checked('proxy_ids', id)), sort_order: index }));
const routePlaceholder = (route) => ({ domain: 'Add domain...', ip: 'Add IP or CIDR...', port: 'Add port or range...', network: 'Add network...', protocol: 'Add protocol...' }[route.match_type] || 'Add value...');
const routeValues = (route) => route.match_values_list || splitRouteValues(route.match_values);
const syncRouteValues = (route) => { route.match_values_list = [...new Set(routeValues(route).map((value) => value.trim()).filter(Boolean))]; route.match_values = route.match_values_list.join(', '); };
const commitRouteInput = (route) => { const values = splitRouteValues(route.value_input); route.match_values_list = [...new Set([...routeValues(route), ...values])]; route.value_input = ''; syncRouteValues(route); };
const pasteRouteValues = (route, event) => { const values = splitRouteValues(event.clipboardData?.getData('text') || ''); if (values.length > 1) { event.preventDefault(); route.match_values_list = [...new Set([...routeValues(route), ...values])]; syncRouteValues(route); } };
const removeRouteValue = (route, index) => { route.match_values_list = routeValues(route).filter((_, valueIndex) => valueIndex !== index); syncRouteValues(route); };
const routeDestinationLabel = (route) => route.target_type === 'balancer' ? (groups.value.find((group) => group.tag === route.target_tag)?.name || 'Outbound group') : targetTypeOptions.find((option) => option.value === route.target_type)?.label || route.target_type;
const routeSummary = (route) => `${routeValues(route).length || 0} ${routeMatchLabels[route.match_type] || 'values'} → ${routeDestinationLabel(route)}`;
const isRouteExpanded = (index) => expandedRoutes.value.has(index);
const toggleRouteExpanded = (index) => { const next = new Set(expandedRoutes.value); next.has(index) ? next.delete(index) : next.add(index); expandedRoutes.value = next; };
const dragStartRoute = (index) => { draggedRouteIndex.value = index; };
const dropRoute = (index) => { if (draggedRouteIndex.value === null || draggedRouteIndex.value === index) return; const [route] = routes.value.splice(draggedRouteIndex.value, 1); routes.value.splice(index, 0, route); draggedRouteIndex.value = null; expandedRoutes.value = new Set(routes.value.map((_, routeIndex) => routeIndex).filter((routeIndex) => routeIndex === index)); };
const addRoute = () => { routes.value.push({ name: `Route ${routes.value.length + 1}`, match_type: 'domain', match_values: '', match_values_list: [], value_input: '', target_type: 'balancer', target_tag: groups.value[0]?.tag || '', is_active: true, _menuOpen: false }); expandedRoutes.value = new Set([...expandedRoutes.value, routes.value.length - 1]); };
const duplicateRoute = (route, index) => { const copy = JSON.parse(JSON.stringify(route)); copy.name = `${route.name || 'Route'} copy`; copy.value_input = ''; copy._menuOpen = false; routes.value.splice(index + 1, 0, copy); expandedRoutes.value = new Set([...expandedRoutes.value, index + 1]); };
const removeRoute = (route, index) => { if (!window.confirm(`Delete route “${route.name || `Route ${index + 1}`}”?`)) return; routes.value.splice(index, 1); };
watch([form, settings, baseSettingsJson, groups, routes], () => {
    hasUnsavedChanges.value = true;

    if (!previewContent.value) {
        return;
    }

    if (previewRefreshTimer) {
        window.clearTimeout(previewRefreshTimer);
    }

    previewRefreshScheduled.value = true;
    previewRefreshTimer = window.setTimeout(async () => {
        previewRefreshScheduled.value = false;
        await preview();
    }, 10000);
}, { deep: true });
const cancelEditing = () => { if (!hasUnsavedChanges.value || window.confirm('Discard unsaved changes?')) window.location.href = '/xray-custom-configs'; };

const parseBaseSettings = () => {
    try {
        const parsed = JSON.parse(baseSettingsJson.value || '{}');
        if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
            throw new Error('Base settings must be a JSON object.');
        }
        baseSettingsError.value = '';
        return parsed;
    } catch (error) {
        baseSettingsError.value = error.message || 'Base settings must be valid JSON.';
        return null;
    }
};
const buildBaseSettings = () => {
    const base = parseBaseSettings();
    if (base === null) return null;
    return { ...base, log: { ...(base.log || {}), loglevel: settings.value.loglevel }, routing: { ...(base.routing || {}), domainStrategy: settings.value.domainStrategy } };
};
const buildRoutes = () => routes.value.map(({ match_values_list, value_input, _menuOpen, ...route }, index) => {
    const values = routeValues({ ...route, match_values_list });
    const rules = route.match_type === 'domain' ? { domain: values.map((value) => value.includes(':') ? value : `domain:${value}`) } : { [route.match_type]: values };
    return { ...route, rules, sort_order: index };
});
const requestPayload = (baseSettings = buildBaseSettings()) => ({
    is_active: Boolean(form.is_active),
    external_subscription_config_ids: [],
    base_settings_json: JSON.stringify(baseSettings || {}),
    outbound_groups_json: JSON.stringify(buildGroups()),
    routes_json: JSON.stringify(buildRoutes()),
});
const submit = () => { const baseSettings = buildBaseSettings(); if (baseSettings === null) return; const request = form.transform((data) => ({ ...data, ...requestPayload(baseSettings), dns_settings_id: data.dns_settings_id || null, geodata_id: data.geodata_id || null })); props.method === 'put' ? request.put(props.submit_url) : request.post(props.submit_url); };
const preview = async () => {
    previewing.value = true;
    try {
        const url = props.config ? `${props.submit_url}/preview` : '/xray-custom-configs/preview';
        const baseSettings = buildBaseSettings();
        if (baseSettings === null) return;
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' }, body: JSON.stringify({ ...form.data(), ...requestPayload(baseSettings), preview_mode: previewMode.value, user_id: previewMode.value === 'user' ? Number(previewUserId.value) : undefined }) });
        previewContent.value = await response.json();
    } finally {
        previewing.value = false;
    }
};
const previewJson = computed(() => previewContent.value ? JSON.stringify(previewContent.value, null, 2) : '');
const copyPreview = async () => {
    if (!previewJson.value) return;
    await navigator.clipboard.writeText(previewJson.value);
    previewCopied.value = true;
    window.setTimeout(() => { previewCopied.value = false; }, 1800);
};
onBeforeUnmount(() => {
    if (previewRefreshTimer) {
        window.clearTimeout(previewRefreshTimer);
    }
});
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
    <Head :title="props.config ? 'Edit Xray Configuration' : 'Create Xray Configuration'" />
    <section class="page-card stack xray-config-form">
        <div class="page-header"><div><h1>{{ props.config ? 'Edit Xray Configuration' : 'Create Xray Configuration' }}</h1></div></div>
        <form class="grid grid--two" @submit.prevent="submit">
            <div class="form-section">
                <h2>Basic information</h2>
                <div class="grid grid--two">
                    <label class="field"><span>Configuration Name</span><AppInput v-model="form.name" required /></label>
                    <label class="field"><span>Slug</span><AppInput v-model="form.slug" required @input="slugManuallyEdited = true" /><small>Generated automatically from the configuration name.</small></label>
                    <label class="field"><span>Order in /connect JSON</span><AppInput v-model="form.sort_order" type="number" min="0" /></label>
                    <label class="field basic-description"><span>Description</span><AppTextarea v-model="form.description" rows="3" /></label>
                    <label class="field basic-status"><span>Status</span><AppCheckbox v-model="form.is_active" /> <small>{{ form.is_active ? 'Enabled' : 'Disabled' }}</small></label>
                </div>
            </div>
            <div class="form-section">
                <h2>Resources</h2>
                <div class="grid grid--two">
                    <div class="field resource-field"><span>DNS Configuration</span><AppSelect v-model="form.dns_settings_id" :options="dnsOptions.map((item) => ({ value: item.id, label: item.name }))" placeholder="Select DNS configuration" /><AppButton class="resource-action" variant="secondary" type="button" @click="openResourceModal('dns')">+ Create DNS configuration</AppButton></div>
                    <div class="field resource-field"><span>Geodata</span><AppSelect v-model="form.geodata_id" :options="geodataOptions.map((item) => ({ value: item.id, label: item.name }))" placeholder="Select geodata source" /><AppButton class="resource-action" variant="secondary" type="button" @click="openResourceModal('geodata')">+ Create geodata source</AppButton></div>
                </div>
            </div>
            <div class="form-section">
                <h2>Base settings</h2>
                <div class="grid grid--two">
                    <label class="field"><span>Domain Strategy</span><AppSelect v-model="settings.domainStrategy" :options="domainStrategyOptions" /></label>
                    <label class="field"><span>Log Level</span><AppSelect v-model="settings.loglevel" :options="logLevelOptions" /></label>
                </div>
                <label class="field base-settings-json-field">
                    <span>Additional Xray base settings (JSON)</span>
                    <AppTextarea v-model="baseSettingsJson" rows="14" spellcheck="false" />
                    <small>Use this for settings such as observatory, policy, stats, domainMatcher, or any other Xray profile-level option. The structured fields above override loglevel and domainStrategy.</small>
                    <small v-if="baseSettingsError" class="form-error">{{ baseSettingsError }}</small>
                </label>
            </div>
            <div class="source-section">
                <div class="source-section__header"><div><h2>Sources</h2><p>Choose which outbound sources may be used by this configuration.</p></div><strong>{{ totalSourcesSelected }} sources selected</strong></div>
                <div class="source-picker">
                    <div class="source-picker__header"><h3>Local servers &amp; inbounds</h3><strong>{{ localSelectedCount }} selected</strong></div>
                    <div class="source-toolbar"><AppInput v-model="localSearch" placeholder="Search servers or inbounds..." /><AppSelect v-model="localCountry" :options="countries.map((country) => ({ value: country, label: country }))" placeholder="Country" /></div>
                    <div class="source-actions"><label class="field-row"><input type="checkbox" :checked="visibleServers.length > 0 && visibleServers.every((server) => visibleInbounds(server).length > 0 && visibleInbounds(server).every((inbound) => checked('xray_inbound_ids', inbound.id)))" @change="selectVisibleInbounds($event.target.checked)"> Select all visible</label><button type="button" class="picker-text-button" @click="selectVisibleInbounds(false)">Clear selection</button></div>
                    <div class="source-list">
                        <div v-for="server in visibleServers" :key="server.id" class="source-group">
                            <div class="source-parent-row">
                                <input type="checkbox" :checked="serverState(server).checked" :indeterminate="serverState(server).indeterminate" @change="toggleServer(server, $event.target.checked)">
                                <button type="button" class="source-expand" :aria-expanded="isServerExpanded(server.id)" @click="toggleServerExpanded(server.id)"><span>{{ countryFlag(server.name) }} {{ server.name }}</span><span class="source-count">{{ serverState(server).selected }} / {{ serverState(server).total }} selected</span><span class="source-chevron">{{ isServerExpanded(server.id) ? '⌄' : '›' }}</span></button>
                            </div>
                            <div v-if="isServerExpanded(server.id)" class="source-children"><label v-for="inbound in visibleInbounds(server)" :key="inbound.id" class="source-child-row"><input type="checkbox" :checked="checked('xray_inbound_ids', inbound.id)" @change="toggle('xray_inbound_ids', inbound.id, $event.target.checked)"><span>Inbound #{{ inbound.external_id }}</span></label></div>
                        </div>
                        <p v-if="visibleServers.length === 0" class="source-empty">No matching servers or inbounds.</p>
                    </div>
                </div>
                <div class="source-picker">
                    <div class="source-picker__header"><h3>External subscriptions</h3><strong>{{ externalSelectedCount }} selected</strong></div>
                    <div class="source-toolbar source-toolbar--single"><AppInput v-model="subscriptionSearch" placeholder="Search subscriptions..." /></div>
                    <div class="source-actions"><label class="field-row"><input type="checkbox" :checked="visibleSubscriptions.length > 0 && visibleSubscriptions.every((subscription) => subscriptionChecked(subscription))" @change="selectVisibleSubscriptions($event.target.checked)"> Select all visible</label><button type="button" class="picker-text-button" @click="selectVisibleSubscriptions(false)">Clear selection</button></div>
                    <div class="source-list">
                        <div v-for="subscription in visibleSubscriptions" :key="subscription.id" class="source-group">
                            <div class="source-parent-row">
                                <input type="checkbox" :checked="subscriptionState(subscription).checked" :indeterminate="subscriptionState(subscription).indeterminate" @change="toggleSubscription(subscription, $event.target.checked)">
                                <button type="button" class="source-expand" :aria-expanded="isSubscriptionExpanded(subscription.id)" @click="toggleSubscriptionExpanded(subscription.id)"><span>{{ subscription.name }}</span><span class="source-count">{{ subscriptionState(subscription).selected }} / {{ subscriptionState(subscription).total }} configs selected</span><span class="source-chevron">{{ isSubscriptionExpanded(subscription.id) ? '⌄' : '›' }}</span></button>
                            </div>
                            <div v-if="isSubscriptionExpanded(subscription.id)" class="source-children"><span v-for="config in visibleConfigs(subscription)" :key="config.id" class="source-child-row">{{ config.name }}</span></div>
                        </div>
                        <p v-if="visibleSubscriptions.length === 0" class="source-empty">No matching subscriptions.</p>
                    </div>
                </div>
                <div class="source-picker">
                    <div class="source-picker__header"><h3>Proxy nodes</h3><strong>{{ proxySelectedCount }} selected</strong></div>
                    <div class="source-toolbar source-toolbar--triple"><AppInput v-model="proxySearch" placeholder="Search proxy nodes..." /><AppSelect v-model="proxyCountry" :options="proxyCountries.map((country) => ({ value: country, label: country }))" placeholder="Country" /><AppSelect v-model="proxyStatus" :options="[{ value: 'available', label: 'Available' }, { value: 'unavailable', label: 'Unavailable' }]" placeholder="Status" /></div>
                    <div class="source-actions"><label class="field-row"><input type="checkbox" :checked="visibleProxies.length > 0 && visibleProxies.every((proxy) => checked('proxy_ids', proxy.id))" @change="selectVisibleProxies($event.target.checked)"> Select all visible</label><button type="button" class="picker-text-button" @click="selectVisibleProxies(false)">Clear selection</button></div>
                    <div class="source-list source-list--flat"><label v-for="proxy in visibleProxies" :key="proxy.id" class="source-child-row"><input type="checkbox" :checked="checked('proxy_ids', proxy.id)" @change="toggle('proxy_ids', proxy.id, $event.target.checked)"><span>{{ countryFlag(proxy.server?.name) }} {{ proxy.server?.name || 'Auto' }} · {{ proxy.name }}<small>{{ proxy.is_ready ? 'Available' : 'Unavailable' }}</small></span></label><p v-if="visibleProxies.length === 0" class="source-empty">No matching proxy nodes.</p></div>
                </div>
            </div>
            <div class="source-section routing-templates">
                <div class="source-section__header"><div><h2>Routing rule templates</h2><p>Optional reusable rules that will be included in this configuration.</p></div></div>
                <div class="routing-list"><label v-for="routing in props.targets.routings || []" :key="routing.id" class="routing-row"><input type="checkbox" :checked="checked('xray_routing_ids', routing.id)" @change="toggle('xray_routing_ids', routing.id, $event.target.checked)"><span><strong>{{ routing.name }}</strong><small>{{ routing.description || 'Reusable routing rule template.' }}{{ routing.is_active ? '' : ' · Inactive' }}</small></span></label><p v-if="!(props.targets.routings || []).length" class="source-empty">No routing rule templates available.</p></div>
            </div>
            <div class="groups-section">
                <div class="groups-section__header"><div><h2>Outbound groups</h2><p>Combine servers, subscription configs, and proxy nodes into reusable outbound groups. Groups can use balancing and fallback.</p></div><AppButton variant="secondary" type="button" @click="addGroup">+ Add group</AppButton></div>
                <div v-if="groups.length === 0" class="groups-empty">No outbound groups yet. Add a group to create reusable balancing and fallback pools.</div>
                <article v-for="(group, index) in groups" :key="index" class="group-card" :class="{ 'group-card--collapsed': !isGroupExpanded(index) }">
                    <div class="group-card__header"><button type="button" class="group-card__toggle" :aria-expanded="isGroupExpanded(index)" @click="toggleGroupExpanded(index)"><span><strong>{{ group.name || 'New outbound group' }}</strong><small>{{ group.tag }}</small></span><span class="group-card__summary"><b>{{ groupMemberCount(group) }} members</b><span>{{ strategyLabel(group.strategy) }}</span><span>{{ group.fallback_group_tag ? `Fallback → ${groups.find((item) => item.tag === group.fallback_group_tag)?.name || group.fallback_group_tag}` : 'No fallback' }}</span></span><span class="source-chevron">{{ isGroupExpanded(index) ? '⌄' : '›' }}</span></button><div class="group-card__menu"><button type="button" aria-label="Group actions" @click.stop="group._menuOpen = !group._menuOpen">⋯</button><div v-if="group._menuOpen" class="group-card__menu-popover"><button type="button" @click="duplicateGroup(group, index)">Duplicate group</button><button type="button" class="is-danger" @click="removeGroup(group, index)">Delete group</button></div></div></div>
                    <div v-if="isGroupExpanded(index)" class="group-card__body">
                        <div class="grid grid--two"><label class="field"><span>Group name</span><AppInput v-model="group.name" @input="onGroupNameInput(group)" /></label><label class="field"><span>Group tag</span><AppInput v-model="group.tag" @input="group._tagManuallyEdited = true" /><small>Generated automatically from the group name.</small></label><label class="field"><span>Balancing strategy</span><AppSelect v-model="group.strategy" :options="strategyOptions" /></label><label class="field"><span>Fallback group</span><AppSelect v-model="group.fallback_group_tag" :options="fallbackOptions(group)" /></label></div>
                        <p v-if="groupInvalidMemberCount(group)" class="group-warning">{{ groupInvalidMemberCount(group) }} member(s) are no longer enabled in Sources and will be removed when saved.</p>
                        <div class="group-members"><h3>Members</h3><div class="group-member-type"><div class="group-member-type__header"><strong>Local servers &amp; inbounds</strong><span>{{ availableServers.flatMap((server) => groupSelectedInbounds(group, server)).length }} selected</span></div><div v-if="availableServers.flatMap((server) => groupSelectedInbounds(group, server)).length" class="group-member-list"><span v-for="inbound in availableServers.flatMap((server) => groupSelectedInbounds(group, server))" :key="`gm-i-${inbound.id}`">{{ countryFlag(availableServers.find((server) => server.xray_inbounds.some((item) => item.id === inbound.id))?.name) }} {{ availableServers.find((server) => server.xray_inbounds.some((item) => item.id === inbound.id))?.name }} · Inbound #{{ inbound.external_id }}</span></div><small v-else class="group-member-empty">No local servers or inbounds added.</small></div><div class="group-member-type"><div class="group-member-type__header"><strong>External subscriptions</strong><span>{{ group.external_subscription_ids.length }} selected</span></div><div v-if="availableSubscriptions.filter((subscription) => group.external_subscription_ids.includes(subscription.id)).length" class="group-member-list"><span v-for="subscription in availableSubscriptions.filter((item) => group.external_subscription_ids.includes(item.id))" :key="`gm-s-${subscription.id}`">{{ subscription.name }} · all current configs</span></div><small v-else class="group-member-empty">No subscription configs added.</small></div><div class="group-member-type"><div class="group-member-type__header"><strong>Proxy nodes</strong><span>{{ groupSelectedProxies(group).length }} selected</span></div><div v-if="groupSelectedProxies(group).length" class="group-member-list"><span v-for="proxy in groupSelectedProxies(group)" :key="`gm-p-${proxy.id}`">{{ countryFlag(proxy.server?.name) }} {{ proxy.server?.name || 'Auto' }} · {{ proxy.name }}</span></div><small v-else class="group-member-empty">No proxy nodes added.</small></div></div>
                        <div class="group-card__actions"><AppButton variant="secondary" type="button" @click="openGroupMemberPicker(index)">+ Add members</AppButton><button type="button" class="picker-text-button" @click="openGroupMemberPicker(index)">Manage members</button></div><div v-if="group.fallback_group_tag" class="fallback-chain"><strong>Fallback chain</strong><span>{{ fallbackChain(group).join(' → ') }}</span></div>
                    </div>
                </article>
            </div>
            <div class="routes-section">
                <div class="routes-section__header"><div><h2>Site routes</h2><p>Route traffic for specific domains, IPs, ports, or networks.<br>Rules are evaluated from top to bottom.</p></div><AppButton variant="secondary" type="button" @click="addRoute">+ Add route</AppButton></div>
                <div v-if="routes.length === 0" class="routes-empty">No site routes yet. Add a route to define traffic behavior.</div>
                <article v-for="(route, index) in routes" :key="route" class="route-card" :class="{ 'route-card--collapsed': !isRouteExpanded(index) }" draggable="true" @dragstart="dragStartRoute(index)" @dragover.prevent @drop="dropRoute(index)">
                    <div class="route-card__header"><span class="route-drag-handle" title="Drag to reorder">⠿</span><button type="button" class="route-card__toggle" :aria-expanded="isRouteExpanded(index)" @click="toggleRouteExpanded(index)"><span class="route-card__title"><b>{{ index + 1 }}</b><strong>{{ route.name || `Route ${index + 1}` }}</strong><small>{{ routeSummary(route) }}</small></span><span class="route-card__status" :class="{ 'is-disabled': !route.is_active }">{{ route.is_active ? 'Enabled' : 'Disabled' }}</span><span class="source-chevron">{{ isRouteExpanded(index) ? '⌄' : '›' }}</span></button><div class="group-card__menu"><button type="button" aria-label="Route actions" @click.stop="route._menuOpen = !route._menuOpen">⋯</button><div v-if="route._menuOpen" class="group-card__menu-popover"><button type="button" @click="duplicateRoute(route, index)">Duplicate route</button><button type="button" class="is-danger" @click="removeRoute(route, index)">Delete route</button></div></div></div>
                    <div v-if="isRouteExpanded(index)" class="route-card__body">
                        <div class="grid grid--two"><label class="field"><span>Route name</span><AppInput v-model="route.name" /></label><label class="field route-enabled"><span>Enabled</span><AppCheckbox v-model="route.is_active" /></label></div>
                        <label class="field"><span>Match type</span><AppSelect v-model="route.match_type" :options="matchTypeOptions" /></label>
                        <div class="field"><span>{{ routeMatchLabels[route.match_type] || 'Values' }}</span><div class="route-token-input"><span v-for="(value, valueIndex) in routeValues(route)" :key="`${value}-${valueIndex}`" class="route-token">{{ value }}<button type="button" aria-label="Remove value" @click="removeRouteValue(route, valueIndex)">×</button></span><input v-model="route.value_input" :placeholder="routePlaceholder(route)" @keydown.enter.prevent="commitRouteInput(route)" @keydown="$event.key === ',' ? ( $event.preventDefault(), commitRouteInput(route) ) : null" @paste="pasteRouteValues(route, $event)" @blur="commitRouteInput(route)"></div><small>Press Enter, type a comma, or paste multiple newline/comma-separated values.</small></div>
                        <div class="grid grid--two"><label class="field"><span>Route via</span><AppSelect v-model="route.target_type" :options="targetTypeOptions" /></label><label v-if="route.target_type === 'balancer'" class="field"><span>Target group</span><AppSelect v-model="route.target_tag" :options="groupOptions" /><small>{{ groups.find((group) => group.tag === route.target_tag)?.tag || 'Select an outbound group.' }}</small></label></div>
                    </div>
                </article>
            </div>
            <div class="config-form-footer"><span :class="{ 'is-dirty': hasUnsavedChanges }">{{ hasUnsavedChanges ? 'Unsaved changes' : 'No unsaved changes' }}</span><div class="actions"><button type="button" class="footer-cancel" @click="cancelEditing">Cancel</button><AppButton type="submit" :disabled="form.processing">Save configuration</AppButton></div></div>
        </form>
        <div v-if="activeGroup" class="group-member-modal" @click.self="closeGroupMemberPicker">
            <section class="group-member-modal__card page-card stack">
                <div class="group-member-modal__header"><div><h2>Add members to “{{ activeGroup.name || activeGroup.tag }}”</h2><p>Only sources enabled in this configuration can be added to a group.</p></div><button type="button" class="group-member-modal__close" aria-label="Close" @click="closeGroupMemberPicker">×</button></div>
                <div class="source-toolbar source-toolbar--triple"><AppInput v-model="groupMemberSearch" placeholder="Search sources..." /><AppSelect v-model="groupMemberType" :options="[{ value: 'local', label: 'Local servers & inbounds' }, { value: 'subscription', label: 'External subscriptions' }, { value: 'proxy', label: 'Proxy nodes' }]" placeholder="Type" /><AppSelect v-model="groupMemberCountry" :options="memberCountries.map((country) => ({ value: country, label: country }))" placeholder="Country" /></div>
                <div class="source-actions"><span>{{ activeGroup ? groupMemberCount(activeGroup) : 0 }} members selected</span><button type="button" class="picker-text-button" @click="toggleAllGroupMembers('xray_inbound_ids', memberVisibleServers.flatMap((server) => memberVisibleInbounds(server)), true); toggleAllGroupMembers('external_subscription_ids', memberVisibleSubscriptions, true); toggleAllGroupMembers('proxy_ids', memberVisibleProxies, true)">Select all visible</button><button type="button" class="picker-text-button" @click="toggleAllGroupMembers('xray_inbound_ids', memberVisibleServers.flatMap((server) => memberVisibleInbounds(server)), false); toggleAllGroupMembers('external_subscription_ids', memberVisibleSubscriptions, false); toggleAllGroupMembers('proxy_ids', memberVisibleProxies, false)">Clear selection</button></div>
                <div class="group-member-modal__list">
                    <div v-if="memberVisibleServers.length" class="member-picker-section"><div class="member-picker-section__header"><strong>Local servers &amp; inbounds</strong><span>{{ memberVisibleServers.flatMap((server) => server.xray_inbounds).filter((inbound) => groupChecked(activeGroup, 'xray_inbound_ids', inbound.id)).length }} selected</span></div><div v-for="server in memberVisibleServers" :key="`mp-s-${server.id}`" class="source-group"><div class="source-parent-row"><input type="checkbox" :checked="memberServerState(server).checked" :indeterminate="memberServerState(server).indeterminate" @change="toggleMemberServer(server, $event.target.checked)"><button type="button" class="source-expand" :aria-expanded="isMemberServerExpanded(server.id)" @click="toggleMemberServerExpanded(server.id)"><span>{{ countryFlag(server.name) }} {{ server.name }}</span><span class="source-count">{{ memberServerState(server).selected }} / {{ memberServerState(server).total }} selected</span><span class="source-chevron">{{ isMemberServerExpanded(server.id) ? '⌄' : '›' }}</span></button></div><div v-if="isMemberServerExpanded(server.id)" class="source-children"><label v-for="inbound in memberVisibleInbounds(server)" :key="`mp-i-${inbound.id}`" class="source-child-row"><input type="checkbox" :checked="groupChecked(activeGroup, 'xray_inbound_ids', inbound.id)" @change="toggleGroup(activeGroup, 'xray_inbound_ids', inbound.id, $event.target.checked)"><span>Inbound #{{ inbound.external_id }}</span></label></div></div></div>
                    <div v-if="memberVisibleSubscriptions.length" class="member-picker-section"><div class="member-picker-section__header"><strong>External subscriptions</strong><span>{{ memberVisibleSubscriptions.filter((subscription) => groupChecked(activeGroup, 'external_subscription_ids', subscription.id)).length }} selected</span></div><div v-for="subscription in memberVisibleSubscriptions" :key="`mp-e-${subscription.id}`" class="source-group"><div class="source-parent-row"><input type="checkbox" :checked="memberSubscriptionState(subscription).checked" @change="toggleMemberSubscription(subscription, $event.target.checked)"><button type="button" class="source-expand" :aria-expanded="isMemberSubscriptionExpanded(subscription.id)" @click="toggleMemberSubscriptionExpanded(subscription.id)"><span>{{ subscription.name }}</span><span class="source-count">{{ memberSubscriptionState(subscription).selected }} / {{ memberSubscriptionState(subscription).total }} current configs</span><span class="source-chevron">{{ isMemberSubscriptionExpanded(subscription.id) ? '⌄' : '›' }}</span></button></div><div v-if="isMemberSubscriptionExpanded(subscription.id)" class="source-children"><span v-for="config in memberVisibleConfigs(subscription)" :key="`mp-c-${config.id}`" class="source-child-row">{{ config.name }}</span></div></div></div>
                    <div v-if="memberVisibleProxies.length" class="member-picker-section"><div class="member-picker-section__header"><strong>Proxy nodes</strong><span>{{ memberVisibleProxies.filter((proxy) => groupChecked(activeGroup, 'proxy_ids', proxy.id)).length }} selected</span></div><label v-for="proxy in memberVisibleProxies" :key="`mp-p-${proxy.id}`" class="source-child-row"><input type="checkbox" :checked="groupChecked(activeGroup, 'proxy_ids', proxy.id)" @change="toggleGroup(activeGroup, 'proxy_ids', proxy.id, $event.target.checked)"><span>{{ countryFlag(proxy.server?.name) }} {{ proxy.server?.name || 'Auto' }} · {{ proxy.name }}<small>{{ proxy.is_ready ? 'Available' : 'Unavailable' }}</small></span></label></div>
                    <p v-if="!memberVisibleServers.length && !memberVisibleSubscriptions.length && !memberVisibleProxies.length" class="source-empty">No enabled sources match your filters.</p>
                </div>
                <div class="actions"><AppButton variant="secondary" type="button" @click="closeGroupMemberPicker">Cancel</AppButton><AppButton type="button" @click="closeGroupMemberPicker(true)">Add {{ activeGroup ? groupMemberCount(activeGroup) : 0 }} members</AppButton></div>
            </section>
        </div>
    </section>
    <section class="page-card stack preview-section">
        <div class="preview-section__header">
            <button type="button" class="preview-section__toggle" :aria-expanded="previewExpanded" @click="previewExpanded = !previewExpanded">
                <span><strong>Предпросмотр JSON</strong><small>Проверка итоговой конфигурации до сохранения</small></span>
                <span class="source-chevron">{{ previewExpanded ? '⌄' : '›' }}</span>
            </button>
            <span v-if="previewRefreshScheduled" class="preview-section__status">Обновится через 10 секунд</span>
        </div>
        <div v-if="previewExpanded" class="preview-section__body">
            <label class="field-row"><input v-model="previewMode" type="radio" value="admin"> От имени администратора</label>
            <label class="field-row"><input v-model="previewMode" type="radio" value="user"> От имени пользователя</label>
            <AppSelect v-if="previewMode === 'user'" v-model="previewUserId" :options="props.users.map((user) => ({ value: user.id, label: user.full_name || user.telegram_id }))" />
            <div class="preview-section__actions">
                <AppButton variant="secondary" type="button" :disabled="previewing" @click="preview">{{ previewing ? 'Загрузка…' : 'Предпросмотр JSON' }}</AppButton>
                <AppButton v-if="previewContent" variant="secondary" type="button" @click="copyPreview">{{ previewCopied ? 'Скопировано' : 'Копировать JSON' }}</AppButton>
            </div>
            <pre v-if="previewContent" class="preview-json">{{ previewJson }}</pre>
        </div>
    </section>
    <div v-if="modal" class="resource-modal" @click.self="closeResourceModal"><section class="page-card stack resource-modal__card"><h2>{{ modal === 'dns' ? 'Новые настройки DNS' : 'Новые геоданные' }}</h2><label class="field"><span>Название</span><AppInput v-model="modalForm.name" /></label><label class="field"><span>Описание</span><AppTextarea v-model="modalForm.description" rows="2" /></label><template v-if="modal === 'dns'"><label class="field"><span>DNS-серверы через запятую</span><AppInput v-model="modalForm.servers" placeholder="8.8.8.8, 1.1.1.1" /></label><label class="field"><span>Стратегия запросов</span><AppSelect v-model="modalForm.query_strategy" :options="[{ value: 'UseIPv4', label: 'Только IPv4' }, { value: 'UseIPv6', label: 'Только IPv6' }, { value: 'UseIP', label: 'IPv4 и IPv6' }, { value: 'AsIs', label: 'Без изменения' }]" /></label><label class="field-row"><input v-model="modalForm.enable_parallel_query" type="checkbox"> Параллельные DNS-запросы</label></template><template v-else><label class="field"><span>URL geoip.dat</span><AppInput v-model="modalForm.geoip_url" /></label><label class="field"><span>URL geosite.dat</span><AppInput v-model="modalForm.geosite_url" /></label></template><p v-if="modalError" class="form-error">{{ modalError }}</p><div class="actions"><AppButton type="button" @click="saveResource">Сохранить</AppButton><AppButton variant="secondary" type="button" @click="closeResourceModal">Отмена</AppButton></div></section></div>
</template>

<style scoped>
.xray-config-form > form {
    gap: 0;
}

.form-section {
    grid-column: 1 / -1;
    padding: 1.25rem 0 1.5rem;
    border-bottom: 1px solid var(--border, rgba(184, 199, 219, 0.55));
}

.form-section:first-child {
    padding-top: 0;
}

.form-section h2 {
    margin: 0 0 1rem;
    font-size: 1.05rem;
}

.form-section .grid {
    gap: 1rem;
}

.xray-config-form :deep(.p-inputtext),
.xray-config-form :deep(.p-select) {
    min-height: 42px;
}

.resource-field {
    align-content: start;
}

.resource-action {
    justify-self: start;
    margin-top: 0.1rem;
}

.field small {
    margin-top: -0.25rem;
}

@media (max-width: 700px) {
    .form-section .grid--two {
        grid-template-columns: 1fr;
    }
}

.source-section {
    grid-column: 1 / -1;
    display: grid;
    gap: 1rem;
    padding-top: 1.5rem;
}

.source-section__header,
.source-picker__header,
.source-actions,
.source-parent-row,
.routing-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.source-section__header {
    justify-content: space-between;
    align-items: flex-start;
}

.source-section__header h2,
.source-picker__header h3 {
    margin: 0;
}

.source-section__header p {
    margin: 0.3rem 0 0;
    color: var(--muted);
}

.source-section__header > strong,
.source-picker__header > strong {
    white-space: nowrap;
    color: var(--muted);
    font-size: 0.9rem;
}

.source-picker {
    display: grid;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid var(--border, rgba(184, 199, 219, 0.72));
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.56);
}

.source-toolbar {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(10rem, 0.35fr);
    gap: 0.65rem;
}

.source-toolbar--triple {
    grid-template-columns: minmax(0, 1fr) minmax(10rem, 0.35fr) minmax(10rem, 0.35fr);
}

.source-toolbar--single {
    grid-template-columns: minmax(0, 1fr);
}

.source-actions {
    justify-content: space-between;
    min-height: 2rem;
    color: var(--muted);
    font-size: 0.9rem;
}

.source-actions .field-row {
    gap: 0.5rem;
}

.picker-text-button {
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--primary);
    font: inherit;
    cursor: pointer;
}

.source-list {
    max-height: 24rem;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.source-group + .source-group {
    border-top: 1px solid rgba(184, 199, 219, 0.45);
}

.source-parent-row {
    min-height: 2.75rem;
}

.source-parent-row > input,
.source-child-row > input,
.routing-row > input {
    flex: 0 0 auto;
}

.source-expand {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
    border: 0;
    padding: 0.55rem 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.source-expand > span:first-child {
    overflow-wrap: anywhere;
    font-weight: 650;
}

.source-count,
.source-chevron {
    color: var(--muted);
    font-size: 0.86rem;
    white-space: nowrap;
}

.source-chevron {
    width: 1rem;
    text-align: center;
    font-size: 1.2rem;
}

.source-children {
    display: grid;
    gap: 0.25rem;
    margin: 0 0 0.5rem 2rem;
}

.source-child-row,
.routing-row {
    min-height: 2.25rem;
    padding: 0.35rem 0.5rem;
    border-radius: 8px;
    color: var(--text);
}

.source-child-row:hover,
.routing-row:hover {
    background: rgba(226, 232, 240, 0.55);
}

.source-child-row > span,
.routing-row > span {
    min-width: 0;
}

.source-child-row small,
.routing-row small {
    display: block;
    margin-top: 0.15rem;
    color: var(--muted);
    font-size: 0.82rem;
}

.source-empty {
    margin: 0;
    padding: 0.75rem 0.5rem;
    color: var(--muted);
    font-size: 0.9rem;
}

.routing-templates {
    padding-bottom: 0;
}

.routing-list {
    display: grid;
    gap: 0.2rem;
    padding: 0.5rem;
    border: 1px solid var(--border, rgba(184, 199, 219, 0.72));
    border-radius: 14px;
}

@media (max-width: 700px) {
    .source-section__header,
    .source-picker__header {
        align-items: flex-start;
        flex-direction: column;
        gap: 0.25rem;
    }

    .source-toolbar {
        grid-template-columns: 1fr;
    }
}

.groups-section {
    grid-column: 1 / -1;
    display: grid;
    gap: 1rem;
    padding-top: 1.5rem;
}

.groups-section__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.groups-section__header h2,
.group-members h3 {
    margin: 0;
}

.groups-section__header p,
.group-member-modal__header p {
    max-width: 48rem;
    margin: 0.3rem 0 0;
    color: var(--muted);
}

.groups-empty,
.group-member-empty {
    color: var(--muted);
}

.group-card {
    position: relative;
    display: grid;
    overflow: visible;
    border: 1px solid var(--border, rgba(184, 199, 219, 0.72));
    border-radius: 14px;
    background: #fff;
}

.group-card__header {
    display: flex;
    align-items: stretch;
    min-width: 0;
}

.group-card__toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
    flex: 1;
    border: 0;
    padding: 1rem 0 1rem 1rem;
    background: transparent;
    color: var(--text);
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.group-card__toggle > span:first-child {
    display: grid;
    min-width: 0;
}

.group-card__toggle strong {
    overflow-wrap: anywhere;
    font-size: 1.05rem;
}

.group-card__toggle small {
    margin-top: 0.2rem;
    color: var(--muted);
}

.group-card__summary {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.75rem;
    color: var(--muted);
    font-size: 0.86rem;
}

.group-card__summary b {
    color: var(--text);
}

.group-card__menu {
    position: relative;
    padding: 0.75rem 0.75rem 0 0;
}

.group-card__menu > button,
.group-member-modal__close {
    border: 0;
    background: transparent;
    color: var(--muted);
    font: inherit;
    cursor: pointer;
}

.group-card__menu > button {
    padding: 0.25rem 0.5rem;
    font-size: 1.25rem;
}

.group-card__menu-popover {
    position: absolute;
    top: 2.5rem;
    right: 0.5rem;
    z-index: 5;
    display: grid;
    min-width: 10rem;
    padding: 0.35rem;
    border: 1px solid var(--border, rgba(184, 199, 219, 0.72));
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.14);
}

.group-card__menu-popover button {
    border: 0;
    padding: 0.55rem 0.65rem;
    background: transparent;
    color: var(--text);
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.group-card__menu-popover button:hover {
    background: #f1f5f9;
}

.group-card__menu-popover .is-danger {
    color: var(--danger);
}

.group-card__body {
    display: grid;
    gap: 1rem;
    padding: 0 1rem 1rem;
}

.group-members {
    display: grid;
    gap: 0.65rem;
}

.group-member-type {
    display: grid;
    gap: 0.35rem;
}

.group-member-type__header,
.member-picker-section__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    color: var(--muted);
    font-size: 0.88rem;
}

.group-member-type__header strong,
.member-picker-section__header strong {
    color: var(--text);
}

.group-member-list {
    display: grid;
    gap: 0.2rem;
    padding: 0.5rem 0.65rem;
    border: 1px solid rgba(184, 199, 219, 0.55);
    border-radius: 9px;
    background: #f8fafc;
}

.group-member-list span {
    overflow-wrap: anywhere;
    font-size: 0.9rem;
}

.group-card__actions,
.fallback-chain {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.fallback-chain {
    justify-content: space-between;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(184, 199, 219, 0.45);
    color: var(--muted);
}

.fallback-chain span {
    color: var(--text);
}

.group-warning {
    margin: 0;
    color: var(--danger);
    font-size: 0.88rem;
}

.group-member-modal {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgb(15 23 42 / 45%);
}

.group-member-modal__card {
    width: min(52rem, 100%);
    max-height: 90vh;
    overflow: auto;
}

.group-member-modal__header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.group-member-modal__header h2 {
    margin: 0;
}

.group-member-modal__close {
    font-size: 1.75rem;
    line-height: 1;
}

.group-member-modal__list {
    max-height: 28rem;
    overflow-y: auto;
    padding-right: 0.25rem;
}

.member-picker-section {
    display: grid;
    gap: 0.4rem;
    padding: 0.75rem 0;
}

.member-picker-section + .member-picker-section {
    border-top: 1px solid rgba(184, 199, 219, 0.55);
}

@media (max-width: 700px) {
    .groups-section__header,
    .group-card__toggle,
    .group-card__summary,
    .fallback-chain {
        align-items: flex-start;
        flex-direction: column;
    }

    .group-card__toggle {
        gap: 0.5rem;
    }
}

.resource-modal { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1rem; background: rgb(0 0 0 / 45%); }
.resource-modal__card { width: min(36rem, 100%); max-height: 90vh; overflow: auto; }

.basic-description {
    grid-column: 1 / -1;
}

.basic-status {
    align-content: start;
}

.routes-section {
    grid-column: 1 / -1;
    display: grid;
    gap: 1rem;
    padding-top: 1.5rem;
}

.routes-section__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.routes-section__header h2 {
    margin: 0;
}

.routes-section__header p {
    margin: 0.3rem 0 0;
    color: var(--muted);
}

.routes-empty {
    color: var(--muted);
}

.route-card {
    display: grid;
    border: 1px solid var(--border, rgba(184, 199, 219, 0.72));
    border-radius: 14px;
    background: #fff;
}

.route-card__header {
    display: flex;
    align-items: center;
    min-width: 0;
}

.route-drag-handle {
    padding: 0 0.4rem 0 1rem;
    color: var(--muted);
    cursor: grab;
    user-select: none;
}

.route-card__toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.85rem;
    min-width: 0;
    flex: 1;
    border: 0;
    padding: 0.9rem 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.route-card__title {
    display: grid;
    grid-template-columns: 1.5rem minmax(8rem, auto) minmax(0, 1fr);
    align-items: baseline;
    gap: 0.65rem;
    min-width: 0;
}

.route-card__title b {
    color: var(--muted);
}

.route-card__title strong,
.route-card__title small {
    overflow-wrap: anywhere;
}

.route-card__title small {
    color: var(--muted);
}

.route-card__status {
    color: var(--success, #15803d);
    font-size: 0.86rem;
    white-space: nowrap;
}

.route-card__status.is-disabled {
    color: var(--muted);
}

.route-card__body {
    display: grid;
    gap: 1rem;
    padding: 0 1rem 1rem 3rem;
}

.route-enabled {
    align-content: start;
}

.route-token-input {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    min-height: 44px;
    padding: 0.35rem 0.55rem;
    border: 1px solid var(--border-strong);
    border-radius: 12px;
    background: #fff;
}

.route-token-input:focus-within {
    border-color: color-mix(in srgb, var(--primary) 65%, white);
    box-shadow: 0 0 0 0.2rem rgba(31, 79, 209, 0.14);
}

.route-token {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    padding: 0.25rem 0.45rem;
    border-radius: 6px;
    background: #eff6ff;
    color: var(--text);
    font-size: 0.88rem;
    overflow-wrap: anywhere;
}

.route-token button {
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--muted);
    cursor: pointer;
}

.route-token-input input {
    flex: 1 1 12rem;
    min-width: 8rem;
    min-height: 30px !important;
    padding: 0.25rem !important;
    border: 0 !important;
    box-shadow: none !important;
}

.config-form-footer {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border, rgba(184, 199, 219, 0.72));
}

.config-form-footer > span {
    color: var(--muted);
    font-size: 0.9rem;
}

.config-form-footer > span.is-dirty {
    color: var(--text);
    font-weight: 600;
}

.footer-cancel {
    border: 0;
    padding: 0.65rem 0.8rem;
    background: transparent;
    color: var(--muted);
    font: inherit;
    cursor: pointer;
}

.preview-section {
    margin-top: 1.5rem;
}

.preview-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.preview-section__toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex: 1;
    gap: 1rem;
    border: 0;
    padding: 0;
    background: transparent;
    color: var(--text);
    text-align: left;
    font: inherit;
    cursor: pointer;
}

.preview-section__toggle > span:first-child {
    display: grid;
    gap: 0.25rem;
}

.preview-section__toggle small,
.preview-section__status {
    color: var(--muted);
    font-size: 0.86rem;
}

.preview-section__status {
    white-space: nowrap;
}

.preview-section__body {
    display: grid;
    gap: 0.75rem;
}

.preview-section__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.preview-json {
    max-height: 36rem;
    overflow: auto;
    margin: 0;
    padding: 1rem;
    border-radius: 10px;
    background: #111827;
    color: #e5e7eb;
    font-size: 0.8rem;
    line-height: 1.5;
    white-space: pre;
}

@media (max-width: 700px) {
    .routes-section__header,
    .config-form-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .route-card__title {
        grid-template-columns: 1.5rem minmax(0, 1fr);
    }

    .route-card__title small {
        grid-column: 2;
    }

    .route-card__body {
        padding-left: 1rem;
    }
}
</style>
