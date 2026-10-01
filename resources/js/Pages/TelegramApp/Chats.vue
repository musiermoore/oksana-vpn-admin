<script setup>
import { onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    openTelegramExternalLink,
} from '../../lib/telegramMiniApp';
import { telegramMiniAppEndpoint, telegramMiniAppEndpoints } from '../../lib/telegramMiniAppApi';
import { telegramChatLinks } from '../../lib/telegramChatLinks';

const state = ref('loading');
const error = ref('');
const user = ref(null);

const chatLinks = telegramChatLinks;

const retry = () => {
    window.location.reload();
};

onMounted(async () => {
    try {
        user.value = await ensureTelegramAppSession({
            authUrl: telegramMiniAppEndpoint(telegramMiniAppEndpoints.authTelegram),
            profileUrl: telegramMiniAppEndpoint(telegramMiniAppEndpoints.profile),
        });
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть чаты.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Сообщество"
        description="Канал с обновлениями и чат, где можно задать быстрый вопрос."
    >
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось открыть чаты</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else class="tg-section">
            <div class="tg-page-header__copy">
                <div class="tg-tag">
                    <AppIcon name="chat" />
                    <span>Чаты</span>
                </div>
                <h2>Где общаться?</h2>
                <p>Откройте официальный канал для новостей или общий чат для общения с пользователями.</p>
            </div>

            <button
                v-for="item in chatLinks"
                :key="item.title"
                class="tg-list-card tg-list-card--button"
                type="button"
                @click="openTelegramExternalLink(item.url)"
            >
                <div class="tg-list-card__icon tg-list-card__icon--blue">
                    <AppIcon name="arrowUpRight" />
                </div>
                <div class="tg-list-card__body">
                    <div class="tg-list-card__title">{{ item.title }}</div>
                    <div class="tg-list-card__description">{{ item.description }}</div>
                </div>
                <div class="tg-list-card__aside">
                    <AppIcon name="arrowUpRight" />
                </div>
            </button>
        </section>
    </TelegramMiniAppFrame>
</template>
