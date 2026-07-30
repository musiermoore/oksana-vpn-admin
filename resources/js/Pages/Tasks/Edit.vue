<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Form from './Form.vue';

defineOptions({ layout: AppLayout });

defineProps({
    submit_url: String,
    task: Object,
    statuses: Array,
});
</script>

<template>
    <Head title="Редактирование задачи" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Редактирование задачи</h1>
                <p>Здесь можно обновить статус, дедлайн или само описание.</p>
            </div>
        </div>

        <Form :submit-url="submit_url" method="put" :statuses="statuses" :initial-task="task" />
    </section>
</template>
