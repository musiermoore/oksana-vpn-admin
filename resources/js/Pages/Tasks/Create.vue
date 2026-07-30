<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Form from './Form.vue';

defineOptions({ layout: AppLayout });

defineProps({
    submit_url: String,
    statuses: Array,
});
</script>

<template>
    <Head title="Создание задачи" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Создание задачи</h1>
                <p>Добавь задачу в общий список, чтобы к ней можно было вернуться позже.</p>
            </div>
        </div>

        <Form :submit-url="submit_url" :statuses="statuses" />
    </section>
</template>
