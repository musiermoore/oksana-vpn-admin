<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    import_url: String,
    active_settings: Object,
    routings: Array,
});

const form = useForm({
    settings_json: '',
});

const submit = () => form.post(props.import_url);

const pretty = (value) => JSON.stringify(value ?? {}, null, 2);
</script>

<template>
    <Head title="Xray Routing" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Xray Routing</h1>
                <p>JSON routing settings for connect subscriptions.</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span>Settings JSON</span>
                <AppTextarea v-model="form.settings_json" rows="18" required />
                <small v-if="form.errors.settings_json" class="field-error">{{ form.errors.settings_json }}</small>
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Import</AppButton>
            </div>
        </form>
    </section>

    <section class="grid grid--two">
        <div class="page-card stack">
            <h2>Active Settings</h2>
            <div v-if="active_settings" class="stack">
                <div>
                    <strong>{{ active_settings.name }}</strong>
                    <div>{{ active_settings.imported_at || active_settings.created_at }}</div>
                </div>
                <pre>{{ pretty({
                    dns: active_settings.dns,
                    routing: active_settings.routing,
                    geodata: active_settings.geodata,
                }) }}</pre>
            </div>
            <p v-else>No active settings.</p>
        </div>

        <div class="page-card stack">
            <h2>Active Rules</h2>
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Name</th>
                        <th>Outbound</th>
                        <th>Rules</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="routing in routings" :key="routing.id">
                        <td>{{ routing.sort_order }}</td>
                        <td>{{ routing.name }}</td>
                        <td>{{ routing.outbound }}</td>
                        <td><pre>{{ pretty(routing.rules) }}</pre></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
