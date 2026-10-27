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
    source_formats: Array,
});

const form = useForm({
    name: props.subscription?.name ?? '',
    description: props.subscription?.description ?? '',
    type: props.subscription?.type ?? 'subscription',
    source_format: props.subscription?.source_format ?? 'direct',
    source_url: props.subscription?.source_url ?? '',
    filter_pattern: props.subscription?.filter_pattern ?? '',
    except_pattern: props.subscription?.except_pattern ?? '',
    connect_name_prefix: props.subscription?.connect_name_prefix ?? '',
    include_in_main_subscription: props.subscription?.include_in_main_subscription ?? false,
    include_in_whitelist: props.subscription?.include_in_whitelist ?? true,
    include_in_connect_v2: props.subscription?.include_in_connect_v2 ?? true,
    is_free: props.subscription?.is_free ?? false,
    is_active: props.subscription?.is_active ?? true,
    is_ready: props.subscription?.is_ready ?? true,
});

const preview = ref(null);
const previewError = ref('');
const previewLoading = ref(false);
const regexHelpOpen = ref(false);
const typeOptions = props.types;
const sourceFormatOptions = props.source_formats;

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
    <Head :title="mode === 'edit' ? 'Редактирование внешней подписки' : 'Создание внешней подписки'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? 'Редактирование внешней подписки' : 'Создание внешней подписки' }}</h1>
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

            <label class="field">
                <span>Формат источника</span>
                <AppSelect v-model="form.source_format" :options="sourceFormatOptions" required />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>
                    {{
                        form.source_format === 'incy'
                            ? 'INCY ссылка или https redirect'
                            : (form.type === 'subscription' ? 'URL подписки' : 'Прямая ссылка на конфиг')
                    }}
                </span>
                <AppTextarea v-model="form.source_url" required  />
            </label>

            <div class="field regex-pattern-field">
                <span>Регулярное выражение для имени</span>
                <AppInput v-model="form.filter_pattern" :disabled="form.type !== 'subscription'" placeholder="Например: Германия|Франция" />
                <small>Проверяется без учёта регистра. Пустое поле включает все конфиги.</small>
                <AppButton variant="secondary" type="button" @click="regexHelpOpen = true">Примеры regexp</AppButton>
            </div>

            <label class="field regex-pattern-field">
                <span>Исключить по регулярному выражению</span>
                <AppInput v-model="form.except_pattern" :disabled="form.type !== 'subscription'" placeholder="Например: тест|резерв" />
                <small>Совпавшие имена будут исключены из результата.</small>
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
                <span>Отображать в connect-v2</span>
                <AppCheckbox v-model="form.include_in_connect_v2" />
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

    <div v-if="regexHelpOpen" class="regex-help-modal" @click.self="regexHelpOpen = false" @keydown.esc="regexHelpOpen = false">
        <section class="page-card stack regex-help-modal__card" role="dialog" aria-modal="true" aria-labelledby="regex-help-title">
            <div class="page-header">
                <div>
                    <h2 id="regex-help-title">Примеры регулярных выражений</h2>
                    <p>Введите выражение без слешей вокруг. Совпадение проверяется без учёта регистра.</p>
                </div>
                <button type="button" class="regex-help-modal__close" aria-label="Закрыть" @click="regexHelpOpen = false">×</button>
            </div>

            <div class="regex-help-modal__examples">
                <strong>Включить</strong>
                <div><code>Германия|Франция</code><span>Имя содержит «Германия» или «Франция».</span></div>
                <div><code>^(DE|FR)\b</code><span>Имя начинается с кода DE или FR.</span></div>
                <div><code>\d+$</code><span>Имя заканчивается цифрами, например «Сервер 12».</span></div>
                <strong>Исключить</strong>
                <div><code>тест|резерв</code><span>Исключает имена, содержащие «тест» или «резерв».</span></div>
            </div>

            <div class="actions">
                <AppButton variant="secondary" type="button" @click="regexHelpOpen = false">Закрыть</AppButton>
            </div>
        </section>
    </div>

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

<style scoped>
.regex-pattern-field {
    grid-column: 1 / -1;
}

.regex-pattern-field :deep(input) {
    width: 100%;
}

.regex-help-modal {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgb(0 0 0 / 45%);
}

.regex-help-modal__card {
    width: min(38rem, 100%);
    max-height: 90vh;
    overflow: auto;
}

.regex-help-modal__close {
    border: 0;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-size: 1.75rem;
    line-height: 1;
}

.regex-help-modal__examples {
    display: grid;
    gap: 1rem;
}

.regex-help-modal__examples > div {
    display: grid;
    gap: 0.3rem;
}

.regex-help-modal__examples code {
    overflow-wrap: anywhere;
    font-size: 0.95rem;
}
</style>
