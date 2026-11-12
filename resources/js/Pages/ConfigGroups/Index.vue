<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
const props = defineProps({ groups: Array });
const rows = props.groups.map((group) => ({ ...group }));
const createForm = useForm({ name: '', sort: null, is_default: false });
const create = () => createForm.post('/config-groups', { onSuccess: () => createForm.reset() });
const update = (group) => router.patch(`/config-groups/${group.id}`, {
    name: group.name,
    sort: Number(group.sort),
    is_default: Boolean(group.is_default),
});
const remove = (group) => {
    if (window.confirm(`Удалить группу «${group.name}»?`)) router.delete(`/config-groups/${group.id}`);
};
</script>

<template>
    <Head title="Группы конфигураций" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>Группы конфигураций</h1><p>Объединяйте VLESS-конфиги, внешние подписки и профили Xray для desktop-клиента.</p></div></div>
        <form class="grid grid--two" @submit.prevent="create">
            <label class="field"><span>Название группы</span><AppInput v-model="createForm.name" required /></label>
            <label class="field"><span>Сортировка</span><AppInput v-model="createForm.sort" type="number" min="0" placeholder="Автоматически" /></label>
            <label class="field-row"><input v-model="createForm.is_default" type="checkbox"> Группа по умолчанию</label>
            <div class="actions"><AppButton type="submit" :disabled="createForm.processing">Добавить группу</AppButton></div>
        </form>
    </section>
    <section class="table-wrap"><table><thead><tr><th>Название</th><th>Сортировка</th><th>По умолчанию</th><th>Действия</th></tr></thead><tbody>
        <tr v-for="group in rows" :key="group.id">
            <td><AppInput v-model="group.name" /></td>
            <td><AppInput v-model.number="group.sort" type="number" min="0" /></td>
            <td><input v-model="group.is_default" type="checkbox" aria-label="Группа по умолчанию"></td>
            <td class="actions"><AppButton variant="secondary" @click="update(group)">Сохранить</AppButton><AppButton variant="danger" @click="remove(group)">Удалить</AppButton></td>
        </tr>
        <tr v-if="props.groups.length === 0"><td colspan="3">Групп пока нет.</td></tr>
    </tbody></table></section>
</template>
