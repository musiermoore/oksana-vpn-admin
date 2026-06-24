<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    statuses: Array,
});

const form = useForm({
    message: '',
    status: props.ticket.status,
});

const submit = () => {
    form.post(`/support-tickets/${props.ticket.id}/reply`);
};

const statusLabel = (status) => ({
    open: 'Открыт',
    answered: 'Отвечен',
    closed: 'Закрыт',
}[status] || status);
</script>

<template>
    <Head :title="`Тикет #${ticket.id}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Тикет #{{ ticket.id }}</h1>
                <p>{{ ticket.subject || 'Без темы' }}</p>
            </div>

            <div class="actions">
                <Link class="button button--secondary" href="/support-tickets">Назад</Link>
                <a
                    v-if="ticket.user?.chat_url"
                    class="button"
                    :href="ticket.user.chat_url"
                    target="_blank"
                    rel="noreferrer"
                >
                    Открыть чат
                </a>
            </div>
        </div>

        <div class="grid-form grid-form--two">
            <div>
                <span class="field-label">Пользователь</span>
                <p>{{ ticket.user?.name || '—' }}</p>
                <p>{{ ticket.user?.telegram || ticket.user?.telegram_id || '—' }}</p>
            </div>
            <div>
                <span class="field-label">Статус</span>
                <p>{{ statusLabel(ticket.status) }}</p>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <h2>Переписка</h2>

        <div class="stack">
            <article
                v-for="message in ticket.messages"
                :key="message.id"
                class="page-card"
                :style="{ borderLeft: message.sender_type === 'admin' ? '4px solid #1f6feb' : '4px solid #2ea043' }"
            >
                <strong>{{ message.sender_name || message.sender_type }}</strong>
                <p>{{ message.message }}</p>
                <small>{{ message.created_at }}</small>
            </article>
        </div>
    </section>

    <section class="page-card stack">
        <h2>Ответить</h2>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span class="field-label">Статус после ответа</span>
                <select v-model="form.status" class="input">
                    <option v-for="status in statuses" :key="status.value" :value="status.value">
                        {{ status.label }}
                    </option>
                </select>
            </label>

            <label class="field">
                <span class="field-label">Сообщение</span>
                <textarea v-model="form.message" class="input input--textarea" rows="6"></textarea>
            </label>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Отправить ответ</button>
            </div>
        </form>
    </section>
</template>
