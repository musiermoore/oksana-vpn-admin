<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
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

const formatDateTime = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'long',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const authorLabel = (item) => (item.sender_type === 'admin' ? 'Поддержка' : 'Вы');

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
        title="Диалог с поддержкой"
        description="Следите за ответами и продолжайте переписку в одном месте."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось открыть диалог</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else-if="ticket">
            <section class="tg-section">
                <div class="tg-page-header__copy">
                    <Link :href="routes.support" class="tg-link-button">
                        <AppIcon name="chevronLeft" />
                        <span>К списку обращений</span>
                    </Link>
                    <div class="tg-tag">
                        <AppIcon name="message" />
                        <span>{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                    </div>
                    <h2>Обращение #{{ ticket.id }}</h2>
                    <p>{{ ticket.subject || 'Без темы' }}</p>
                    <p class="tg-caption">Создано {{ formatDateTime(ticket.created_at) }}</p>
                </div>
            </section>

            <section class="tg-chat-panel">
                <div class="tg-chat-thread">
                    <article
                        v-for="item in ticket.messages"
                        :key="item.id"
                        class="tg-support-message"
                        :class="{ 'tg-support-message--user': item.sender_type !== 'admin' }"
                    >
                        <strong>{{ authorLabel(item) }}</strong>
                        <p class="tg-support-message__text">{{ item.message }}</p>
                        <span class="tg-support-message__meta">{{ formatDateTime(item.created_at) }}</span>
                    </article>
                </div>
            </section>

            <section class="tg-chat-composer">
                <div class="tg-field">
                    <label for="support-thread-message">Новое сообщение</label>
                    <textarea
                        id="support-thread-message"
                        v-model="message"
                        class="tg-textarea"
                        placeholder="Опишите, что изменилось или какой шаг не получается"
                    ></textarea>
                </div>

                <button class="tg-button" type="button" :disabled="sending" @click="sendMessage">
                    <AppIcon name="send" />
                    <span>{{ sending ? 'Отправляем...' : 'Отправить сообщение' }}</span>
                </button>

                <p v-if="error" class="tg-error">{{ error }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
