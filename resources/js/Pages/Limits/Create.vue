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
</script>

<template>
    <Head title="Создание ограничения" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Создание ограничения</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.post(submit_url)">
            <label class="field">
                <span>Конфиг</span>
                <select v-model="form.config_id">
                    <option v-for="config in configs" :key="config.id" :value="config.id">
                        {{ config.name }} - {{ config.user?.full_name }}
                    </option>
                </select>
            </label>

            <label class="field">
                <span>Ограничение</span>
                <select v-model="form.amount">
                    <option v-for="limit in speed_limits" :key="limit.amount" :value="limit.amount">{{ limit.name }}</option>
                </select>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/limits">Назад</Link>
            </div>
        </form>
    </section>
</template>
