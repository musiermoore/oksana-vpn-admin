<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    supportUser: Object,
});

const form = useForm({
    message: '',
});
const copiedLink = ref('');
let copyResetTimer = null;

const submit = () => {
    form.post(`/support-tickets/${props.ticket.id}/reply`);
};

const closeTicket = () => router.post(`/support-tickets/${props.ticket.id}/close`);

const copyLink = async (url) => {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.append(input);
            input.select();
            const copied = document.execCommand('copy');
            input.remove();

            if (!copied) {
                throw new Error('Clipboard copy failed');
            }
        }

        copiedLink.value = url;
        window.clearTimeout(copyResetTimer);
        copyResetTimer = window.setTimeout(() => {
            copiedLink.value = '';
        }, 2000);
    } catch {
        copiedLink.value = `error:${url}`;
    }
};

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
                <div v-for="link in supportUser.connect_links" :key="link.label" class="support-link-row">
                    <a :href="link.url" target="_blank">{{ link.label }}</a>
                    <AppButton type="button" variant="secondary" @click="copyLink(link.url)">
                        {{ copiedLink === link.url ? 'Скопировано' : copiedLink === `error:${link.url}` ? 'Не скопировано' : 'Копировать' }}
                    </AppButton>
                </div>
            </div>
        </div>
        <div>
            <span class="field-label">Конфиги Amnezia</span>
            <p v-if="!supportUser.amnezia_configs.length">Нет конфигов</p>
            <div v-for="config in supportUser.amnezia_configs" :key="config.url" class="support-link-row">
                <a :href="config.url">{{ config.label }}</a>
                <AppButton type="button" variant="secondary" @click="copyLink(config.url)">
                    {{ copiedLink === config.url ? 'Скопировано' : copiedLink === `error:${config.url}` ? 'Не скопировано' : 'Копировать' }}
                </AppButton>
            </div>
        </div>
        <details class="support-user-details">
            <summary>
                <span>Последние 5 транзакций</span>
                <span class="support-user-details__count">{{ supportUser.transactions.length }}</span>
            </summary>
            <p v-if="!supportUser.transactions.length" class="support-user-details__empty">Транзакций пока нет</p>
            <div v-else class="support-compact-list">
                <article v-for="transaction in supportUser.transactions" :key="transaction.id" class="support-compact-item">
                    <div class="support-compact-item__head">
                        <strong>{{ transaction.type }} · {{ transaction.amount }}</strong>
                        <span>{{ transaction.approved ? 'Одобрена' : 'Ожидает' }}</span>
                    </div>
                    <div class="support-compact-item__meta">
                        <span>{{ formatDate(transaction.date) }}</span>
                        <span v-if="transaction.description">{{ transaction.description }}</span>
                    </div>
                </article>
            </div>
        </details>
        <details class="support-user-details">
            <summary>
                <span>Последние подключения</span>
                <span class="support-user-details__count">{{ supportUser.connected_devices.length }}</span>
            </summary>
            <p v-if="!supportUser.connected_devices.length" class="support-user-details__empty">Данных о подключениях нет</p>
            <div v-else class="support-compact-list">
                <article v-for="(device, index) in supportUser.connected_devices" :key="`${device.label}-${index}`" class="support-compact-item">
                    <div class="support-compact-item__head">
                        <strong>{{ device.label || device.device || 'Устройство' }}</strong>
                        <span>{{ formatDate(device.last_connection_at) }}</span>
                    </div>
                    <div class="support-compact-item__meta">
                        <span>{{ device.device || 'Устройство неизвестно' }} · {{ device.connection_route || 'Маршрут неизвестен' }}</span>
                        <span>{{ device.user_agent || 'Версия приложения неизвестна' }}</span>
                        <span>{{ device.ip_address || 'IP не записан' }} · {{ device.connection_count }} подключений</span>
                    </div>
                </article>
            </div>
        </details>
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
