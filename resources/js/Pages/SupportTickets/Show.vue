<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    supportUser: Object,
});

const form = useForm({
    message: '',
});

const submit = () => {
    form.post(`/support-tickets/${props.ticket.id}/reply`);
};

const closeTicket = () => router.post(`/support-tickets/${props.ticket.id}/close`);

const statusLabel = (status) => ({
    open: 'Открыт',
    answered: 'Отвечен',
    closed: 'Закрыт',
}[status] || status);

const formatDate = (value) => value ? new Date(value).toLocaleString('ru-RU') : '—';
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
                <AppButton variant="secondary" href="/support-tickets">Назад</AppButton>
                <AppButton v-if="ticket.status !== 'closed'" variant="secondary" @click="closeTicket">Закрыть обращение</AppButton>
                <AppButton
                    v-if="ticket.user?.chat_url"
                    :href="ticket.user.chat_url"
                    target="_blank"
                >
                    Открыть чат
                </AppButton>
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

    <section v-if="supportUser" class="page-card stack">
        <h2>Информация о пользователе</h2>
        <div class="grid-form grid-form--two">
            <div>
                <span class="field-label">Подписка действует до</span>
                <p>{{ formatDate(supportUser.subscription_expires_at) }}</p>
            </div>
            <div>
                <span class="field-label">Ссылки подключения</span>
                <p v-for="link in supportUser.connect_links" :key="link.label"><a :href="link.url" target="_blank">{{ link.label }}</a></p>
            </div>
        </div>
        <div>
            <span class="field-label">Конфиги Amnezia</span>
            <p v-if="!supportUser.amnezia_configs.length">Нет конфигов</p>
            <p v-for="config in supportUser.amnezia_configs" :key="config.url"><a :href="config.url">{{ config.label }}</a></p>
        </div>
        <div>
            <span class="field-label">Последние 5 транзакций</span>
            <p v-if="!supportUser.transactions.length">Транзакций пока нет</p>
            <div v-for="transaction in supportUser.transactions" :key="transaction.id" class="page-card">
                <strong>{{ transaction.type }} · {{ transaction.amount }}</strong>
                <p>{{ formatDate(transaction.date) }} · {{ transaction.approved ? 'Одобрена' : 'Ожидает' }}</p>
                <p v-if="transaction.description">{{ transaction.description }}</p>
            </div>
        </div>
        <div>
            <span class="field-label">Последние подключения</span>
            <p v-if="!supportUser.connected_devices.length">Данных о подключениях нет</p>
            <div v-for="(device, index) in supportUser.connected_devices" :key="`${device.label}-${index}`" class="page-card">
                <strong>{{ device.label || device.device || 'Устройство' }}</strong>
                <p>{{ device.device || '—' }} · {{ device.connection_route || '—' }} · {{ formatDate(device.last_connection_at) }}</p>
                <p>{{ device.user_agent || 'Версия приложения неизвестна' }}</p>
                <p>{{ device.ip_address || 'IP не записан' }} · {{ device.connection_count }} подключений</p>
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
                <p style="white-space: pre-wrap">{{ message.message }}</p>
                <div v-if="message.attachments?.length">
                    <a v-for="file in message.attachments" :key="file.url" :href="file.url" target="_blank">{{ file.name }}</a>
                </div>
                <small>{{ message.created_at }}</small>
            </article>
        </div>
    </section>

    <section class="page-card stack">
        <h2>Ответить</h2>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span class="field-label">Сообщение</span>
                <AppTextarea v-model="form.message" rows="6" />
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Отправить ответ</AppButton>
            </div>
        </form>
    </section>
</template>
