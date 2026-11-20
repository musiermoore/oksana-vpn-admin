<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    promo_code: Object,
    submit_url: String,
    benefits: Array,
});
const title = props.promo_code ? 'Редактирование промокода' : 'Новый промокод';

const form = useForm({
    code: props.promo_code?.code ?? '',
    benefit: props.promo_code?.benefit ?? 'percentage_discount',
    value: props.promo_code?.value ?? '',
    once_per_user: props.promo_code?.once_per_user ?? true,
    new_users_only: props.promo_code?.new_users_only ?? false,
    is_active: props.promo_code?.is_active ?? true,
    starts_on: props.promo_code?.starts_on ?? '',
    ends_on: props.promo_code?.ends_on ?? '',
});
const isDays = computed(() => form.benefit === 'bonus_days');
const valueUnit = computed(() => {
    if (isDays.value) return 'дни';
    return form.benefit === 'percentage_discount' ? '%' : '₽';
});

const save = () => form.submit(props.promo_code ? 'put' : 'post', props.submit_url);
</script>

<template>
    <Head :title="title" />
    <section class="page-card stack">
        <div class="page-header"><div><h1>{{ title }}</h1></div></div>
        <form class="grid grid--two" @submit.prevent="save">
            <label class="field">
                <span>Промокод</span>
                <AppInput v-model="form.code" required maxlength="64" pattern="[A-Za-z0-9_\-]+" />
                <small v-if="form.errors.code" class="field-error">{{ form.errors.code }}</small>
            </label>
            <label class="field">
                <span>Бонус</span>
                <AppSelect v-model="form.benefit" :options="benefits" />
                <small v-if="form.errors.benefit" class="field-error">{{ form.errors.benefit }}</small>
            </label>
            <label class="field">
                <span>Размер бонуса ({{ valueUnit }})</span>
                <AppInput v-model="form.value" type="number" :min="isDays ? 1 : 0.01" :step="isDays ? 1 : 0.01" required />
                <small v-if="form.errors.value" class="field-error">{{ form.errors.value }}</small>
            </label>
            <label class="field">
                <span>Частота использования</span>
                <AppSelect v-model="form.once_per_user" :options="[
                    { value: true, label: 'Один раз на пользователя' },
                    { value: false, label: 'При каждой покупке' },
                ]" />
                <small v-if="form.errors.once_per_user" class="field-error">{{ form.errors.once_per_user }}</small>
            </label>
            <label class="field">
                <span>Начало действия (включительно)</span>
                <AppInput v-model="form.starts_on" type="date" />
                <small v-if="form.errors.starts_on" class="field-error">{{ form.errors.starts_on }}</small>
            </label>
            <label class="field">
                <span>Окончание действия (включительно)</span>
                <AppInput v-model="form.ends_on" type="date" />
                <small v-if="form.errors.ends_on" class="field-error">{{ form.errors.ends_on }}</small>
            </label>
            <div>
                <label><input v-model="form.new_users_only" type="checkbox" /> Только без подписок или после пробного периода</label>
                <small v-if="form.errors.new_users_only" class="field-error">{{ form.errors.new_users_only }}</small>
            </div>
            <div>
                <label><input v-model="form.is_active" type="checkbox" /> Промокод включён</label>
                <small v-if="form.errors.is_active" class="field-error">{{ form.errors.is_active }}</small>
            </div>
            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/promo-codes">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
