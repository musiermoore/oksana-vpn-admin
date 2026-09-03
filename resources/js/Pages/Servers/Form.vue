<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    method: String,
    server: Object,
    sort_connect_items_url: String,
});

const form = useForm({
    name: props.server?.name ?? '',
    code: props.server?.code ?? '',
    ip: props.server?.ip ?? '',
    type: props.server?.type ?? 'wireguard-old',
    is_https: props.server?.is_https ?? false,
    link_host: props.server?.link_host ?? '',
    panel_link: props.server?.panel_link ?? '',
    panel_username: props.server?.panel_username ?? '',
    panel_password: props.server?.panel_password ?? '',
    panel_api_version: props.server?.panel_api_version ?? 'v2.9.*',
    app_path: props.server?.app_path ?? '',
    ssh_private_key: '',
    ssh_public_key: props.server?.ssh_public_key ?? '',
    is_active: props.server?.is_active ?? true,
    is_ready: props.server?.is_ready ?? false,
    hide_configs_for_non_admins: props.server?.hide_configs_for_non_admins ?? false,
    prices: props.server?.prices ?? [],
    inbounds: props.server?.inbounds ?? [],
    connect_items: props.server?.connect_items ?? [],
});
const isPanelPasswordVisible = ref(false);

const sortItems = ref([...(props.server?.connect_items ?? [])]);
const sortForm = useForm({
    items: [],
});
const dragKey = ref(null);
const hasSortableItems = computed(() => sortItems.value.length > 0 && !!props.sort_connect_items_url);
const serverTypeOptions = [
    { label: 'WireGuard (legacy)', value: 'wireguard-old' },
    { label: 'WireGuard Agent API', value: 'wireguard' },
    { label: 'VLESS', value: 'vless' },
];
const panelApiVersionOptions = [
    { label: 'v2.9.*', value: 'v2.9.*' },
    { label: 'v3.2.8', value: 'v3.2.8' },
];

const moveSortItem = (targetKey) => {
    const fromIndex = sortItems.value.findIndex((item) => item.key === dragKey.value);
    const toIndex = sortItems.value.findIndex((item) => item.key === targetKey);

    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
        return;
    }

    const next = [...sortItems.value];
    const [movedItem] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, movedItem);
    sortItems.value = next;
    form.connect_items = next.map((item) => ({
        type: item.type,
        id: item.entity_id,
    }));
};

const saveConnectItemsOrder = () => {
    if (!props.sort_connect_items_url) {
        return;
    }

    sortForm.transform(() => ({
        items: sortItems.value.map((item, index) => ({
            type: item.type,
            id: item.entity_id,
            sort_order: index,
        })),
    })).post(props.sort_connect_items_url, {
        preserveScroll: true,
    });
};

form.connect_items = sortItems.value.map((item) => ({
    type: item.type,
    id: item.entity_id,
}));

const submit = () => props.method === 'patch' ? form.patch(props.submit_url) : form.post(props.submit_url);

const addPriceRow = () => {
    form.prices.push({
        effective_from: '',
        price: '',
    });
};

const removePriceRow = (index) => {
    form.prices.splice(index, 1);
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование сервера' : 'Создание сервера'" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ mode === 'edit' ? 'Редактирование сервера' : 'Создание сервера' }}</h1></div></div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field"><span>Имя</span><AppInput v-model="form.name" required /></label>
            <label class="field"><span>Сокращение</span><AppInput v-model="form.code" required /></label>
            <label class="field"><span>IP</span><AppInput v-model="form.ip" required /></label>
            <label class="field"><span>Тип</span>
                <AppSelect v-model="form.type" :options="serverTypeOptions" />
            </label>
            <label class="field"><span>Is HTTPS</span><AppCheckbox v-model="form.is_https" /></label>
            <label class="field"><span>Link Host</span><AppInput v-model="form.link_host" /></label>
            <label class="field"><span>Panel Link</span><AppInput v-model="form.panel_link" /></label>
            <label class="field"><span>Panel Username</span><AppInput v-model="form.panel_username" /></label>
            <label class="field"><span>Panel API Version</span>
                <AppSelect v-model="form.panel_api_version" :options="panelApiVersionOptions" />
            </label>
            <label class="field" style="grid-column: 1 / -1;">
                <span>Panel Password</span>
                <div class="password-field">
                    <AppInput v-model="form.panel_password" :type="isPanelPasswordVisible ? 'text' : 'password'" />
                    <button
                        class="password-toggle"
                        type="button"
                        @click="isPanelPasswordVisible = !isPanelPasswordVisible"
                    >
                        {{ isPanelPasswordVisible ? 'Скрыть пароль' : 'Показать пароль' }}
                    </button>
                </div>
            </label>
            <label class="field" style="grid-column: 1 / -1;"><span>Путь до приложения</span><AppInput v-model="form.app_path" required /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Private Key</span><AppTextarea v-model="form.ssh_private_key"  /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Public Key</span><AppTextarea v-model="form.ssh_public_key"  /></label>
            <label class="field"><span>Is Active</span><AppCheckbox v-model="form.is_active" /></label>
            <label class="field"><span>Is Ready</span><AppCheckbox v-model="form.is_ready" /></label>
            <label class="field"><span>Hide configs for non-admins</span><AppCheckbox v-model="form.hide_configs_for_non_admins" /></label>

            <div class="field" style="grid-column: 1 / -1;">
                <div class="page-header">
                    <div>
                        <span>Цены сервера</span>
                        <p class="hint">История цен по периодам. Дата означает начало действия цены.</p>
                    </div>
                    <div class="actions">
                        <AppButton variant="secondary" type="button" @click="addPriceRow">Добавить цену</AppButton>
                    </div>
                </div>

                <div v-if="form.prices.length === 0" class="hint">
                    Цены пока не добавлены.
                </div>

                <div v-else class="price-list">
                    <div v-for="(priceRow, index) in form.prices" :key="priceRow.id ?? `new-${index}`" class="price-row">
                        <label class="field">
                            <span>С даты</span>
                            <AppInput v-model="priceRow.effective_from" type="date" required />
                        </label>
                        <label class="field">
                            <span>Цена</span>
                            <AppInput v-model="priceRow.price" type="number" min="0" step="0.01" required />
                        </label>
                        <div class="actions actions--end">
                            <AppButton variant="danger" type="button" @click="removePriceRow(index)">Удалить</AppButton>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="hasSortableItems" class="field" style="grid-column: 1 / -1;">
                <div class="page-header">
                    <div>
                        <span>Порядок элементов в connect</span>
                        <p class="hint">Перетаскивайте inbound'ы и proxy, чтобы поменять порядок внутри этого сервера.</p>
                    </div>
                    <div class="actions">
                        <AppButton variant="secondary" type="button" :disabled="sortForm.processing" @click="saveConnectItemsOrder">
                            Сохранить порядок connect
                        </AppButton>
                    </div>
                </div>

                <div class="server-sort-list">
                    <div
                        v-for="item in sortItems"
                        :key="item.key"
                        class="server-sort-item"
                        draggable="true"
                        @dragstart="dragKey = item.key"
                        @dragover.prevent
                        @drop="moveSortItem(item.key)"
                    >
                        <div class="server-sort-item__handle">::</div>
                        <div class="server-sort-item__body">
                            <strong>{{ item.title }}</strong>
                            <div class="hint">{{ item.subtitle || '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field" style="grid-column: 1 / -1;">
                <span>Inbounds</span>
                <p class="hint">
                    `Connect Sort` это общий порядок элементов в подписке внутри сервера. Он делится между inbound и proxy,
                    поэтому номера inbound могут идти не подряд, если между ними есть proxy.
                </p>

                <div v-if="form.inbounds.length === 0" class="hint">
                    Для этого сервера пока нет синхронизированных inbound'ов. Сначала выполните sync с 3x-ui.
                </div>

                <table v-else>
                    <thead>
                        <tr>
                            <th>Inbound #</th>
                            <th>Connect Sort</th>
                            <th>ID</th>
                            <th>Remark</th>
                            <th>Protocol</th>
                            <th>Is Active</th>
                            <th>Is Public</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(inbound, index) in form.inbounds" :key="inbound.id">
                            <td>{{ index + 1 }}</td>
                            <td>{{ inbound.sort_order }}</td>
                            <td>{{ inbound.external_id }}</td>
                            <td>{{ inbound.remark || '—' }}</td>
                            <td>{{ inbound.protocol || '—' }}</td>
                            <td><AppCheckbox v-model="inbound.is_active" /></td>
                            <td><AppCheckbox v-model="inbound.is_public" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/servers">Назад</AppButton>
            </div>
        </form>
    </section>
</template>

<style scoped>
.password-field {
    display: flex;
    align-items: center;
    gap: 12px;
}

.password-toggle {
    border: 0;
    padding: 0;
    background: transparent;
    color: #2563eb;
    font: inherit;
    cursor: pointer;
    white-space: nowrap;
}

.password-toggle:hover {
    color: #1d4ed8;
    text-decoration: underline;
}
</style>

<style scoped>
.price-list {
    display: grid;
    gap: 12px;
    margin-top: 12px;
}

.price-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.actions--end {
    justify-content: flex-end;
}

.server-sort-list {
    display: grid;
    gap: 12px;
    margin-top: 12px;
}

.server-sort-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 14px;
    background: rgba(248, 250, 252, 0.92);
    cursor: grab;
}

.server-sort-item__handle {
    font-weight: 700;
    color: #64748b;
    user-select: none;
}

.server-sort-item__body {
    display: grid;
    gap: 4px;
}
</style>
