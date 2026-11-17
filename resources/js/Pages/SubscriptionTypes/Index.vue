<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    types: { type: Array, default: () => [] },
    catalog: { type: Array, default: () => [] },
    selected_type_id: Number,
});
const page = usePage();
const serverErrors = computed(() => page.props.errors ?? {});

const localTypes = ref(props.types.map((type) => ({ ...type, items: type.items.map((item) => ({ ...item })) })));
const selectedId = ref(props.selected_type_id || localTypes.value[0]?.id || null);
const addSourceKey = ref('');
const dragKey = ref(null);
const userUuid = ref('');
const previewAudience = ref('active');
const previewUserSearch = ref('');
const previewUsers = ref([]);
const selectedPreviewUser = ref(null);
const previewResult = ref(null);
const previewLoading = ref(false);
const previewUserSearchLoading = ref(false);
const previewError = ref('');

watch(() => props.types, (types) => {
    localTypes.value = types.map((type) => ({ ...type, items: type.items.map((item) => ({ ...item })) }));
    if (!localTypes.value.some((type) => type.id === selectedId.value)) {
        selectedId.value = props.selected_type_id || localTypes.value[0]?.id || null;
    }
});

watch(() => props.selected_type_id, (id) => {
    if (id && localTypes.value.some((type) => type.id === id)) {
        selectedId.value = id;
    }
});

const selectedType = computed(() => localTypes.value.find((type) => type.id === selectedId.value) ?? null);
const previewAudienceOptions = [
    { value: 'active', label: 'Активный пользователь' },
    { value: 'admin', label: 'Администратор' },
    { value: 'inactive', label: 'Без активной подписки или с долгом' },
    { value: 'user', label: 'Выбрать пользователя' },
];
const kindOptions = [
    { value: 'connect', label: 'V1 — Connect' },
    { value: 'connect_v2', label: 'V2 — JSON' },
    { value: 'connect_free', label: 'Free — бесплатный доступ' },
];
const formatOptions = [
    { value: 'uri', label: 'URI / base64' },
    { value: 'json', label: 'Xray JSON' },
    { value: 'clash', label: 'Clash / Mihomo' },
    { value: 'sing-box', label: 'sing-box' },
];

const catalogByKey = computed(() => new Map(props.catalog.map((source) => [`${source.source_type}:${source.source_id}`, source])));
const sourceKey = (item) => `${item.source_type}:${item.source_id}`;
const selectedItems = computed(() => (selectedType.value?.items ?? [])
    .slice()
    .sort((left, right) => left.sort_order - right.sort_order)
    .map((item) => ({ ...item, key: sourceKey(item), source: catalogByKey.value.get(sourceKey(item)) })));
const hasFormatConflict = computed(() => selectedType.value?.format !== 'json'
    && selectedItems.value.some((item) => item.source_type === 'xray_custom_config'));
const selectedSourceKeys = computed(() => new Set((selectedType.value?.items ?? []).map(sourceKey)));
const availableSources = computed(() => props.catalog.filter((source) => {
    if (selectedSourceKeys.value.has(`${source.source_type}:${source.source_id}`)) return false;
    if (source.source_type === 'xray_custom_config' && selectedType.value?.format !== 'json') return false;
    return true;
}));
const sourceGroups = computed(() => availableSources.value.reduce((groups, source) => {
    (groups[source.group] ??= []).push(source);
    return groups;
}, {}));
const availableSourceOptions = computed(() => Object.entries(sourceGroups.value).flatMap(([group, sources]) => [
    { value: `group:${group}`, label: `— ${group} —`, disabled: true },
    ...sources.map((source) => ({
        value: `${source.source_type}:${source.source_id}`,
        label: `${source.name}${source.detail ? ` · ${source.detail}` : ''}`,
    })),
]));

const createForm = useForm({
    name: '',
    slug: '',
    kind: 'connect',
    format: 'uri',
    is_active: true,
    show_expired_placeholder: true,
    items: [],
});

const outputUrl = computed(() => selectedType.value
    ? `/subscription/${selectedType.value.slug}?token=${userUuid.value || '{user_uuid}'}`
    : '');
const hasChanges = computed(() => {
    if (!selectedType.value) return false;
    const initial = props.types.find((type) => type.id === selectedType.value.id);
    return initial ? JSON.stringify(initial) !== JSON.stringify(selectedType.value) : true;
});

const addSource = () => {
    const [source_type, rawId] = addSourceKey.value.split(':');
    if (!source_type || source_type === 'group' || !rawId || !selectedType.value) return;
    selectedType.value.items.push({ source_type, source_id: Number(rawId), sort_order: selectedType.value.items.length });
    addSourceKey.value = '';
};

const removeSource = (item) => {
    selectedType.value.items = selectedType.value.items.filter((source) => sourceKey(source) !== item.key);
    normalizeOrder();
};

const moveItem = (index, offset) => {
    const items = selectedItems.value.map((item) => ({
        source_type: item.source_type,
        source_id: item.source_id,
        sort_order: item.sort_order,
    }));
    const target = index + offset;
    if (target < 0 || target >= items.length) return;
    const [moving] = items.splice(index, 1);
    items.splice(target, 0, moving);
    selectedType.value.items = items.map((item, sort_order) => ({ ...item, sort_order }));
};

const dropOn = (targetKey) => {
    const fromIndex = selectedItems.value.findIndex((item) => item.key === dragKey.value);
    const toIndex = selectedItems.value.findIndex((item) => item.key === targetKey);
    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return;
    const items = selectedItems.value.map(({ source_type, source_id }) => ({ source_type, source_id }));
    const [moving] = items.splice(fromIndex, 1);
    items.splice(toIndex, 0, moving);
    selectedType.value.items = items.map((item, sort_order) => ({ ...item, sort_order }));
};

function normalizeOrder() {
    selectedType.value.items = selectedItems.value.map(({ source_type, source_id }, sort_order) => ({
        source_type,
        source_id,
        sort_order,
    }));
}

const saveType = () => {
    if (!selectedType.value) return;
    const payload = {
        name: selectedType.value.name,
        slug: selectedType.value.slug,
        kind: selectedType.value.kind,
        format: selectedType.value.format,
        is_active: Boolean(selectedType.value.is_active),
        show_expired_placeholder: Boolean(selectedType.value.show_expired_placeholder),
        items: selectedItems.value.map(({ source_type, source_id }, sort_order) => ({ source_type, source_id, sort_order })),
    };
    router.put(`/subscription-types/${selectedType.value.id}`, payload, { preserveScroll: true });
};

watch(() => JSON.stringify(selectedType.value), () => {
    previewResult.value = null;
    previewError.value = '';
});

watch(previewAudience, () => {
    previewResult.value = null;
    previewError.value = '';
});

watch(previewUserSearch, () => {
    previewUsers.value = [];
    selectedPreviewUser.value = null;
    previewResult.value = null;
    previewError.value = '';
});

watch(selectedPreviewUser, () => {
    previewResult.value = null;
    previewError.value = '';
});

const searchPreviewUsers = async () => {
    const search = previewUserSearch.value.trim();
    if (!search) {
        previewUsers.value = [];
        return;
    }

    previewUserSearchLoading.value = true;
    previewError.value = '';
    try {
        const { data } = await window.axios.get('/subscription-types/users', { params: { search } });
        previewUsers.value = data.users ?? [];
    } catch (error) {
        previewError.value = error.response?.data?.message ?? 'Не удалось найти пользователей.';
    } finally {
        previewUserSearchLoading.value = false;
    }
};

const runPreview = async () => {
    if (!selectedType.value || (previewAudience.value === 'user' && !selectedPreviewUser.value)) return;

    previewLoading.value = true;
    previewError.value = '';
    previewResult.value = null;
    const payload = {
        kind: selectedType.value.kind,
        format: selectedType.value.format,
        show_expired_placeholder: Boolean(selectedType.value.show_expired_placeholder),
        items: selectedItems.value.map(({ source_type, source_id }, sort_order) => ({ source_type, source_id, sort_order })),
        audience: previewAudience.value,
    };
    if (previewAudience.value === 'user') payload.user_id = selectedPreviewUser.value.id;

    try {
        const { data } = await window.axios.post('/subscription-types/' + selectedType.value.id + '/preview', payload);
        previewResult.value = data;
    } catch (error) {
        previewError.value = error.response?.data?.errors?.audience?.[0]
            ?? error.response?.data?.message
            ?? 'Не удалось собрать предпросмотр.';
    } finally {
        previewLoading.value = false;
    }
};

const createType = () => createForm.post('/subscription-types', { onSuccess: () => createForm.reset() });
const deleteType = () => {
    if (!selectedType.value || selectedType.value.is_system) return;
    if (window.confirm(`Удалить тип «${selectedType.value.name}»?`)) {
        router.delete(`/subscription-types/${selectedType.value.id}`);
    }
};
</script>

<template>
    <Head title="Сборка подписок" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Сборка подписок</h1>
                <p>Собирайте отдельные ссылки из серверов, прокси, внешних подписок и профилей Xray. Подробные настройки источников остаются на их страницах.</p>
            </div>
            <AppButton :disabled="!selectedType || !hasChanges || hasFormatConflict" @click="saveType">Сохранить состав</AppButton>
        </div>
    </section>

    <section class="subscription-types-layout">
        <aside class="page-card stack subscription-types-sidebar">
            <h2>Типы подписки</h2>
            <button
                v-for="type in localTypes"
                :key="type.id"
                class="subscription-type-tab"
                :class="{ 'is-selected': selectedId === type.id }"
                type="button"
                @click="selectedId = type.id"
            >
                <span><strong>{{ type.name }}</strong><small>/subscription/{{ type.slug }}</small></span>
                <span class="status-dot" :class="type.is_active ? 'is-active' : ''" :title="type.is_active ? 'Ссылка активна' : 'Ссылка отключена'"></span>
            </button>

            <form class="create-type-form stack" @submit.prevent="createType">
                <h3>Добавить тип</h3>
                <label class="field"><span>Название</span><AppInput v-model="createForm.name" required maxlength="120" /></label>
                <label class="field"><span>Адресный slug</span><AppInput v-model="createForm.slug" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="например: trial" /><small v-if="createForm.errors.slug" class="field-error">{{ createForm.errors.slug }}</small></label>
                <label class="field"><span>Основа</span><AppSelect v-model="createForm.kind" :options="kindOptions" /></label>
                <label class="field"><span>Формат</span><AppSelect v-model="createForm.format" :options="formatOptions" /></label>
                <AppButton type="submit" :disabled="createForm.processing || !createForm.name || !createForm.slug">Создать</AppButton>
            </form>
        </aside>

        <div v-if="selectedType" class="stack">
            <section class="page-card stack">
                <div class="page-header">
                    <div>
                        <h2>{{ selectedType.name }}</h2>
                        <p>Ссылка: <code>{{ outputUrl }}</code></p>
                    </div>
                    <AppButton v-if="!selectedType.is_system" variant="danger" @click="deleteType">Удалить тип</AppButton>
                </div>

                <div class="grid grid--two">
                    <label class="field"><span>Название</span><AppInput v-model="selectedType.name" required maxlength="120" /></label>
                    <label class="field"><span>Slug ссылки</span><AppInput v-model="selectedType.slug" required maxlength="64" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" /><small v-if="serverErrors.slug" class="field-error">{{ serverErrors.slug }}</small></label>
                    <label class="field"><span>Основа генерации</span><AppSelect v-model="selectedType.kind" :options="kindOptions" /></label>
                    <label class="field"><span>Формат</span><AppSelect v-model="selectedType.format" :options="formatOptions" /></label>
                    <label class="field-row"><AppCheckbox v-model="selectedType.is_active" /> Ссылка включена</label>
                    <label class="field-row"><AppCheckbox v-model="selectedType.show_expired_placeholder" /> Показывать заглушку при отсутствии активной подписки</label>
                </div>
                <div class="link-preview">
                    <label class="field">
                        <span>UUID пользователя для проверки ссылки</span>
                        <AppInput v-model="userUuid" placeholder="Вставьте UUID пользователя" />
                    </label>
                    <AppButton v-if="userUuid" variant="secondary" :href="outputUrl" target="_blank">Открыть ссылку</AppButton>
                </div>
                <p v-if="hasFormatConflict" class="form-error">Уберите пользовательские профили Xray из состава или выберите JSON-формат.</p>
                <p class="hint">UUID пользователя передаётся в параметре <code>token</code>. Существующие /connect и /connect-v2 продолжают работать без изменений.</p>
            </section>

            <section class="page-card stack">
                <div class="page-header">
                    <div>
                        <h2>Как подписка выглядит для пользователя</h2>
                        <p>Предпросмотр использует текущий состав и порядок, даже если изменения ещё не сохранены.</p>
                    </div>
                </div>

                <div class="grid grid--two preview-controls">
                    <label class="field">
                        <span>Кого посмотреть</span>
                        <AppSelect v-model="previewAudience" :options="previewAudienceOptions" />
                    </label>
                    <div v-if="previewAudience === 'user'" class="field">
                        <span>Найти пользователя по имени, Telegram или ID</span>
                        <div class="preview-search-row">
                            <AppInput v-model="previewUserSearch" placeholder="Например, @telegram или 12345" @keyup.enter="searchPreviewUsers" />
                            <AppButton variant="secondary" :disabled="previewUserSearchLoading || !previewUserSearch.trim()" @click="searchPreviewUsers">
                                {{ previewUserSearchLoading ? 'Поиск…' : 'Найти' }}
                            </AppButton>
                        </div>
                    </div>
                </div>

                <div v-if="previewAudience === 'user'" class="preview-user-results">
                    <button
                        v-for="user in previewUsers"
                        :key="user.id"
                        type="button"
                        class="preview-user-option"
                        :class="{ 'is-selected': selectedPreviewUser?.id === user.id }"
                        @click="selectedPreviewUser = user"
                    >
                        <span><strong>{{ user.telegram || user.name }}</strong><small>{{ user.name }} · #{{ user.id }}</small></span>
                        <span class="preview-user-status">{{ user.is_admin ? 'Администратор' : user.has_active_subscription ? 'Активная подписка' : 'Без подписки' }}<template v-if="user.has_debt"> · долг {{ user.debt_amount }}</template></span>
                    </button>
                    <p v-if="previewUserSearch.trim() && !previewUsers.length && !previewUserSearchLoading" class="hint">Пользователи не найдены.</p>
                    <p v-if="selectedPreviewUser" class="hint">Выбран: {{ selectedPreviewUser.telegram || selectedPreviewUser.name }} · #{{ selectedPreviewUser.id }}</p>
                </div>

                <div class="preview-actions">
                    <p class="hint">Вариант «без подписки или с долгом» выберет подходящего пользователя и покажет его фактический статус доступа.</p>
                    <AppButton :disabled="previewLoading || (previewAudience === 'user' && !selectedPreviewUser) || hasFormatConflict" @click="runPreview">
                        {{ previewLoading ? 'Собираем…' : 'Показать подписку' }}
                    </AppButton>
                </div>
                <p v-if="previewError" class="form-error">{{ previewError }}</p>

                <div v-if="previewResult" class="preview-result stack">
                    <div class="preview-user-summary">
                        <strong>{{ previewResult.user.telegram || previewResult.user.name }} · #{{ previewResult.user.id }}</strong>
                        <span>{{ previewResult.user.is_admin ? 'Администратор' : 'Пользователь' }}</span>
                        <span>Активная подписка: {{ previewResult.user.has_active_subscription ? 'да' : 'нет' }}</span>
                        <span>Доступ по текущим правилам: {{ previewResult.user.has_active_access ? 'есть' : 'нет' }}</span>
                        <span>Долг: {{ previewResult.user.has_debt ? previewResult.user.debt_amount : 'нет' }}</span>
                    </div>
                    <div class="page-header">
                        <div>
                            <h3>Содержимое конфигурации</h3>
                            <small>{{ previewResult.format }} · {{ previewResult.content_type }}</small>
                        </div>
                    </div>
                    <pre class="preview-content">{{ previewResult.readable_content }}</pre>
                    <details>
                        <summary>Точный ответ ссылки подписки</summary>
                        <pre class="preview-content preview-content--raw">{{ previewResult.content }}</pre>
                    </details>
                </div>
            </section>

            <section class="page-card stack">
                <div class="page-header">
                    <div>
                        <h2>Содержимое и порядок</h2>
                        <p>В список попадают только добавленные источники. Перетаскивайте строки или используйте кнопки порядка.</p>
                    </div>
                </div>

                <div class="add-source-row">
                    <label class="field">
                        <span>Добавить источник</span>
                        <AppSelect v-model="addSourceKey" :options="availableSourceOptions" option-disabled="disabled" filter placeholder="Выберите источник" />
                    </label>
                    <AppButton variant="secondary" :disabled="!addSourceKey" @click="addSource">Добавить</AppButton>
                </div>

                <div class="subscription-source-list">
                    <div
                        v-for="(item, index) in selectedItems"
                        :key="item.key"
                        class="subscription-source-row"
                        draggable="true"
                        @dragstart="dragKey = item.key"
                        @dragend="dragKey = null"
                        @dragover.prevent
                        @drop="dropOn(item.key)"
                    >
                        <span class="source-order">{{ index + 1 }}</span>
                        <span class="source-handle" aria-hidden="true">⠿</span>
                        <span class="source-copy">
                            <strong>{{ item.source?.name ?? `${item.source_type} #${item.source_id}` }}</strong>
                            <small>{{ item.source?.group ?? item.source_type }}<template v-if="item.source?.detail"> · {{ item.source.detail }}</template></small>
                        </span>
                        <span v-if="item.source && !item.source.active" class="source-state">Источник выключен</span>
                        <div class="source-actions">
                            <AppButton v-if="item.source?.edit_url" variant="secondary" :href="item.source.edit_url" target="_blank">Настроить</AppButton>
                            <AppButton variant="secondary" :disabled="index === 0" title="Переместить вверх" @click="moveItem(index, -1)">↑</AppButton>
                            <AppButton variant="secondary" :disabled="index === selectedItems.length - 1" title="Переместить вниз" @click="moveItem(index, 1)">↓</AppButton>
                            <AppButton variant="danger" title="Отключить этот источник для типа" @click="removeSource(item)">Убрать из типа</AppButton>
                        </div>
                    </div>
                    <p v-if="!selectedItems.length" class="empty-state">Пока нет источников. Добавьте сервер, прокси или подписку выше.</p>
                </div>
            </section>
        </div>
        <section v-else class="page-card empty-state">Создайте тип подписки, чтобы собрать его состав.</section>
    </section>
</template>

<style scoped>
.subscription-types-layout {
    display: grid;
    grid-template-columns: minmax(250px, 330px) minmax(0, 1fr);
    align-items: start;
    gap: 18px;
}

.subscription-types-sidebar {
    position: sticky;
    top: 18px;
}

.subscription-type-tab {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    width: 100%;
    padding: 12px 14px;
    color: inherit;
    text-align: left;
    border: 1px solid rgba(148, 163, 184, .32);
    border-radius: 12px;
    background: rgba(255, 255, 255, .7);
    cursor: pointer;
}

.subscription-type-tab.is-selected {
    border-color: var(--accent, #647de8);
    box-shadow: 0 0 0 2px rgba(100, 125, 232, .12);
}

.subscription-type-tab span:first-child,
.source-copy {
    display: grid;
    gap: 4px;
}

.subscription-type-tab small,
.source-copy small,
.hint {
    color: var(--muted, #687386);
}

.status-dot {
    width: 9px;
    height: 9px;
    flex: 0 0 auto;
    border-radius: 50%;
    background: #d28b72;
}

.status-dot.is-active { background: #4aa881; }

.create-type-form {
    padding-top: 16px;
    border-top: 1px solid rgba(148, 163, 184, .3);
}

.create-type-form h3,
.subscription-types-sidebar h2,
.page-card h2 { margin: 0; }

.add-source-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 10px;
}

.link-preview {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 10px;
}

.preview-search-row { display: flex; align-items: center; gap: 8px; }
.preview-user-results { display: grid; gap: 8px; max-height: 300px; overflow: auto; }
.preview-user-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    color: inherit;
    text-align: left;
    border: 1px solid rgba(148, 163, 184, .32);
    border-radius: 10px;
    background: rgba(255, 255, 255, .7);
    cursor: pointer;
}
.preview-user-option.is-selected { border-color: var(--accent, #647de8); box-shadow: 0 0 0 2px rgba(100, 125, 232, .12); }
.preview-user-option > span:first-child { display: grid; gap: 3px; }
.preview-user-option small, .preview-user-status { color: var(--muted, #687386); }
.preview-user-status { text-align: right; font-size: .88rem; }
.preview-actions { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
.preview-actions .hint { margin: 0; }
.preview-result { padding-top: 14px; border-top: 1px solid rgba(148, 163, 184, .3); }
.preview-user-summary { display: flex; flex-wrap: wrap; gap: 8px 16px; }
.preview-user-summary span { color: var(--muted, #687386); }
.preview-result h3 { margin: 0; }
.preview-content { max-height: 520px; overflow: auto; margin: 0; padding: 14px; white-space: pre-wrap; overflow-wrap: anywhere; border-radius: 10px; background: rgba(15, 23, 42, .06); font-size: .82rem; }
.preview-content--raw { max-height: 300px; margin-top: 10px; }
.preview-result details summary { cursor: pointer; color: var(--muted, #687386); }

.subscription-source-list { display: grid; gap: 10px; }

.subscription-source-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid rgba(148, 163, 184, .32);
    border-radius: 12px;
    background: rgba(255, 255, 255, .76);
    cursor: grab;
}

.source-order {
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    flex: 0 0 auto;
    border-radius: 9px;
    background: rgba(100, 125, 232, .1);
    font-weight: 700;
}

.source-handle { color: var(--muted, #687386); }
.source-copy { min-width: 0; flex: 1; }
.source-state { color: #a16449; font-size: .85rem; }
.source-actions { display: flex; align-items: center; gap: 6px; }

@media (max-width: 900px) {
    .subscription-types-layout { grid-template-columns: 1fr; }
    .subscription-types-sidebar { position: static; }
}

@media (max-width: 650px) {
    .subscription-source-row { flex-wrap: wrap; }
    .source-copy { flex: 1 1 calc(100% - 70px); }
    .source-actions { width: 100%; justify-content: flex-end; }
    .add-source-row { grid-template-columns: 1fr; }
    .link-preview { grid-template-columns: 1fr; }
    .preview-user-option, .preview-actions { align-items: flex-start; flex-direction: column; }
    .preview-user-status { text-align: left; }
    .preview-search-row { align-items: stretch; flex-direction: column; }
}
</style>
