<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps({
    templates: { type: Array, default: () => [] },
    create_url: { type: String, required: true },
});

const pendingDelete = ref(null);
const deleting = ref(false);

const askDelete = (template) => {
    pendingDelete.value = template;
};

const cancelDelete = () => {
    if (deleting.value) return;
    pendingDelete.value = null;
};

const deleteTemplate = () => {
    if (!pendingDelete.value || deleting.value) return;
    deleting.value = true;
    router.delete(pendingDelete.value.links.destroy, {
        onFinish: () => {
            deleting.value = false;
            pendingDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Шаблоны команд" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Шаблоны команд</h1>
                <p>Переиспользуемые shell-скрипты и последовательности команд для серверной консоли.</p>
            </div>
            <div class="actions">
                <AppButton href="/servers">К серверам</AppButton>
                <AppButton :href="create_url">Добавить шаблон</AppButton>
            </div>
        </div>

        <div v-if="!templates.length" class="empty-state">
            Шаблонов пока нет. Создайте raw shell script или шаблон с командами и интерактивными ответами.
        </div>

        <div v-else class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Формат</th>
                        <th>Тайм-аут</th>
                        <th>Описание</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="template in templates" :key="template.id">
                        <td><strong>{{ template.name }}</strong></td>
                        <td>{{ template.format === 'script' ? 'Raw shell script' : 'Command blocks' }}</td>
                        <td>{{ template.timeout_seconds }} сек.</td>
                        <td>{{ template.description || '—' }}</td>
                        <td>
                            <div class="actions">
                                <Link :href="template.links.edit">Изменить</Link>
                                <AppButton variant="danger" type="button" @click="askDelete(template)">Удалить</AppButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div v-if="pendingDelete" class="delete-modal" @click.self="cancelDelete" @keydown.esc="cancelDelete">
        <section class="page-card stack delete-modal__card" role="dialog" aria-modal="true" aria-labelledby="delete-title">
            <div>
                <h2 id="delete-title">Удалить шаблон?</h2>
                <p>Шаблон «{{ pendingDelete.name }}» будет удалён. Вы уверены?</p>
            </div>
            <div class="actions">
                <AppButton variant="secondary" type="button" :disabled="deleting" @click="cancelDelete">Отмена</AppButton>
                <AppButton variant="danger" type="button" :disabled="deleting" @click="deleteTemplate">
                    {{ deleting ? 'Удаление…' : 'Удалить шаблон' }}
                </AppButton>
            </div>
        </section>
    </div>
</template>

<style scoped>
.delete-modal {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgb(15 23 42 / 58%);
}

.delete-modal__card {
    width: min(28rem, 100%);
}

.delete-modal__card h2,
.delete-modal__card p {
    margin: 0;
}

.delete-modal__card p {
    margin-top: 0.5rem;
    color: var(--text-muted, #64748b);
}
</style>
