<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    template: { type: Object, default: null },
    submit_url: { type: String, required: true },
    method: { type: String, required: true },
    formats: { type: Array, default: () => [] },
});

const blockExample = `{
  "blocks": [
    {
      "command": "sudo apt install example-package",
      "timeout_seconds": 120,
      "answers": [
        { "scan": "\\\\[Y/n\\\\]", "answer": "y", "send": "enter" }
      ]
    }
  ]
}`;

const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    format: props.template?.format ?? 'script',
    content: props.template?.content ?? '',
    timeout_seconds: props.template?.timeout_seconds ?? 300,
});

const submit = () => {
    if (props.method === 'put') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};
</script>

<template>
    <Head :title="template ? 'Редактировать шаблон' : 'Новый шаблон команд'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ template ? 'Редактировать шаблон' : 'Новый шаблон команд' }}</h1>
                <p>Raw script выполняется как shell-файл. Command blocks задаются JSON-массивом и могут описывать ответы на prompt.</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field" style="grid-column: 1 / -1;">
                <span>Название</span>
                <AppInput v-model="form.name" required maxlength="255" />
                <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
            </label>

            <label class="field">
                <span>Формат</span>
                <AppSelect v-model="form.format" :options="formats" />
                <small v-if="form.errors.format" class="field-error">{{ form.errors.format }}</small>
            </label>

            <label class="field">
                <span>Тайм-аут по умолчанию, секунды</span>
                <AppInput v-model.number="form.timeout_seconds" type="number" min="1" max="3600" required />
                <small v-if="form.errors.timeout_seconds" class="field-error">{{ form.errors.timeout_seconds }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description" rows="2" maxlength="2000" />
                <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>{{ form.format === 'script' ? 'Shell script' : 'Command blocks JSON' }}</span>
                <AppTextarea
                    v-model="form.content"
                    rows="18"
                    spellcheck="false"
                    :placeholder="form.format === 'script' ? '#!/usr/bin/env bash\nset -euo pipefail\n\n# Add commands here' : blockExample"
                />
                <small v-if="form.format === 'blocks'" class="muted">
                    Для каждого блока укажите command и при необходимости answers. scan — регулярное выражение prompt, answer — вводимый текст, send — enter или raw.
                </small>
                <small v-if="form.errors.content" class="field-error">{{ form.errors.content }}</small>
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/server-command-templates">Назад</AppButton>
            </div>
        </form>
    </section>
</template>
