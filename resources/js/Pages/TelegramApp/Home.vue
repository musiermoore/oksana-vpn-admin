<script setup>
import { onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import { ensureTelegramAppSession, getTelegramProfile, normalizeTelegramAppError } from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const telegramProfile = ref(null);

const formatSubscriptionDate = (value) => {
    if (!value) {
        return 'Подписка не активна';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Подписка не активна'
        : `Подписка активна до ${date.toLocaleDateString('ru-RU', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        })}`;
};

const retry = () => {
    window.location.reload();
};

onMounted(async () => {
    telegramProfile.value = getTelegramProfile();

    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть приложение.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="OksanaVPN"
        description="Ваш безопасный доступ к интернету"
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
            <section class="tg-panel tg-home-intro">
                <div class="tg-home-intro__brand">
                    <div class="tg-brand-mark tg-brand-mark--large" aria-hidden="true">
                        <span class="tg-brand-mark__core"></span>
                    </div>

                    <div class="tg-home-intro__copy">
                        <h2>OksanaVPN</h2>
                        <p>Ваш безопасный доступ к интернету</p>
                    </div>
                </div>
            </section>

            <section class="tg-panel">
                <span class="tg-section-label">Статус подключения</span>

                <div class="tg-status-row tg-status-row--success">
                    <div class="tg-status-icon" aria-hidden="true">✓</div>
                    <div class="tg-status-copy">
                        <strong>Ваш доступ активен</strong>
                        <span>Подключение готово к использованию</span>
                    </div>
                </div>

                <div class="tg-rows">
                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">✦</div>
                        <div class="tg-row-link__copy">
                            <strong>{{ formatSubscriptionDate(user?.subscription_expires_at) }}</strong>
                            <span>Текущий период доступа</span>
                        </div>
                    </div>

                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">@</div>
                        <div class="tg-row-link__copy">
                            <strong>Профиль</strong>
                            <span>{{ user?.telegram || telegramProfile?.username || user?.name || 'Данные аккаунта' }}</span>
                        </div>
                    </div>

                    <div class="tg-row-link">
                        <div class="tg-row-link__icon" aria-hidden="true">₽</div>
                        <div class="tg-row-link__copy">
                            <strong>Баланс</strong>
                            <span>{{ user?.balance ?? 0 }} ₽</span>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
