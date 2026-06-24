<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
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
    support_ticket_store_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const tickets = ref([]);
const form = ref({
    subject: '',
    message: '',
});
const sending = ref(false);
let pollTimer = null;

const loadTickets = async () => {
    const response = await window.axios.get(props.support_tickets_url, {
        headers: telegramAppHeaders(),
    });

    tickets.value = response.data?.tickets ?? [];
};

const submitTicket = async () => {
    if (form.value.message.trim() === '') {
        error.value = 'Опишите ваш вопрос или проблему.';
        return;
    }

    sending.value = true;
    error.value = '';

    try {
        await window.axios.post(props.support_ticket_store_url, form.value, {
            headers: telegramAppHeaders(),
        });

        form.value = {
            subject: '',
            message: '',
        };

        await loadTickets();
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось отправить обращение.');
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
        await loadTickets();
        pollTimer = window.setInterval(() => {
            void loadTickets();
        }, 5000);
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить поддержку.');
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
        title="Поддержка / Предложения"
        description="Если что-то не работает или нужен совет, напишите нам прямо здесь."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-card tg-state-card">
            <h2>Открываем поддержку</h2>
            <p>Загружаем ваши обращения и форму связи.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-card tg-state-card">
            <h2>Поддержка временно недоступна</h2>
            <p>{{ error }}</p>
        </section>

        <template v-else>
            <section class="tg-card stack">
                <span class="tg-card__eyebrow">Новое обращение</span>
                <h2>Написать в поддержку</h2>

                <label class="field">
                    <span>Тема</span>
                    <input v-model="form.subject" type="text" placeholder="Например: не проходит оплата" />
                </label>

                <label class="field">
                    <span>Сообщение</span>
                    <textarea v-model="form.message" placeholder="Опишите вопрос как можно подробнее"></textarea>
                </label>

                <div class="actions">
                    <button class="button" type="button" :disabled="sending" @click="submitTicket">
                        {{ sending ? 'Отправляем...' : 'Отправить обращение' }}
                    </button>
                </div>

                <p v-if="error" class="field-error">{{ error }}</p>
            </section>

            <section class="tg-card stack">
                <span class="tg-card__eyebrow">Мои обращения</span>
                <h2>История переписки</h2>

                <div v-if="tickets.length === 0" class="empty-state">
                    У вас пока нет обращений. Если появится вопрос, просто напишите его выше.
                </div>

                <div v-else class="tg-ticket-list">
                    <Link
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        :href="`${routes.support}/${ticket.id}`"
                        class="tg-ticket-card"
                    >
                        <div class="tg-ticket-card__top">
                            <strong>#{{ ticket.id }} · {{ ticket.subject || 'Без темы' }}</strong>
                            <span class="badge">{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                        </div>

                        <p class="tg-muted">{{ ticket.latest_message?.message || 'Нет сообщений' }}</p>
                    </Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
