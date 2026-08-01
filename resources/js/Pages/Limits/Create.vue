<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    configs: Array,
    speed_limits: Array,
});

const form = useForm({
    config_id: props.configs[0]?.id ?? '',
    amount: props.speed_limits[0]?.amount ?? '',
});

const configOptions = props.configs.map((config) => ({
    label: `${config.name} - ${config.user?.full_name}`,
    value: config.id,
}));

const speedLimitOptions = props.speed_limits.map((limit) => ({
    label: limit.name,
    value: limit.amount,
}));
</script>

<template>
    <Head title="Создание ограничения" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создание ограничения</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Конфиг</span>
                <AppSelect v-model="form.config_id" :options="configOptions" />
            </label>

            <label class="field">
                <span>Ограничение</span>
                <AppSelect v-model="form.amount" :options="speedLimitOptions" />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/limits">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
