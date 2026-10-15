<script setup>
import { onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-es-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    specUrl: {
        type: String,
        required: true,
    },
});

const swaggerRoot = ref(null);
onMounted(() => {
    SwaggerUIBundle({
        url: props.specUrl,
        domNode: swaggerRoot.value,
        deepLinking: true,
        displayRequestDuration: true,
        presets: [SwaggerUIBundle.presets.apis],
        layout: 'BaseLayout',
    });
});
</script>

<template>
    <Head title="Telegram Mini App API" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Telegram Mini App API</h1>
                <p>OpenAPI contract for the Laravel API and its Go replacement.</p>
            </div>
        </div>
    </section>

    <section class="page-card api-docs-card">
        <div ref="swaggerRoot" class="api-docs-swagger" />
    </section>
</template>

<style>
.api-docs-card {
    overflow: hidden;
    padding: 0;
}

.api-docs-swagger {
    min-height: 70vh;
    padding: 1rem;
}

.api-docs-swagger .swagger-ui {
    color: inherit;
}

.api-docs-swagger .info {
    margin: 1rem 0 2rem;
}
</style>
