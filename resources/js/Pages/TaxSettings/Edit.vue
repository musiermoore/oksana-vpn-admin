<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({
            login: '',
            password: '',
            service_name: 'Настройка сетевой конфигурации',
        }),
    },
});

const form = useForm({
    login: props.settings.login ?? '',
    password: props.settings.password ?? '',
    service_name: props.settings.service_name ?? 'Настройка сетевой конфигурации',
});

const submit = () => form.put('/tax-settings');
</script>

<template>
    <Head title="Tax Settings" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Tax Settings</h1>
                <p>Логин/пароль Moy Nalog и дефолтное имя услуги для `services.*.name`.</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span>Логин (ИНН)</span>
                <input v-model="form.login" type="text" autocomplete="off">
                <small v-if="form.errors.login" class="field-error">{{ form.errors.login }}</small>
            </label>

            <label class="field">
                <span>Пароль</span>
                <input v-model="form.password" type="password" autocomplete="new-password">
                <small v-if="form.errors.password" class="field-error">{{ form.errors.password }}</small>
            </label>

            <label class="field">
                <span>Название услуги</span>
                <input v-model="form.service_name" type="text">
                <small class="muted">По умолчанию: `Настройка сетевой конфигурации`.</small>
                <small v-if="form.errors.service_name" class="field-error">{{ form.errors.service_name }}</small>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
            </div>
        </form>
    </section>
</template>
