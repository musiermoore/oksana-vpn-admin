<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({ submit_url: String });
const form = useForm({ amount: '', description: '' });
</script>

<template>
    <Head title="Новый нерегулярный расход" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>Новый нерегулярный расход</h1></div></div>
        <form class="grid grid--two" @submit.prevent="form.post(props.submit_url)">
            <label class="field"><span>Сумма</span><AppInput v-model="form.amount" type="number" step="0.01" min="0.01" required /></label>
            <label class="field"><span>Описание</span><AppInput v-model="form.description" required /></label>
            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/non-regular-payments">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
