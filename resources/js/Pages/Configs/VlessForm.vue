<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    config: Object,
    users: Array,
    existing_configs: Array,
});

const form = useForm({
    user_id: props.config?.user?.id ?? props.users[0]?.id ?? '',
    config_id: props.existing_configs[0]?.id ?? '',
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование VLESS-конфига' : 'Создать VLESS-конфиг'" />

    <section class="page-card stack">
        <div class="page-header">
            <div><h1>{{ mode === 'edit' ? 'Редактирование VLESS-конфига' : 'Создать VLESS-конфиг' }}</h1></div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Участник</span>
                <select v-model="form.user_id">
                    <option v-for="user in users" :key="user.id" :value="user.id">{{ user.full_name }}</option>
                </select>
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Конфиг</span>
                <select v-model="form.config_id">
                    <option v-for="item in existing_configs" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </label>

            <template v-else>
                <label class="field">
                    <span>Сервер</span>
                    <input :value="`${config.server.name} (${config.server.ip})`" readonly>
                </label>

                <label class="field" style="grid-column: 1 / -1;">
                    <span>Ссылка</span>
                    <textarea :value="config.link" readonly />
                </label>
            </template>

            <div class="actions" style="grid-column: 1 / -1;">
                <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
                <Link class="button button--secondary" href="/vless-configs">Назад</Link>
            </div>
        </form>
    </section>
</template>
