<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    type: { type: Object, default: null },
    catalog: { type: Array, default: () => [] },
});

const page = usePage();
const errors = computed(() => page.props.errors ?? {});
const localType = ref(props.type
    ? { ...props.type, items: props.type.items.map((item) => ({ ...item })) }
    : {
        id: null,
        name: '',
        slug: '',
        kind: 'connect',
        format: 'uri',
        is_active: true,
        requires_user_token: true,
        show_expired_placeholder: true,
        is_system: false,
        items: [],
    });
const isEdit = computed(() => Number(localType.value.id) > 0);
const saving = ref(false);
const userUuid = ref('');
const dragKey = ref(null);
const previewAudience = ref('active');
const previewVariant = ref('incy-happ');
const previewUserSearch = ref('');
const previewUsers = ref([]);
const selectedPreviewUser = ref(null);
const previewResult = ref(null);
const previewLoading = ref(false);
const previewUserSearchLoading = ref(false);
const previewError = ref('');

watch(() => props.type, (type) => {
    if (type) {
        localType.value = { ...type, items: type.items.map((item) => ({ ...item })) };
    }
});

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
const previewVariantOptions = [
    { value: 'incy-happ', label: 'Incy / Happ' },
    { value: 'oksana', label: 'Oksana Client' },
    { value: 'json', label: 'JSON' },
];
const previewAudienceOptions = [
    { value: 'active', label: 'Активный пользователь' },
    { value: 'admin', label: 'Администратор' },
    { value: 'inactive', label: 'Без активной подписки или с долгом' },
    { value: 'user', label: 'Выбрать пользователя' },
];

const pageTitle = computed(() => isEdit.value ? 'Редактирование типа подписки' : 'Новый тип подписки');
const sourceKey = (item) => item.source_type + ':' + item.source_id;
const orderedItemData = ({ source_type, source_id, is_free }, sort_order) => ({
    source_type, source_id, sort_order, is_free: Boolean(is_free),
});
const sourceAccessBadge = (source) => {
    if (!['external_subscription', 'xray_custom_config'].includes(source.source_type)) return null;
    if (!source.is_free) return { label: 'Платная · нужен активный доступ', isFree: false };
    if (source.source_type === 'xray_custom_config' && !source.is_public) {
        return { label: 'Бесплатная · назначенным (с токеном)', isFree: true };
    }

    return {
        label: source.source_type === 'xray_custom_config'
            ? 'Бесплатная · доступна без доступа (с токеном)'
            : 'Бесплатная · доступна без доступа',
        isFree: true,
    };
};
const catalogByKey = computed(() => new Map(props.catalog.map((source) => [
    source.source_type + ':' + source.source_id,
    source,
])));
const selectedItems = computed(() => localType.value.items
    .slice()
    .sort((left, right) => left.sort_order - right.sort_order)
    .map((item) => ({ ...item, key: sourceKey(item), source: catalogByKey.value.get(sourceKey(item)) })));
const selectedSourceKeys = computed(() => new Set(localType.value.items.map(sourceKey)));
const availableSources = computed(() => props.catalog.filter((source) => {
    const key = source.source_type + ':' + source.source_id;
    if (selectedSourceKeys.value.has(key)) return true;
    if (!localType.value.requires_user_token) {
        return source.source_type === 'external_subscription';
    }

    return source.source_type !== 'xray_custom_config' || localType.value.format === 'json';
}));
const sourceGroups = computed(() => availableSources.value.reduce((groups, source) => {
    (groups[source.group] ??= []).push(source);
    return groups;
}, {}));
const availableSourceGroups = computed(() => Object.entries(sourceGroups.value).map(([label, sources]) => ({
    label,
    items: sources.map((source) => ({
        value: source.source_type + ':' + source.source_id,
        label: source.name + (source.detail ? ' · ' + source.detail : '')
            + (sourceAccessBadge(source) ? ' · ' + sourceAccessBadge(source).label : ''),
    })),
})));
const sourceSelection = computed({
    get: () => localType.value.items.map(sourceKey),
    set: syncSourceSelection,
});
const hasFormatConflict = computed(() => localType.value.format !== 'json'
    && selectedItems.value.some((item) => item.source_type === 'xray_custom_config'));
const hasStaticSourceConflict = computed(() => !localType.value.requires_user_token
    && (selectedItems.value.length === 0 || selectedItems.value.some((item) =>
        item.source_type !== 'external_subscription' || (!item.is_free && item.source?.is_free !== true))));
const outputUrl = computed(() => localType.value.requires_user_token
    ? '/subscription/' + localType.value.slug + '?token=' + (userUuid.value || '{user_uuid}')
    : '/subscription/' + localType.value.slug);
const hasChanges = computed(() => !props.type
    || JSON.stringify(props.type) !== JSON.stringify(localType.value));
const canSave = computed(() => Boolean(localType.value.name.trim())
    && Boolean(localType.value.slug.trim())
    && !saving.value
    && !hasFormatConflict.value
    && !hasStaticSourceConflict.value
    && (!isEdit.value || hasChanges.value));

watch(() => localType.value.requires_user_token, (requiresUserToken) => {
    if (!requiresUserToken) {
        localType.value.show_expired_placeholder = false;
    }
    previewResult.value = null;
    previewError.value = '';
});

function syncSourceSelection(values) {
    const selectedKeys = Array.isArray(values) ? [...new Set(values)] : [];
    const selectedKeySet = new Set(selectedKeys);
    localType.value.items = localType.value.items.filter((item) => selectedKeySet.has(sourceKey(item)));
    const existing = new Set(localType.value.items.map(sourceKey));

    selectedKeys.forEach((key) => {
        const source = catalogByKey.value.get(key);
        if (!source || existing.has(key)) return;

        localType.value.items.push({
            source_type: source.source_type,
            source_id: Number(source.source_id),
            sort_order: localType.value.items.length,
            is_free: false,
        });
        existing.add(key);
    });
    normalizeOrder();
}

const removeSource = (item) => {
    localType.value.items = localType.value.items.filter((source) => sourceKey(source) !== item.key);
    normalizeOrder();
};

const reorderItems = (fromIndex, toIndex) => {
    const items = selectedItems.value.slice();
    if (fromIndex < 0 || fromIndex >= items.length || toIndex < 0 || toIndex >= items.length || fromIndex === toIndex) return;
    const [moving] = items.splice(fromIndex, 1);
    items.splice(toIndex, 0, moving);
    localType.value.items = items.map(orderedItemData);
};

const moveItem = (index, offset) => reorderItems(index, index + offset);

function setItemOrder(index, event) {
    const requestedPosition = Number.parseInt(event.target.value, 10);
    if (!Number.isFinite(requestedPosition)) {
        event.target.value = String(index + 1);
        return;
    }

    const targetIndex = Math.max(0, Math.min(selectedItems.value.length - 1, requestedPosition - 1));
    event.target.value = String(targetIndex + 1);
    reorderItems(index, targetIndex);
}

const dropOn = (targetKey) => {
    const fromIndex = selectedItems.value.findIndex((item) => item.key === dragKey.value);
    const toIndex = selectedItems.value.findIndex((item) => item.key === targetKey);
    reorderItems(fromIndex, toIndex);
};

function normalizeOrder() {
    localType.value.items = selectedItems.value.map(orderedItemData);
}

function setItemFree(key, isFree) {
    const item = localType.value.items.find((source) => sourceKey(source) === key);
    if (item) item.is_free = isFree;
}

const saveType = () => {
    if (!canSave.value) return;

    const payload = {
        name: localType.value.name,
        slug: localType.value.slug,
        kind: localType.value.kind,
        format: localType.value.format,
        is_active: Boolean(localType.value.is_active),
        requires_user_token: Boolean(localType.value.requires_user_token),
        show_expired_placeholder: Boolean(localType.value.show_expired_placeholder),
        items: selectedItems.value.map(orderedItemData),
    };
    saving.value = true;
    const options = { preserveScroll: true, onFinish: () => { saving.value = false; } };

    if (isEdit.value) {
        router.put('/subscription-types/' + localType.value.id, payload, options);
        return;
    }

    router.post('/subscription-types', payload, options);
};

const deleteType = () => {
    if (!isEdit.value || localType.value.is_system) return;
    if (window.confirm('Удалить тип «' + localType.value.name + '»?')) {
        router.delete('/subscription-types/' + localType.value.id);
    }
};

const previewContext = computed(() => JSON.stringify([
    localType.value,
    previewAudience.value,
    selectedPreviewUser.value?.id,
]));

watch(previewContext, () => {
    previewResult.value = null;
    previewError.value = '';
});

watch(previewUserSearch, () => {
    previewUsers.value = [];
    selectedPreviewUser.value = null;
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
    if (localType.value.requires_user_token && previewAudience.value === 'user' && !selectedPreviewUser.value) return;

    const context = previewContext.value;
    previewLoading.value = true;
    previewError.value = '';
    previewResult.value = null;
    const payload = {
        kind: localType.value.kind,
        format: localType.value.format,
        requires_user_token: Boolean(localType.value.requires_user_token),
        show_expired_placeholder: Boolean(localType.value.show_expired_placeholder),
        items: selectedItems.value.map(orderedItemData),
    };
    if (localType.value.requires_user_token) {
        payload.audience = previewAudience.value;
        if (previewAudience.value === 'user') payload.user_id = selectedPreviewUser.value.id;
    }

    const endpoint = isEdit.value
        ? '/subscription-types/' + localType.value.id + '/preview'
        : '/subscription-types/preview';
    try {
        const { data } = await window.axios.post(endpoint, payload);
        if (context === previewContext.value) previewResult.value = data;
    } catch (error) {
        if (context !== previewContext.value) return;
        previewError.value = error.response?.data?.errors?.audience?.[0]
            ?? error.response?.data?.message
            ?? 'Не удалось собрать предпросмотр.';
    } finally {
        previewLoading.value = false;
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <AppButton variant="ghost" href="/subscription-types">← Все типы подписки</AppButton>
                <h1>{{ pageTitle }}<template v-if="isEdit">: {{ localType.name }}</template></h1>
                <p>Состав и порядок задаются здесь. Подробные настройки источников остаются на их страницах.</p>
            </div>
            <div class="page-actions">
                <AppButton v-if="isEdit && !localType.is_system" variant="danger" @click="deleteType">Удалить тип</AppButton>
                <AppButton :disabled="!canSave" @click="saveType">{{ saving ? 'Сохраняем…' : 'Сохранить' }}</AppButton>
            </div>
        </div>
        <p v-if="hasFormatConflict" class="form-error">Уберите пользовательские профили Xray из состава или выберите JSON-формат.</p>
        <p v-if="hasStaticSourceConflict" class="form-error">Для статической ссылки добавьте хотя бы одну бесплатную внешнюю подписку и уберите остальные источники.</p>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2>1. Информация о типе</h2>
                <p>Название, адрес и формат этой подписки.</p>
            </div>
        </div>

        <div class="grid grid--two">
            <label class="field">
                <span>Название</span>
                <AppInput v-model="localType.name" required maxlength="120" />
                <small v-if="errors.name" class="field-error">{{ errors.name }}</small>
            </label>
            <label class="field">
                <span>Slug ссылки</span>
                <AppInput v-model="localType.slug" required maxlength="64" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" />
                <small v-if="errors.slug" class="field-error">{{ errors.slug }}</small>
            </label>
            <label class="field">
                <span>Основа генерации</span>
                <AppSelect v-model="localType.kind" :options="kindOptions" />
            </label>
            <label class="field">
                <span>Формат</span>
                <AppSelect v-model="localType.format" :options="formatOptions" />
            </label>
        </div>

        <div class="type-toggle-row">
            <label class="type-toggle">
                <AppCheckbox v-model="localType.is_active" />
                <span class="type-toggle-copy">
                    <strong>Ссылка активна</strong>
                    <small>Отключённый тип не выдаёт конфигурацию.</small>
                </span>
            </label>
            <label class="type-toggle">
                <AppCheckbox v-model="localType.show_expired_placeholder" :disabled="!localType.requires_user_token" />
                <span class="type-toggle-copy">
                    <strong>Показывать заглушку без доступа</strong>
                    <small>Для персональных ссылок без активной подписки.</small>
                </span>
            </label>
            <label class="type-toggle">
                <AppCheckbox v-model="localType.requires_user_token" />
                <span class="type-toggle-copy">
                    <strong>Требовать токен пользователя</strong>
                    <small>{{ localType.requires_user_token ? 'Персональная ссылка по UUID.' : 'Статическая ссылка, доступная без UUID.' }}</small>
                </span>
            </label>
        </div>

        <div v-if="localType.requires_user_token" class="link-preview">
            <label class="field">
                <span>UUID пользователя для открытия ссылки</span>
                <AppInput v-model="userUuid" placeholder="Вставьте UUID пользователя" />
            </label>
            <AppButton v-if="isEdit && userUuid" variant="secondary" :href="outputUrl" target="_blank">Открыть ссылку</AppButton>
        </div>
        <div v-else class="static-link-preview">
            <span>Статическая ссылка: <code>{{ outputUrl }}</code></span>
            <AppButton v-if="isEdit" variant="secondary" :href="outputUrl" target="_blank">Открыть ссылку</AppButton>
        </div>
        <p v-if="localType.requires_user_token" class="hint">UUID передаётся в параметре <code>token</code>. Существующие /connect и /connect-v2 продолжают работать без изменений.</p>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2>2. Наполнение подписки</h2>
                <p>Выбирайте источники в списке, затем меняйте порядок перетаскиванием или номером позиции. Соседние источники сдвинутся автоматически.</p>
            </div>
        </div>

        <label class="field">
            <span>Источники, включённые в подписку</span>
            <MultiSelect
                v-model="sourceSelection"
                :options="availableSourceGroups"
                option-label="label"
                option-value="value"
                option-group-label="label"
                option-group-children="items"
                display="chip"
                filter
                fluid
                :max-selected-labels="4"
                placeholder="Выберите один или несколько источников"
            />
        </label>
        <p v-if="!localType.requires_user_token" class="hint">Для статической ссылки выберите бесплатную внешнюю подписку или отметьте источник как Free.</p>
        <p v-if="hasStaticSourceConflict" class="form-error">Удалите локальные, платные или пользовательские источники, чтобы сделать ссылку статической.</p>

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
                <input
                    class="source-position"
                    type="number"
                    min="1"
                    :max="selectedItems.length"
                    step="1"
                    :value="index + 1"
                    :aria-label="'Позиция ' + (item.source?.name ?? 'источника')"
                    title="Введите номер позиции"
                    @dragstart.stop.prevent
                    @change="setItemOrder(index, $event)"
                >
                <span class="source-handle" aria-hidden="true">⠿</span>
                <span class="source-copy">
                    <span class="source-copy-title">
                        <strong>{{ item.source?.name ?? item.source_type + ' #' + item.source_id }}</strong>
                        <span
                            v-if="item.is_free || (item.source && sourceAccessBadge(item.source))"
                            class="source-badge"
                            :class="{ 'source-badge--free': item.is_free || sourceAccessBadge(item.source)?.isFree }"
                        >{{ item.is_free ? 'Бесплатная в этой подписке' : sourceAccessBadge(item.source).label }}</span>
                    </span>
                    <small>{{ item.source?.group ?? item.source_type }}<template v-if="item.source?.detail"> · {{ item.source.detail }}</template></small>
                </span>
                <span v-if="item.source && !item.source.active" class="source-state">Источник выключен</span>
                <span v-else-if="item.source?.source_type === 'external_subscription' && item.source.ready === false" class="source-state">Источник не готов: доступен только администратору</span>
                <div class="source-actions">
                    <label class="type-toggle source-free" title="Бесплатный доступ к источнику в этой подписке">
                        <AppCheckbox :model-value="Boolean(item.is_free)" @update:model-value="setItemFree(item.key, $event)" />
                        <span>Free</span>
                    </label>
                    <AppButton v-if="item.source?.edit_url" variant="secondary" :href="item.source.edit_url" target="_blank">Настроить</AppButton>
                    <AppButton variant="secondary" :disabled="index === 0" title="Переместить вверх" @click="moveItem(index, -1)">↑</AppButton>
                    <AppButton variant="secondary" :disabled="index === selectedItems.length - 1" title="Переместить вниз" @click="moveItem(index, 1)">↓</AppButton>
                    <AppButton
                        variant="ghost"
                        rounded
                        size="small"
                        title="Убрать из типа"
                        :aria-label="'Убрать ' + (item.source?.name ?? 'источник') + ' из типа'"
                        @click="removeSource(item)"
                    >
                        <AppIcon name="trash" />
                    </AppButton>
                </div>
            </div>
            <p v-if="!selectedItems.length" class="empty-state">Выберите сервер, прокси или подписку выше.</p>
        </div>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2>3. Предпросмотр для пользователя</h2>
                <p>Предпросмотр использует текущий состав и порядок, даже если изменения ещё не сохранены.</p>
            </div>
        </div>

        <template v-if="localType.requires_user_token">
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
                <AppButton :disabled="previewLoading || (previewAudience === 'user' && !selectedPreviewUser) || hasFormatConflict || hasStaticSourceConflict" @click="runPreview">
                    {{ previewLoading ? 'Собираем…' : 'Показать подписку' }}
                </AppButton>
            </div>
        </template>
        <div v-else class="preview-actions">
            <p class="hint">Предпросмотр статической конфигурации без пользовательского токена.</p>
            <AppButton :disabled="previewLoading || hasStaticSourceConflict" @click="runPreview">
                {{ previewLoading ? 'Собираем…' : 'Показать подписку' }}
            </AppButton>
        </div>

        <p v-if="previewError" class="form-error">{{ previewError }}</p>

        <div v-if="previewResult" class="preview-result stack">
            <div v-if="previewResult.is_static" class="preview-user-summary">
                <strong>Статическая конфигурация</strong>
                <span>Токен пользователя не требуется</span>
            </div>
            <div v-else-if="previewResult.user" class="preview-user-summary">
                <strong>{{ previewResult.user.telegram || previewResult.user.name }} · #{{ previewResult.user.id }}</strong>
                <span>{{ previewResult.user.is_admin ? 'Администратор' : 'Пользователь' }}</span>
                <span>Активная подписка: {{ previewResult.user.has_active_subscription ? 'да' : 'нет' }}</span>
                <span>Доступ по текущим правилам: {{ previewResult.user.has_active_access ? 'есть' : 'нет' }}</span>
                <span>Долг: {{ previewResult.user.has_debt ? previewResult.user.debt_amount : 'нет' }}</span>
            </div>
            <label class="field preview-variant">
                <span>Вид предпросмотра</span>
                <AppSelect v-model="previewVariant" :options="previewVariantOptions" />
            </label>
            <template v-if="previewVariant === 'json'">
                <p v-if="!previewResult.content_type.startsWith('application/json')" class="hint">Предпросмотр в формате Xray JSON.</p>
                <pre class="preview-content">{{ previewResult.json }}</pre>
            </template>
            <ol v-else-if="previewResult.profiles.length" class="preview-profiles">
                <li v-for="(profile, index) in previewResult.profiles" :key="index" class="preview-profile">
                    <strong>{{ profile.name }}</strong>
                    <template v-if="previewVariant === 'oksana'">
                        <small v-if="profile.category" class="preview-category">{{ profile.category }}</small>
                        <small v-if="profile.subtitle" class="preview-subtitle">{{ profile.subtitle }}</small>
                    </template>
                </li>
            </ol>
            <p v-else class="empty-state">Для выбранного пользователя нет конфигураций.</p>
            <details>
                <summary>Точный ответ ссылки подписки</summary>
                <small>{{ previewResult.format }} · {{ previewResult.content_type }}</small>
                <pre class="preview-content preview-content--raw">{{ previewResult.content }}</pre>
            </details>
            <details v-if="previewResult.readable_content !== previewResult.content">
                <summary>Декодированные ссылки</summary>
                <pre class="preview-content preview-content--raw">{{ previewResult.readable_content }}</pre>
            </details>
        </div>
    </section>

    <div class="form-footer">
        <AppButton variant="secondary" href="/subscription-types">Отмена</AppButton>
        <AppButton :disabled="!canSave" @click="saveType">{{ saving ? 'Сохраняем…' : 'Сохранить' }}</AppButton>
    </div>
</template>

<style scoped>
.page-actions,
.form-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
}

.link-preview,
.preview-search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 10px;
}

.type-toggle-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    align-items: center;
    gap: 16px;
}

.type-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.type-toggle-copy { display: grid; gap: 3px; }
.type-toggle-copy small { color: var(--muted, #687386); }
.static-link-preview { display: flex; align-items: center; justify-content: space-between; gap: 12px; }

.subscription-source-list,
.preview-user-results {
    display: grid;
    gap: 10px;
}

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

.source-position {
    width: 58px;
    min-width: 58px;
    height: 36px;
    flex: 0 0 auto;
    padding: 0 6px;
    border: 1px solid rgba(148, 163, 184, .45);
    border-radius: 9px;
    background: rgba(100, 125, 232, .08);
    color: inherit;
    font: inherit;
    text-align: center;
}

.source-handle { color: var(--muted, #687386); }
.source-copy { min-width: 0; flex: 1; display: grid; gap: 4px; }
.source-copy-title { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.source-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; background: rgba(180, 83, 9, .1); color: #9a5b16; font-size: .75rem; font-weight: 600; }
.source-badge--free { background: rgba(5, 150, 105, .12); color: #087450; }
.source-copy small, .hint { color: var(--muted, #687386); }
.source-state { color: #a16449; font-size: .85rem; }
.source-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.source-free { gap: 6px; margin-right: 6px; }

.preview-user-results {
    max-height: 300px;
    overflow: auto;
}

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
.preview-variant { max-width: 300px; }
.preview-profiles { display: grid; gap: 8px; margin: 0; padding-left: 30px; }
.preview-profile { padding: 10px 12px; border: 1px solid rgba(148, 163, 184, .32); border-radius: 10px; }
.preview-profile::marker { color: var(--muted, #687386); }
.preview-category { display: inline-block; margin-left: 8px; color: var(--muted, #687386); }
.preview-subtitle { display: block; margin-top: 3px; color: var(--muted, #687386); }
.preview-content { max-height: 520px; overflow: auto; margin: 0; padding: 14px; white-space: pre-wrap; overflow-wrap: anywhere; border-radius: 10px; background: rgba(15, 23, 42, .06); font-size: .82rem; }
.preview-content--raw { max-height: 300px; margin-top: 10px; }
.preview-result details summary { cursor: pointer; color: var(--muted, #687386); }
.form-footer { padding-bottom: 10px; }

@media (max-width: 650px) {
    .subscription-source-row,
    .preview-user-option,
    .preview-actions {
        align-items: flex-start;
        flex-direction: column;
    }

    .source-actions { width: 100%; justify-content: flex-end; }
    .preview-user-status { text-align: left; }
    .link-preview,
    .preview-search-row {
        grid-template-columns: 1fr;
    }

    .type-toggle-row { grid-template-columns: 1fr; }
    .static-link-preview { align-items: flex-start; flex-direction: column; }
}
</style>
