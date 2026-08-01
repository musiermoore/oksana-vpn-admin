<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    subscription: Object,
});

const form = useForm({
    start_date: props.subscription.start_date,
    end_date: props.subscription.end_date,
});

const submit = () => form.put(props.submit_url);
</script>

<template>
    <Head title="Редактирование подписки" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Редактирование подписки</h1>
                <p>{{ subscription.user?.full_name || 'Участник не найден' }}</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Дата начала</span>
                <AppInput v-model="form.start_date" type="date" required />
            </label>

            <label class="field">
                <span>Дата окончания</span>
                <AppInput v-model="form.end_date" type="date" required />
            </label>

            <label class="field">
                <span>Стоимость</span>
                <AppInput :value="subscription.price" type="number" step="0.01" disabled />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/subscriptions">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
