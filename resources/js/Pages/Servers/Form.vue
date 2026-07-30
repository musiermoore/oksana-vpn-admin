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
});

const sortItems = ref([...(props.server?.connect_items ?? [])]);
const sortForm = useForm({
    items: [],
});
const dragKey = ref(null);
const hasSortableItems = computed(() => sortItems.value.length > 0 && !!props.sort_connect_items_url);

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
            <label class="field"><span>Имя</span><input v-model="form.name" required></label>
            <label class="field"><span>Сокращение</span><input v-model="form.code" required></label>
            <label class="field"><span>IP</span><input v-model="form.ip" required></label>
            <label class="field"><span>Тип</span>
                <select v-model="form.type">
                    <option value="wireguard-old">WireGuard (legacy)</option>
                    <option value="wireguard">WireGuard Agent API</option>
                    <option value="vless">VLESS</option>
                </select>
            </label>
            <label class="field"><span>Is HTTPS</span><input v-model="form.is_https" type="checkbox"></label>
            <label class="field"><span>Link Host</span><input v-model="form.link_host"></label>
            <label class="field"><span>Panel Link</span><input v-model="form.panel_link"></label>
            <label class="field"><span>Panel Username</span><input v-model="form.panel_username"></label>
            <label class="field"><span>Panel API Version</span>
                <select v-model="form.panel_api_version">
                    <option value="v2.9.*">v2.9.*</option>
                    <option value="v3.2.8">v3.2.8</option>
                </select>
            </label>
            <label class="field" style="grid-column: 1 / -1;"><span>Panel Password</span><input v-model="form.panel_password" type="password"></label>
            <label class="field" style="grid-column: 1 / -1;"><span>Путь до приложения</span><input v-model="form.app_path" required></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Private Key</span><textarea v-model="form.ssh_private_key" /></label>
            <label class="field" style="grid-column: 1 / -1;"><span>SSH Public Key</span><textarea v-model="form.ssh_public_key" /></label>
            <label class="field"><span>Is Active</span><input v-model="form.is_active" type="checkbox"></label>
            <label class="field"><span>Is Ready</span><input v-model="form.is_ready" type="checkbox"></label>
            <label class="field"><span>Hide configs for non-admins</span><input v-model="form.hide_configs_for_non_admins" type="checkbox"></label>

            <div class="field" style="grid-column: 1 / -1;">
                <div class="page-header">
                    <div>
                        <span>Цены сервера</span>
                        <p class="hint">История цен по периодам. Дата означает начало действия цены.</p>
                    </div>
                    <div class="actions">
                        <button class="button button--secondary" type="button" @click="addPriceRow">Добавить цену</button>
                    </div>
                </div>

                <div v-if="form.prices.length === 0" class="hint">
                    Цены пока не добавлены.
                </div>

                <div v-else class="price-list">
                    <div v-for="(priceRow, index) in form.prices" :key="priceRow.id ?? `new-${index}`" class="price-row">
                        <label class="field">
                            <span>С даты</span>
                            <input v-model="priceRow.effective_from" type="date" required>
                        </label>
                        <label class="field">
                            <span>Цена</span>
                            <input v-model="priceRow.price" type="number" min="0" step="0.01" required>
                        </label>
                        <div class="actions actions--end">
                            <button class="button button--danger" type="button" @click="removePriceRow(index)">Удалить</button>
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
                        <button class="button button--secondary" type="button" :disabled="sortForm.processing" @click="saveConnectItemsOrder">
                            Сохранить порядок connect
                        </button>
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

                <div v-if="form.inbounds.length === 0" class="hint">
                    Для этого сервера пока нет синхронизированных inbound'ов. Сначала выполните sync с 3x-ui.
                </div>

                <table v-else>
                    <thead>
                        <tr>
                            <th>Sort</th>
                            <th>ID</th>
                            <th>Remark</th>
                            <th>Protocol</th>
                            <th>Is Active</th>
                            <th>Is Public</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inbound in form.inbounds" :key="inbound.id">
                            <td>{{ inbound.sort_order }}</td>
                            <td>{{ inbound.external_id }}</td>
                            <td>{{ inbound.remark || '—' }}</td>
                            <td>{{ inbound.protocol || '—' }}</td>
                            <td><input v-model="inbound.is_active" type="checkbox"></td>
                            <td><input v-model="inbound.is_public" type="checkbox"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/servers">Назад</Link>
            </div>
        </form>
    </section>
</template>

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
