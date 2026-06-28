<script setup>
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import { onMounted, ref } from 'vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    openTelegramExternalLink,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);

const chatLinks = [
    {
        title: 'Новости',
        description: 'Официальный канал с обновлениями и важными объявлениями.',
        url: 'https://t.me/+DfexxpJzKiFkNzQ6',
    },
    {
        title: 'Флуд',
        description: 'Неформальное общение, вопросы и обсуждения с участниками.',
        url: 'https://t.me/+jG8T4yBk0tg4MWNi',
    },
];

const retry = () => {
    window.location.reload();
};

onMounted(async () => {
    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть страницу бесед.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Беседы"
        description="Новости проекта и отдельная беседа для свободного общения."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Открываем беседы...</h2>
            <p>Подгружаем ссылки на Telegram-сообщества.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось открыть беседы</h2>
            <p>{{ error }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else class="tg-panel">
            <span class="tg-section-label">Сообщество</span>
            <h2>Беседы и каналы</h2>
            <p>Здесь собраны Telegram-ссылки на основные каналы и чаты проекта.</p>

            <div class="tg-link-list">
                <button
                    v-for="item in chatLinks"
                    :key="item.title"
                    class="tg-row-link tg-row-link--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-row-link__copy">
                        <strong>{{ item.title }}</strong>
                        <span>{{ item.description }}</span>
                    </div>
                    <span class="tg-link-pill">Открыть</span>
                </button>
            </div>
        </section>
    </TelegramMiniAppFrame>
</template>
