<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    redirectFromTelegramStartParam,
    telegramAppLabels,
} from '../../lib/telegramMiniApp';
import { telegramMiniAppApi, telegramMiniAppApiEndpoint, telegramMiniAppEndpoints, telegramMiniAppRoutes } from '../../lib/telegramMiniAppApi';

const state = ref('loading');
const error = ref('');
const user = ref(null);
const tickets = ref([]);
const isComposerOpen = ref(false);
const form = ref({
    subject: '',
});
const sending = ref(false);
const servers = ref([]);
const attachments = ref([]);
const template = ref({
    category: 'vpn',
    issueTypes: [],
    application: '',
    otherApplication: '',
    serverIds: [],
    inboundIds: [],
    triedAllConfigs: '',
    updatedApplication: '',
    customMessage: '',
});
let pollTimer = null;

const hasTickets = computed(() => tickets.value.length > 0);
const serverConnectionSummary = computed(() => servers.value
    .filter((server) => template.value.serverIds.includes(server.id))
    .map((server) => {
        const inboundNames = server.inbounds
            .filter((inbound) => template.value.inboundIds.includes(inbound.id))
            .map((inbound) => inbound.name);

        return `${server.name} (${inboundNames.join(', ') || 'подключения не выбраны'})`;
    })
    .join(', '));
const templateMessage = computed(() => {
    if (template.value.category === 'payment') return template.value.customMessage.trim() ? 'Не получается оплатить подписку.\n\n' + template.value.customMessage : '';
    if (template.value.category === 'other') return template.value.customMessage;

    const app = template.value.application === 'other' ? template.value.otherApplication : template.value.application;
    return [
        'VPN не работает',
        `Что не работает: ${template.value.issueTypes.length ? template.value.issueTypes.join(', ') : 'не указано'}`,
        `Приложение: ${app || 'не указано'}`,
        `Серверы: ${serverConnectionSummary.value || 'не указаны'}`,
        `Пробовал(а) все конфиги: ${template.value.triedAllConfigs || 'не указано'}`,
        `Обновлял(а) приложение: ${template.value.updatedApplication || 'не указано'}`,
        template.value.customMessage.trim(),
    ].filter(Boolean).join('\n');
});

const selectAttachments = (event) => {
    attachments.value = Array.from(event.target.files ?? []).slice(0, 5);
};

const loadTickets = async () => {
    const response = await telegramMiniAppApi.get(telegramMiniAppEndpoints.supportTickets);

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
    if (templateMessage.value.trim() === '') {
        error.value = 'Опишите вопрос или проблему.';
        return;
    }

    sending.value = true;
    error.value = '';

    try {
        const payload = new FormData();
        payload.append('subject', form.value.subject || (template.value.category === 'vpn' ? 'VPN не работает' : template.value.category === 'payment' ? 'Проблема с оплатой' : 'Другой вопрос'));
        payload.append('message', templateMessage.value);
        attachments.value.forEach((file) => payload.append('attachments[]', file));
        const response = await telegramMiniAppApi.post(telegramMiniAppEndpoints.supportTickets, payload);
        const ticketId = response.data?.ticket?.id;

        form.value = {
            subject: '',
        };
        attachments.value = [];

        if (ticketId) {
            window.location.href = `${telegramMiniAppRoutes.support}/${ticketId}`;
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
    if (redirectFromTelegramStartParam(telegramMiniAppRoutes)) {
        return;
    }

    try {
        user.value = await ensureTelegramAppSession({
            authUrl: telegramMiniAppApiEndpoint(telegramMiniAppEndpoints.authTelegram),
            profileUrl: telegramMiniAppApiEndpoint(telegramMiniAppEndpoints.profile),
        });
        await loadTickets();
        const options = await telegramMiniAppApi.get(telegramMiniAppEndpoints.supportOptions);
        servers.value = options.data?.servers ?? [];
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

                <Link :href="telegramMiniAppRoutes.chats" class="tg-list-card">
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
                        <label for="support-category">Тема обращения</label>
                        <select id="support-category" v-model="template.category" class="tg-input">
                            <option value="vpn">Не работает VPN</option>
                            <option value="payment">Не получается оплатить подписку</option>
                            <option value="other">Другой вопрос</option>
                        </select>
                    </div>

                    <template v-if="template.category === 'vpn'">
                        <div class="tg-field">
                            <span>Что именно не работает?</span>
                            <label><input v-model="template.issueTypes" type="checkbox" value="Xray / VLESS"> Xray / VLESS</label>
                            <label><input v-model="template.issueTypes" type="checkbox" value="Amnezia"> Amnezia</label>
                        </div>
                        <div class="tg-field">
                            <span>Приложение</span>
                            <label><input v-model="template.application" type="radio" value="Incy"> Incy</label>
                            <label><input v-model="template.application" type="radio" value="Happ"> Happ</label>
                            <label><input v-model="template.application" type="radio" value="V2RayTun"> V2RayTun</label>
                            <label><input v-model="template.application" type="radio" value="other"> Другое</label>
                            <input v-if="template.application === 'other'" v-model="template.otherApplication" class="tg-input" type="text" placeholder="Название приложения">
                        </div>
                        <div class="tg-field">
                            <span>Какие серверы затронуты?</span>
                            <label v-for="server in servers" :key="server.id"><input v-model="template.serverIds" type="checkbox" :value="server.id"> {{ server.name }}</label>
                        </div>
                        <div class="tg-field">
                            <span>Какие подключения?</span>
                            <template v-for="server in servers.filter((item) => template.serverIds.includes(item.id))" :key="server.id">
                                <strong>{{ server.name }}</strong>
                                <label v-for="inbound in server.inbounds" :key="inbound.id"><input v-model="template.inboundIds" type="checkbox" :value="inbound.id"> {{ inbound.name }}</label>
                            </template>
                        </div>
                        <div class="tg-field">
                            <span>Пробовали все конфиги?</span>
                            <label><input v-model="template.triedAllConfigs" type="radio" value="Да"> Да</label>
                            <label><input v-model="template.triedAllConfigs" type="radio" value="Нет"> Нет</label>
                        </div>
                        <div class="tg-field">
                            <span>Обновляли приложение?</span>
                            <label><input v-model="template.updatedApplication" type="radio" value="Да"> Да</label>
                            <label><input v-model="template.updatedApplication" type="radio" value="Нет"> Нет</label>
                        </div>
                    </template>

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
                        <label for="support-message">{{ template.category === 'vpn' ? 'Дополнительная информация' : 'Сообщение' }}</label>
                        <textarea
                            id="support-message"
                            v-model="template.customMessage"
                            class="tg-textarea"
                            placeholder="Добавьте детали обращения"
                        ></textarea>
                    </div>

                    <div class="tg-field">
                        <label for="support-files">Файлы (до 5, изображения или PDF, до 5 МБ каждый)</label>
                        <input id="support-files" type="file" accept="image/*,application/pdf" multiple @change="selectAttachments">
                        <small v-if="attachments.length">{{ attachments.map((file) => file.name).join(', ') }}</small>
                    </div>

                    <div class="tg-note">
                        <strong>Так будет выглядеть сообщение</strong>
                        <p style="white-space: pre-wrap">{{ templateMessage }}</p>
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
                    :href="`${telegramMiniAppRoutes.support}/${ticket.id}`"
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
