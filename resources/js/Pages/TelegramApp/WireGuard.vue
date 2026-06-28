<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    fetchTelegramBinary,
    isTelegramDebtError,
    normalizeTelegramAppError,
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    wireguard_configs_url: String,
});

const state = ref('loading');
const step = ref('list');
const error = ref('');
const debtMessage = ref('');
const actionError = ref('');
const user = ref(null);
const configs = ref([]);
const selectedConfig = ref(null);
const loadingAction = ref(false);
const sendingToBot = ref(false);
const qrImageUrl = ref('');
const actionStatus = ref('');

const revokeQrUrl = () => {
    if (qrImageUrl.value) {
        URL.revokeObjectURL(qrImageUrl.value);
        qrImageUrl.value = '';
    }
};

const retry = () => {
    window.location.reload();
};

const resetToList = () => {
    actionError.value = '';
    actionStatus.value = '';
    selectedConfig.value = null;
    step.value = 'list';
    revokeQrUrl();
};

const loadConfigs = async () => {
    user.value = await ensureTelegramAppSession({
        authUrl: props.auth_url,
        profileUrl: props.profile_url,
    });

    const response = await window.axios.get(props.wireguard_configs_url, {
        headers: telegramAppHeaders(),
    });

    configs.value = response.data?.configs ?? [];
    state.value = configs.value.length > 0 ? 'ready' : 'empty';
};

const selectConfig = (config) => {
    selectedConfig.value = config;
    actionError.value = '';
    actionStatus.value = '';
    step.value = 'actions';
};

const showQrCode = async () => {
    if (!selectedConfig.value) {
        return;
    }

    loadingAction.value = true;
    actionError.value = '';
    actionStatus.value = '';
    revokeQrUrl();

    try {
        const response = await fetchTelegramBinary(selectedConfig.value.qr_code_url);
        qrImageUrl.value = URL.createObjectURL(response.data);
        step.value = 'qr';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось получить QR-код.');
    } finally {
        loadingAction.value = false;
    }
};

const sendConfigToBot = async () => {
    if (!selectedConfig.value) {
        return;
    }

    sendingToBot.value = true;
    actionError.value = '';
    actionStatus.value = '';

    try {
        const response = await window.axios.post(selectedConfig.value.send_file_to_bot_url, {}, {
            headers: telegramAppHeaders(),
        });
        actionStatus.value = response.data?.message ?? 'Файл отправлен в бот.';
        step.value = 'file';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить файл в бота.');
    } finally {
        sendingToBot.value = false;
    }
};

const sendQrToBot = async () => {
    if (!selectedConfig.value) {
        return;
    }

    sendingToBot.value = true;
    actionError.value = '';
    actionStatus.value = '';

    try {
        const response = await window.axios.post(selectedConfig.value.send_qr_to_bot_url, {}, {
            headers: telegramAppHeaders(),
        });
        actionStatus.value = response.data?.message ?? 'QR-код отправлен в бот.';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить QR-код в бота.');
    } finally {
        sendingToBot.value = false;
    }
};

onMounted(async () => {
    try {
        await loadConfigs();
    } catch (requestError) {
        if (isTelegramDebtError(requestError)) {
            state.value = 'debt';
            debtMessage.value = normalizeTelegramAppError(requestError, 'Доступ к конфигам требует активной подписки.');
            return;
        }

        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить WireGuard-конфиги.');
    }
});

onBeforeUnmount(() => {
    revokeQrUrl();
});
</script>

<template>
    <TelegramMiniAppFrame
        title="WireGuard"
        description="Выберите конфиг, сразу покажите QR-код или отправьте файл в Telegram-бота."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Загружаем конфиги...</h2>
            <p>Сейчас подтянем доступные WireGuard-подключения.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось загрузить конфиги</h2>
            <p>{{ error }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else-if="state === 'debt'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">₽</span>
            </div>
            <h2>Доступ к конфигам закрыт</h2>
            <p>{{ debtMessage }}</p>
            <div class="tg-stack-actions">
                <Link :href="routes?.payments" class="button tg-button-full">Подписка</Link>
                <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
            </div>
        </section>

        <section v-else-if="state === 'empty'" class="tg-empty-panel">
            <h2>Конфиги не найдены</h2>
            <p>Пока нет доступных WireGuard-конфигов для вашего аккаунта.</p>
            <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
        </section>

        <template v-else>
            <section v-if="step === 'list'" class="tg-panel">
                <div class="tg-section-head">
                    <div>
                        <span class="tg-section-label">WireGuard Configs</span>
                        <h2>Выберите конфиг</h2>
                    </div>
                    <span class="badge">{{ configs.length }}</span>
                </div>

                <div class="tg-plan-list">
                    <button
                        v-for="config in configs"
                        :key="config.id"
                        class="tg-plan-option"
                        type="button"
                        @click="selectConfig(config)"
                    >
                        <div class="tg-plan-option__copy">
                            <strong>{{ config.name }}</strong>
                            <span>Нажмите, чтобы открыть действия по конфигу</span>
                        </div>

                        <div class="tg-plan-option__meta">
                            <strong>Открыть</strong>
                        </div>
                    </button>
                </div>

                <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
            </section>

            <section v-else-if="step === 'actions'" class="tg-panel">
                <span class="tg-section-label">Конфиг</span>
                <h2>{{ selectedConfig?.name }}</h2>
                <p>QR-код можно сразу показать на экране, а файл отправить прямо в бота.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" :disabled="loadingAction" @click="showQrCode">
                        {{ loadingAction ? 'Загружаем...' : 'QR Code' }}
                    </button>
                    <button class="button tg-button-full" type="button" :disabled="sendingToBot" @click="sendConfigToBot">
                        {{ sendingToBot ? 'Отправляем...' : 'Отправить файл в бота' }}
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="resetToList">
                        WireGuard Конфиги
                    </button>
                    <Link :href="routes?.vless" class="button button--secondary tg-button-full">VLESS</Link>
                </div>

                <p v-if="actionError" class="field-error">{{ actionError }}</p>
            </section>

            <section v-else-if="step === 'qr'" class="tg-panel">
                <span class="tg-section-label">QR Code</span>
                <h2>{{ selectedConfig?.name }}</h2>
                <p>Отсканируйте QR-код в приложении WireGuard.</p>

                <div class="tg-qr-card">
                    <img v-if="qrImageUrl" :src="qrImageUrl" alt="WireGuard QR code" class="tg-qr-card__image">
                </div>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" :disabled="sendingToBot" @click="sendQrToBot">
                        {{ sendingToBot ? 'Отправляем...' : 'Отправить в бота' }}
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="resetToList">
                        Конфиги
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>

                <p v-if="actionStatus" class="tg-muted">{{ actionStatus }}</p>
                <p v-if="actionError" class="field-error">{{ actionError }}</p>
            </section>

            <section v-else class="tg-panel">
                <span class="tg-section-label">Файл</span>
                <h2>Файл отправлен в бота</h2>
                <p>Откройте диалог с ботом в Telegram и заберите конфиг оттуда.</p>
                <div class="tg-inline-callout">
                    <span>Статус</span>
                    <strong>{{ actionStatus || 'Файл отправлен в бот.' }}</strong>
                </div>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" :disabled="sendingToBot" @click="sendConfigToBot">
                        {{ sendingToBot ? 'Отправляем...' : 'Отправить ещё раз в бота' }}
                    </button>
                    <button class="button button--secondary tg-button-full" type="button" @click="resetToList">
                        Конфиги
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>

                <p v-if="actionError" class="field-error">{{ actionError }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
