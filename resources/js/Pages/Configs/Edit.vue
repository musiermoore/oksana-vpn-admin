<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: String,
    config: Object,
    users: Array,
    servers: Array,
});

const form = useForm({
    user_id: props.config.user?.id ?? '',
    description: props.config.description ?? '',
});

const userOptions = props.users.map((user) => ({
    label: user.name,
    value: user.id,
}));
</script>

<template>
    <Head title="Редактирование конфига" />

    <section class="page-card stack">
        <div class="page-header"><div><h1>Редактирование конфига</h1></div></div>

        <form class="grid grid--two" @submit.prevent="form.put(submit_url)">
            <label class="field">
                <span>Сервер</span>
                <AppInput :value="config.server.name" readonly />
            </label>

            <label class="field">
                <span>Участник</span>
                <AppSelect v-model="form.user_id" :options="userOptions" />
            </label>

            <label class="field">
                <span>Название</span>
                <AppInput :value="config.name" readonly />
            </label>

            <label class="field">
                <span>Адрес</span>
                <AppInput :value="config.address" readonly />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description"  />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/configs">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
