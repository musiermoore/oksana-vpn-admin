<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    resource: Object,
});

const form = useForm({
    name: props.resource?.name ?? '',
    description: props.resource?.description ?? '',
    geoip_url: props.resource?.geoip_url ?? '',
    geosite_url: props.resource?.geosite_url ?? '',
    is_active: props.resource?.is_active ?? true,
});

const submit = () => props.mode === 'edit'
    ? form.put(props.submit_url)
    : form.post(props.submit_url);
</script>

<template>
    <Head :title="mode === 'edit' ? 'Изменение геоданных Xray' : 'Добавление геоданных Xray'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? 'Изменение источника геоданных' : 'Добавление источника геоданных' }}</h1>
                <p>Укажите прямые HTTPS-ссылки на файлы `.dat`. Они будут переданы клиенту как Xray geodata assets.</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field">
                <span>Название</span>
                <AppInput v-model="form.name" required />
                <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
            </label>

            <label class="field field--checkbox">
                <span>Активен</span>
                <AppCheckbox v-model="form.is_active" />
                <small v-if="form.errors.is_active" class="field-error">{{ form.errors.is_active }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description" />
                <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>URL GeoIP</span>
                <AppInput v-model="form.geoip_url" type="url" placeholder="https://.../geoip.dat" />
                <small v-if="form.errors.geoip_url" class="field-error">{{ form.errors.geoip_url }}</small>
                <small>Сохранится как `geoip.dat`. Например, XTLS GeoIP Full содержит категории стран, включая `ru`.</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>URL GeoSite</span>
                <AppInput v-model="form.geosite_url" type="url" placeholder="https://.../geosite.dat" />
                <small v-if="form.errors.geosite_url" class="field-error">{{ form.errors.geosite_url }}</small>
                <small>Сохранится как `geosite.dat`. Можно оставить поле пустым, если нужен только GeoIP.</small>
            </label>

            <small v-if="form.errors.geodata" class="field-error" style="grid-column: 1 / -1;">{{ form.errors.geodata }}</small>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/xray-geodata">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
