<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import TelegramMessageEditor from '../../Shared/TelegramMessageEditor.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    messages: Object,
});

const form = useForm({
    basic_text: props.messages.basic_text ?? '',
    extended_text: props.messages.extended_text ?? '',
});

const submit = () => {
    form.put('/messages/welcome', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Welcome Messages" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Приветственные сообщения</h1>
                <p>Расширенное сообщение показывается пользователю не чаще одного раза в неделю или после первой регистрации. Во все остальные вызовы `/start` бот получает базовый текст.</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <section class="notification-section stack">
                <div class="page-header">
                    <div>
                        <h2 class="section-title">Расширенное сообщение</h2>
                        <p>Используется для первого показа и повторного показа спустя неделю после `welcome_text_seen_at`.</p>
                    </div>
                </div>

                <TelegramMessageEditor
                    v-model="form.extended_text"
                    label="Текст расширенного сообщения"
                    :error="form.errors.extended_text"
                    preview-title="Предпросмотр расширенного сообщения"
                />
            </section>

            <section class="notification-section stack">
                <div class="page-header">
                    <div>
                        <h2 class="section-title">Базовое сообщение</h2>
                        <p>Используется для всех остальных вызовов `/start`. Пустая строка допустима: тогда бот сам применит fallback.</p>
                    </div>
                </div>

                <TelegramMessageEditor
                    v-model="form.basic_text"
                    label="Текст базового сообщения"
                    :error="form.errors.basic_text"
                    preview-title="Предпросмотр базового сообщения"
                />
            </section>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Сохраняем...' : 'Сохранить' }}
                </button>
            </div>
        </form>
    </section>
</template>
