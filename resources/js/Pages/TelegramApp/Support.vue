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
    support_ticket_store_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const tickets = ref([]);
const isComposerOpen = ref(false);
const form = ref({
    subject: '',
    message: '',
});
const sending = ref(false);
let pollTimer = null;

const hasTickets = computed(() => tickets.value.length > 0);

const loadTickets = async () => {
    const response = await window.axios.get(props.support_tickets_url, {
        headers: telegramAppHeaders(),
    });

    tickets.value = response.data?.tickets ?? [];
};

const openComposer = () => {
    isComposerOpen.value = true;
};

const closeComposer = () => {
    isComposerOpen.value = false;
    error.value = '';
};

const submitTicket = async () => {
    if (form.value.message.trim() === '') {
        error.value = 'Опишите ваш вопрос или предложение.';
        return;
    }

    sending.value = true;
    error.value = '';

    try {
        const response = await window.axios.post(props.support_ticket_store_url, form.value, {
            headers: telegramAppHeaders(),
        });
        const ticketId = response.data?.ticket?.id;

        form.value = {
            subject: '',
            message: '',
        };

        if (ticketId) {
            window.location.href = `${props.routes.support}/${ticketId}`;
            return;
        }

        await loadTickets();
    } catch (requestError) {
        error.value = normalizeTelegramAppError(requestError, 'Не удалось отправить обращение.');
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
        description="Если у вас возник вопрос или есть предложение, мы с радостью поможем и ответим как можно скорее."
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

        <template v-else>
            <section v-if="!hasTickets && !isComposerOpen" class="tg-empty-panel">
                <div class="tg-empty-panel__icon">
                    <span>💬</span>
                </div>
                <h2>У вас пока нет обращений</h2>
                <p>Если у вас возник вопрос или есть предложение, мы с радостью поможем и ответим как можно скорее.</p>
                <button class="button tg-button-full" type="button" @click="openComposer">Создать обращение</button>
            </section>

            <section v-if="isComposerOpen || hasTickets" class="tg-panel">
                <div class="tg-section-head">
                    <div>
                        <span class="tg-section-label">Новое обращение</span>
                        <h2>Написать в поддержку</h2>
                    </div>

                    <button v-if="isComposerOpen && hasTickets" class="tg-link-button" type="button" @click="closeComposer">
                        Скрыть
                    </button>
                </div>

                <div v-if="!isComposerOpen && hasTickets" class="tg-inline-callout">
                    <p>Если нужно новое обращение, нажмите на кнопку ниже.</p>
                    <button class="button tg-button-full" type="button" @click="openComposer">Создать обращение</button>
                </div>

                <template v-else>
                    <label class="field">
                        <span>Тема</span>
                        <input v-model="form.subject" type="text" placeholder="Например: продление подписки" />
                    </label>

                    <label class="field">
                        <span>Сообщение</span>
                        <textarea v-model="form.message" placeholder="Опишите вопрос как можно подробнее"></textarea>
                    </label>

                    <div class="actions">
                        <button class="button tg-button-full" type="button" :disabled="sending" @click="submitTicket">
                            {{ sending ? 'Отправляем...' : 'Отправить обращение' }}
                        </button>
                    </div>

                    <p v-if="error" class="field-error">{{ error }}</p>
                </template>
            </section>

            <section v-if="hasTickets" class="tg-panel">
                <div class="tg-section-head">
                    <div>
                        <span class="tg-section-label">История переписки</span>
                        <h2>Мои обращения</h2>
                    </div>
                </div>

                <div class="tg-ticket-list">
                    <Link
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        :href="`${routes.support}/${ticket.id}`"
                        class="tg-ticket-card"
                    >
                        <div class="tg-ticket-card__top">
                            <div class="tg-ticket-card__copy">
                                <strong>Обращение #{{ ticket.id }}</strong>
                                <span>{{ ticket.subject || 'Без темы' }}</span>
                            </div>
                            <span class="badge">{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                        </div>

                        <p class="tg-muted">{{ ticket.latest_message?.message || 'Нет сообщений' }}</p>
                    </Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
