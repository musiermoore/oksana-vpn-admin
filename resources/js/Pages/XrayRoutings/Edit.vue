<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Form from './Form.vue';

defineOptions({ layout: AppLayout });

defineProps({
    submit_url: String,
    index_url: String,
    routing: Object,
    outbound_options: Array,
    subscription_type_options: Array,
    target_tree: Object,
});
</script>

<template>
    <Head title="Редактирование правила Xray Routing" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Редактирование правила</h1>
                <p>{{ routing?.name }}</p>
            </div>
        </div>

        <Form
            method="put"
            :submit-url="submit_url"
            :cancel-href="index_url"
            :initial-routing="routing"
            :outbound-options="outbound_options"
            :subscription-type-options="subscription_type_options"
            :target-tree="target_tree"
        />
    </section>
</template>
