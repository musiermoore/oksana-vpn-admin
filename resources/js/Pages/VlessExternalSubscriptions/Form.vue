<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    method: String,
    submit_url: String,
    preview_url: String,
    subscription: Object,
    types: Array,
});

const form = useForm({
    name: props.subscription?.name ?? '',
    description: props.subscription?.description ?? '',
    type: props.subscription?.type ?? 'subscription',
    source_url: props.subscription?.source_url ?? '',
    filter_pattern: props.subscription?.filter_pattern ?? '',
    connect_name_prefix: props.subscription?.connect_name_prefix ?? '',
    include_in_main_subscription: props.subscription?.include_in_main_subscription ?? false,
    include_in_whitelist: props.subscription?.include_in_whitelist ?? true,
    is_free: props.subscription?.is_free ?? false,
    is_active: props.subscription?.is_active ?? true,
    is_ready: props.subscription?.is_ready ?? true,
});

const preview = ref(null);
const previewError = ref('');
const previewLoading = ref(false);
const typeOptions = props.types;

const submit = () => props.method === 'patch'
    ? form.patch(props.submit_url)
    : form.post(props.submit_url);

const loadPreview = async () => {
    previewLoading.value = true;
    previewError.value = '';

    try {
        const response = await window.axios.post(props.preview_url, form.data());
        preview.value = response.data ?? null;
    } catch (error) {
        previewError.value = error?.response?.data?.message ?? 'Не удалось получить конфиги.';
    } finally {
        previewLoading.value = false;
    }
};
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование WL-подписки' : 'Создание WL-подписки'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? 'Редактирование WL-подписки' : 'Создание WL-подписки' }}</h1>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Название</span>
                <AppInput v-model="form.name" required />
            </label>

            <label class="field">
                <span>Тип</span>
                <AppSelect v-model="form.type" :options="typeOptions" required />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>{{ form.type === 'subscription' ? 'URL подписки' : 'Прямая ссылка на конфиг' }}</span>
                <AppTextarea v-model="form.source_url" required  />
            </label>

            <label class="field">
                <span>Паттерн</span>
                <AppInput v-model="form.filter_pattern" :disabled="form.type !== 'subscription'" placeholder="Например: Германия" />
            </label>

            <label class="field">
                <span>Название в connect-wl</span>
                <AppInput v-model="form.connect_name_prefix" placeholder="Например: Сервер" />
            </label>

            <label class="field">
                <span>Включить в основную подписку</span>
                <AppCheckbox v-model="form.include_in_main_subscription" />
            </label>

            <label class="field">
                <span>Включить в подписку белых списков</span>
                <AppCheckbox v-model="form.include_in_whitelist" />
            </label>

            <label class="field">
                <span>Бесплатная</span>
                <AppCheckbox v-model="form.is_free" />
            </label>

            <label class="field">
                <span>Активна</span>
                <AppCheckbox v-model="form.is_active" />
            </label>

            <label class="field">
                <span>Ready</span>
                <AppCheckbox v-model="form.is_ready" />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description"  />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" type="button" :disabled="previewLoading" @click="loadPreview">
                    {{ previewLoading ? 'Загружаем...' : 'Получить конфиги' }}
                </AppButton>
                <AppButton variant="secondary" href="/vless-external-subscriptions">Назад</AppButton>
            </div>
        </form>
    </section>

    <section v-if="previewError" class="page-card stack">
        <p class="field-error">{{ previewError }}</p>
    </section>

    <section v-if="preview" class="grid grid--two">
        <div class="page-card stack">
            <h2>Полный список</h2>
            <p>Найдено: {{ preview.full?.length ?? 0 }}</p>
            <ul class="stack">
                <li v-for="item in preview.full" :key="item.config_key">
                    <strong>{{ item.name }}</strong>
                    <div>{{ item.protocol || 'unknown' }}</div>
                </li>
            </ul>
        </div>

        <div class="page-card stack">
            <h2>Отфильтрованный список</h2>
            <p>Найдено: {{ preview.filtered?.length ?? 0 }}</p>
            <ul class="stack">
                <li v-for="item in preview.filtered" :key="item.config_key">
                    <strong>{{ item.name }}</strong>
                    <div>{{ item.protocol || 'unknown' }}</div>
                </li>
            </ul>
        </div>
    </section>

    <section v-if="subscription?.configs?.length" class="page-card stack">
        <h2>Синхронизированные конфиги</h2>
        <ul class="stack">
            <li v-for="item in subscription.configs" :key="item.id">
                <strong>{{ item.name }}</strong>
                <div>{{ item.protocol || 'unknown' }}</div>
            </li>
        </ul>
    </section>
</template>
