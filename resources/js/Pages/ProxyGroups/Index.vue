<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ groups: Array });
const createForm = useForm({ name: '' });
const selectedGroup = ref(null);
const preview = ref(null);
const steps = ref([]);
const loading = ref(false);
const applying = ref(false);
const error = ref('');
const stage = ref('preview');
const flowText = computed(() => preview.value?.flow ?? []);

const createGroup = () => createForm.post('/proxy-groups');
const renameGroup = (group) => {
    const name = window.prompt('Название группы', group.name);
    if (name && name.trim() && name !== group.name) {
        router.patch(`/proxy-groups/${group.id}`, { name: name.trim() });
    }
};
const deleteGroup = (group) => {
    if (window.confirm(`Удалить группу «${group.name}»? Прокси останутся без группы.`)) {
        router.delete(`/proxy-groups/${group.id}`);
    }
};
const openPipeline = async (group) => {
    selectedGroup.value = group;
    preview.value = null;
    steps.value = [];
    error.value = '';
    stage.value = 'preview';
    loading.value = true;
    try {
        const response = await window.axios.get(`/proxy-groups/${group.id}/configuration-preview`);
        preview.value = response.data;
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'Не удалось построить схему конфигурации.';
    } finally {
        loading.value = false;
    }
};
const applyConfiguration = async () => {
    if (!selectedGroup.value || !window.confirm('Подтвердить применение показанной конфигурации ко всем серверам группы?')) return;
    applying.value = true;
    steps.value = [];
    error.value = '';
    stage.value = 'running';
    try {
        const response = await window.axios.post(`/proxy-groups/${selectedGroup.value.id}/configure`);
        steps.value = response.data.steps ?? [];
        stage.value = response.data.success ? 'complete' : 'failed';
    } catch (exception) {
        steps.value = exception.response?.data?.steps ?? [];
        error.value = exception.response?.data?.message ?? 'Не удалось применить конфигурацию.';
        stage.value = 'failed';
    } finally {
        applying.value = false;
    }
};
const closePipeline = () => { selectedGroup.value = null; };
</script>

<template>
    <Head title="Группы прокси" />
    <section class="page-card stack">
        <div class="page-header">
            <div><h1>Группы прокси</h1><p class="hint">Группируйте каскадные цепочки и вручную применяйте HAProxy-конфигурацию.</p></div>
            <AppButton href="/proxies">Прокси</AppButton>
        </div>
        <form class="actions" @submit.prevent="createGroup">
            <AppInput v-model="createForm.name" placeholder="Название новой группы" required />
            <AppButton type="submit" :disabled="createForm.processing">Создать группу</AppButton>
        </form>
    </section>

    <section class="stack">
        <article v-for="group in groups" :key="group.id" class="page-card stack">
            <div class="page-header">
                <div><h2>{{ group.name }}</h2><p class="hint">{{ group.proxy_count }} прокси</p></div>
                <div class="actions">
                    <AppButton variant="secondary" @click="renameGroup(group)">Переименовать</AppButton>
                    <AppButton variant="danger" @click="deleteGroup(group)">Удалить</AppButton>
                    <AppButton :disabled="group.proxy_count === 0" @click="openPipeline(group)">Настроить HAProxy</AppButton>
                </div>
            </div>
            <div v-if="group.proxies.length" class="table-wrap">
                <table><thead><tr><th>Прокси</th><th>Сервер назначения</th><th>Готов</th><th>Цепочка узлов</th></tr></thead>
                    <tbody><tr v-for="proxy in group.proxies" :key="proxy.id"><td><a :href="`/proxies/${proxy.id}/edit`">{{ proxy.name }}</a></td><td>{{ proxy.server_name || '—' }}</td><td>{{ proxy.is_ready ? 'Да' : 'Нет' }}</td><td>{{ proxy.hosts.map((host) => `${host.host}:${host.port}`).join(' → ') }}</td></tr></tbody>
                </table>
            </div>
            <p v-else class="hint">Добавьте прокси в эту группу из формы прокси.</p>
        </article>
        <section v-if="groups.length === 0" class="page-card">Групп пока нет.</section>
    </section>

    <div v-if="selectedGroup" class="pipeline-backdrop" @click.self="closePipeline">
        <section class="pipeline-modal page-card stack" role="dialog" aria-modal="true" aria-labelledby="pipeline-title">
            <div class="page-header"><div><h2 id="pipeline-title">HAProxy · {{ selectedGroup.name }}</h2><p class="hint">Проверьте схему и конфигурацию перед применением.</p></div><AppButton variant="secondary" @click="closePipeline">Закрыть</AppButton></div>
            <p v-if="loading">Подготовка схемы…</p>
            <p v-if="applying">Выполняю проверку и настройку HAProxy на серверах группы…</p>
            <p v-if="error" class="pipeline-error">{{ error }}</p>
            <template v-if="preview">
                <h3>Путь трафика</h3>
                <div v-for="item in flowText" :key="item.proxy" class="pipeline-flow"><strong>{{ item.proxy }}</strong><div>{{ item.path }}</div></div>
                <h3>Инструкция для сервера</h3>
                <p>Выполните <code>systemctl edit haproxy</code> и установите override:</p>
                <pre v-for="instruction in preview.instructions" :key="instruction">{{ instruction }}</pre>
                <h3>Сгенерированная конфигурация</h3>
                <details v-for="server in preview.servers" :key="server.id" open>
                    <summary>{{ server.name }} · {{ server.host }}</summary>
                    <p>frontends/frontend.cfg</p><pre>{{ server.frontend_config }}</pre>
                    <p>backends/backend.cfg</p><pre>{{ server.backend_config }}</pre>
                </details>
                <div v-if="steps.length" class="pipeline-steps"><h3>{{ stage === 'complete' ? 'Результат' : 'Этапы и результаты' }}</h3>
                    <div v-for="(step, index) in steps" :key="`${step.server}-${index}`" class="pipeline-step" :class="step.success ? 'is-success' : 'is-failed'"><strong>{{ step.server }} · {{ step.step }}</strong><span>{{ step.message }}</span></div>
                </div>
                <div class="actions"><AppButton v-if="stage === 'preview'" :disabled="applying" @click="applyConfiguration">Да, применить</AppButton><AppButton v-if="stage === 'failed'" variant="secondary" @click="stage = 'preview'; steps = []">Вернуться к схеме</AppButton></div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.pipeline-backdrop { position: fixed; inset: 0; z-index: 1000; display: grid; place-items: center; padding: 24px; background: rgba(0, 0, 0, .55); }
.pipeline-modal { width: min(980px, 100%); max-height: 90vh; overflow: auto; }
.pipeline-modal pre { max-height: 280px; overflow: auto; padding: 14px; border-radius: 8px; background: var(--surface-muted, #111827); color: var(--text-primary, #f9fafb); white-space: pre-wrap; }
.pipeline-flow, .pipeline-step { display: grid; gap: 6px; padding: 12px; border: 1px solid var(--border, #d1d5db); border-radius: 8px; overflow-wrap: anywhere; }
.pipeline-steps { display: grid; gap: 10px; }
.pipeline-step.is-success { border-color: #22a06b; }
.pipeline-step.is-failed, .pipeline-error { color: #b42318; border-color: #b42318; }
</style>
