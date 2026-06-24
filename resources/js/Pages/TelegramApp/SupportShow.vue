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
        title="Обращение в поддержку"
        description="Следите за ответами и продолжайте переписку в одном месте."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-card tg-state-card">
            <h2>Открываем обращение</h2>
            <p>Загружаем переписку с поддержкой.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-card tg-state-card">
            <h2>Не удалось открыть обращение</h2>
            <p>{{ error }}</p>
        </section>

        <template v-else-if="ticket">
            <section class="tg-card stack">
                <div class="page-header">
                    <div>
                        <span class="tg-card__eyebrow">Тикет #{{ ticket.id }}</span>
                        <h2>{{ ticket.subject || 'Без темы' }}</h2>
                    </div>

                    <div class="actions">
                        <span class="badge">{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                        <Link class="button button--secondary" :href="routes.support">Назад</Link>
                    </div>
                </div>
            </section>

            <section class="tg-card stack">
                <div class="tg-chat">
                    <article
                        v-for="item in ticket.messages"
                        :key="item.id"
                        class="tg-chat__message"
                        :class="{ 'is-admin': item.sender_type === 'admin' }"
                    >
                        <strong>{{ item.sender_name || (item.sender_type === 'admin' ? 'Поддержка' : 'Вы') }}</strong>
                        <p>{{ item.message }}</p>
                    </article>
                </div>
            </section>

            <section class="tg-card stack">
                <span class="tg-card__eyebrow">Новое сообщение</span>
                <label class="field">
                    <span>Сообщение</span>
                    <textarea v-model="message" placeholder="Напишите ответ или добавьте детали"></textarea>
                </label>

                <div class="actions">
                    <button class="button" type="button" :disabled="sending" @click="sendMessage">
                        {{ sending ? 'Отправляем...' : 'Отправить сообщение' }}
                    </button>
                </div>

                <p v-if="error" class="field-error">{{ error }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
