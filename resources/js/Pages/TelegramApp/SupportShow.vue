<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    telegramAppHeaders,
    telegramAppLabels,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    support_tickets_url: String,
    ticket_id: Number,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const ticket = ref(null);
const message = ref('');
const sending = ref(false);
let pollTimer = null;

const ticketUrl = computed(() => `${props.support_tickets_url}/${props.ticket_id}`);
const messageUrl = computed(() => `${props.support_tickets_url}/${props.ticket_id}/messages`);

const formatDate = (value, options = {}) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString('ru-RU', options);
};

const formatTime = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const authorLabel = (item) => (item.sender_type === 'admin' ? 'Оператор' : 'Вы');

const loadTicket = async () => {
    const response = await window.axios.get(ticketUrl.value, {
        headers: telegramAppHeaders(),
    });

    ticket.value = response.data?.ticket ?? null;
};

const sendMessage = async () => {
    if (message.value.trim() === '') {
        error.value = 'Введите сообщение.';
        return;
    }

    sending.value = true;
    error.value = '';

    try {
        await window.axios.post(messageUrl.value, {
            message: message.value,
        }, {
            headers: telegramAppHeaders(),
        });

        message.value = '';
        await loadTicket();
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось отправить сообщение.');
    } finally {
        sending.value = false;
    }
};

const retry = () => {
    window.location.reload();
};

onMounted(async () => {
    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
        await loadTicket();
        pollTimer = window.setInterval(() => {
            if (!sending.value) {
                void loadTicket();
            }
        }, 5000);
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть обращение.');
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        window.clearInterval(pollTimer);
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Чат обращения"
        description="Следите за ответами и продолжайте переписку в одном месте."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Загружаем данные...</h2>
            <p>Пожалуйста, подождите</p>

            <div class="tg-skeleton-list">
                <div class="tg-skeleton-card"></div>
                <div class="tg-skeleton-card"></div>
                <div class="tg-skeleton-card"></div>
            </div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось загрузить данные</h2>
            <p>{{ error || 'Пожалуйста, попробуйте ещё раз через пару секунд' }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else-if="ticket">
            <section class="tg-panel tg-ticket-head">
                <div class="tg-ticket-head__main">
                    <div>
                        <h2>Обращение #{{ ticket.id }}</h2>
                        <p>Тема: {{ ticket.subject || 'Без темы' }}</p>
                    </div>

                    <span class="badge badge--success">{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                </div>

                <div class="tg-ticket-head__meta">
                    <span>{{ formatDate(ticket.created_at, { day: 'numeric', month: 'long' }) }}</span>
                    <Link class="tg-link-button" :href="routes.support">К списку</Link>
                </div>
            </section>

            <section class="tg-chat-panel">
                <div class="tg-chat">
                    <article
                        v-for="item in ticket.messages"
                        :key="item.id"
                        class="tg-chat__message"
                        :class="{ 'is-admin': item.sender_type === 'admin', 'is-user': item.sender_type !== 'admin' }"
                    >
                        <span class="tg-chat__author">{{ authorLabel(item) }}</span>
                        <p>{{ item.message }}</p>
                        <span class="tg-chat__time">{{ formatTime(item.created_at) }}</span>
                    </article>
                </div>
            </section>

            <section class="tg-chat-composer">
                <label class="field">
                    <textarea v-model="message" placeholder="Напишите сообщение..."></textarea>
                </label>

                <button class="button tg-chat-composer__button" type="button" :disabled="sending" @click="sendMessage">
                    {{ sending ? '...' : '➜' }}
                </button>
            </section>

            <p v-if="error" class="field-error tg-chat-error">{{ error }}</p>
        </template>
    </TelegramMiniAppFrame>
</template>
