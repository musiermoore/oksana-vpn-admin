<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    redirectFromTelegramStartParam,
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
    error.value = '';
};

const closeComposer = () => {
    isComposerOpen.value = false;
    error.value = '';
};

const submitTicket = async () => {
    if (form.value.message.trim() === '') {
        error.value = 'Опишите вопрос или проблему.';
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
        isComposerOpen.value = false;
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
    if (redirectFromTelegramStartParam(props.routes)) {
        return;
    }

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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть поддержку.');
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
        title="Поддержка"
        description="Напишите вопрос, посмотрите историю обращений или откройте нужный чат."
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
            <h2>Не удалось открыть поддержку</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section class="tg-section">
                <div class="tg-page-header__copy">
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="headset" />
                        <span>Поддержка</span>
                    </div>
                    <h2>Нужна помощь?</h2>
                    <p>Если VPN не работает или есть вопрос по подписке, напишите нам. Обычно удобнее сразу описать проблему одним сообщением.</p>
                </div>

                <Link :href="routes?.chats" class="tg-list-card">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="chat" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Канал и общий чат</div>
                        <div class="tg-list-card__description">Быстрые ссылки на Telegram-сообщество и новости.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>
            </section>

            <section class="tg-surface-card tg-stack">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Новое обращение</div>
                        <div class="tg-section__subtitle">Откроем отдельный диалог и продолжим переписку там.</div>
                    </div>

                    <button
                        v-if="hasTickets && isComposerOpen"
                        class="tg-link-button"
                        type="button"
                        @click="closeComposer"
                    >
                        <AppIcon name="chevronDown" />
                        <span>Скрыть</span>
                    </button>
                </div>

                <button v-if="!isComposerOpen" class="tg-button" type="button" @click="openComposer">
                    <AppIcon name="message" />
                    <span>Написать в поддержку</span>
                </button>

                <div v-else class="tg-form">
                    <div class="tg-field">
                        <label for="support-subject">Тема</label>
                        <input
                            id="support-subject"
                            v-model="form.subject"
                            class="tg-input"
                            type="text"
                            placeholder="Например: не подключается VPN"
                        >
                    </div>

                    <div class="tg-field">
                        <label for="support-message">Сообщение</label>
                        <textarea
                            id="support-message"
                            v-model="form.message"
                            class="tg-textarea"
                            placeholder="Опишите, что происходит и на каком шаге возникла проблема"
                        ></textarea>
                    </div>

                    <button class="tg-button" type="button" :disabled="sending" @click="submitTicket">
                        <AppIcon name="send" />
                        <span>{{ sending ? 'Отправляем...' : 'Отправить обращение' }}</span>
                    </button>

                    <p v-if="error" class="tg-error">{{ error }}</p>
                </div>
            </section>

            <section v-if="hasTickets" class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Мои обращения</div>
                        <div class="tg-section__subtitle">Откройте нужный диалог и продолжите переписку.</div>
                    </div>
                </div>

                <Link
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    :href="`${routes.support}/${ticket.id}`"
                    class="tg-list-card"
                >
                    <div class="tg-list-card__icon">
                        <AppIcon name="message" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Обращение #{{ ticket.id }}</div>
                        <div class="tg-list-card__description">{{ ticket.subject || 'Без темы' }}</div>
                        <div class="tg-caption">{{ ticket.latest_message?.message || 'Пока нет сообщений' }}</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <span class="tg-tag">{{ telegramAppLabels[ticket.status] || ticket.status_label }}</span>
                    </div>
                </Link>
            </section>

            <section v-else-if="!isComposerOpen" class="tg-note">
                <strong>Обращений пока нет</strong>
                <p>Если хотите задать вопрос, создайте первое обращение. Оно откроется в отдельном чате внутри mini app.</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
