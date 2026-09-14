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
    <Head title="Создание правила Xray Routing" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Создание правила</h1>
                <p>Добавьте routing rule для connect JSON subscriptions.</p>
            </div>
        </div>

        <Form
            :submit-url="submit_url"
            :cancel-href="index_url"
            :initial-routing="routing"
            :outbound-options="outbound_options"
            :subscription-type-options="subscription_type_options"
            :target-tree="target_tree"
        />
    </section>
</template>
