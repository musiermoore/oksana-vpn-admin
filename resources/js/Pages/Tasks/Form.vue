<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    submitUrl: {
        type: String,
        required: true,
    },
    method: {
        type: String,
        default: 'post',
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    initialTask: {
        type: Object,
        default: () => ({
            title: '',
            description: '',
            status: 'todo',
            due_date: '',
        }),
    },
    cancelHref: {
        type: String,
        default: '/tasks',
    },
});

const form = useForm({
    title: props.initialTask.title ?? '',
    description: props.initialTask.description ?? '',
    status: props.initialTask.status ?? 'todo',
    due_date: props.initialTask.due_date ?? '',
});

const submit = () => {
    if (props.method === 'put') {
        form.put(props.submitUrl);
        return;
    }

    form.post(props.submitUrl);
};
</script>

<template>
    <form class="grid grid--two" @submit.prevent="submit">
        <label class="field" style="grid-column: 1 / -1;">
            <span>Название</span>
            <input v-model="form.title" type="text" required maxlength="255">
            <small v-if="form.errors.title" class="field-error">{{ form.errors.title }}</small>
        </label>

        <label class="field">
            <span>Статус</span>
            <select v-model="form.status">
                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
            <small v-if="form.errors.status" class="field-error">{{ form.errors.status }}</small>
        </label>

        <label class="field">
            <span>Дедлайн</span>
            <input v-model="form.due_date" type="date">
            <small v-if="form.errors.due_date" class="field-error">{{ form.errors.due_date }}</small>
        </label>

        <label class="field" style="grid-column: 1 / -1;">
            <span>Описание</span>
            <textarea v-model="form.description" rows="8"></textarea>
            <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
        </label>

        <div class="actions" style="grid-column: 1 / -1;">
            <button class="button" type="submit" :disabled="form.processing">Сохранить</button>
            <Link class="button button--secondary" :href="cancelHref">Назад</Link>
        </div>
    </form>
</template>
