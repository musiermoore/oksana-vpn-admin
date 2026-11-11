<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ groups: Array });
const createForm = useForm({ name: '' });
const create = () => createForm.post('/config-groups', { onSuccess: () => createForm.reset() });
const rename = (group) => {
    const name = window.prompt('Название группы', group.name);
    if (name?.trim()) router.patch(`/config-groups/${group.id}`, { name: name.trim() });
};
const remove = (group) => {
    if (window.confirm(`Удалить группу «${group.name}»?`)) router.delete(`/config-groups/${group.id}`);
};
</script>

<template>
    <Head title="Группы конфигураций" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>Группы конфигураций</h1><p>Объединяйте VLESS-конфиги, внешние подписки и профили Xray для desktop-клиента.</p></div></div>
        <form class="actions" @submit.prevent="create"><AppInput v-model="createForm.name" placeholder="Название группы" required /><AppButton type="submit" :disabled="createForm.processing">Добавить группу</AppButton></form>
    </section>
    <section class="table-wrap"><table><thead><tr><th>Название</th><th>Порядок</th><th>Действия</th></tr></thead><tbody>
        <tr v-for="group in props.groups" :key="group.id"><td>{{ group.name }}</td><td>{{ group.sort_order }}</td><td class="actions"><AppButton variant="secondary" @click="rename(group)">Переименовать</AppButton><AppButton variant="danger" @click="remove(group)">Удалить</AppButton></td></tr>
        <tr v-if="props.groups.length === 0"><td colspan="3">Групп пока нет.</td></tr>
    </tbody></table></section>
</template>
