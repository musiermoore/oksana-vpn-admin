<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    fetchTelegramBinary,
    getFilenameFromDisposition,
    isTelegramDebtError,
    normalizeTelegramAppError,
    telegramAppHeaders,
    triggerBrowserDownload,
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
const qrImageUrl = ref('');
const downloadedFilename = ref('');

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
    step.value = 'actions';
};

const showQrCode = async () => {
    if (!selectedConfig.value) {
        return;
    }

    loadingAction.value = true;
    actionError.value = '';
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

const downloadConfig = async () => {
    if (!selectedConfig.value) {
        return;
    }

    loadingAction.value = true;
    actionError.value = '';

    try {
        const response = await fetchTelegramBinary(selectedConfig.value.download_url);
        const filename = getFilenameFromDisposition(
            response.headers['content-disposition'],
            `${selectedConfig.value.name || 'wireguard'}.conf`,
        );

        triggerBrowserDownload(response.data, filename);
        downloadedFilename.value = filename;
        step.value = 'file';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось скачать конфиг.');
    } finally {
        loadingAction.value = false;
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
        description="Выберите конфиг, получите QR-код или скачайте готовый файл."
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
                <p>Выберите, как удобнее получить WireGuard-конфиг.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" :disabled="loadingAction" @click="showQrCode">
                        {{ loadingAction ? 'Загружаем...' : 'QR Code' }}
                    </button>
                    <button class="button tg-button-full" type="button" :disabled="loadingAction" @click="downloadConfig">
                        {{ loadingAction ? 'Подготавливаем...' : 'Файл' }}
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
                    <button class="button button--secondary tg-button-full" type="button" @click="resetToList">
                        Конфиги
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else class="tg-panel">
                <span class="tg-section-label">Файл</span>
                <h2>Файл отправлен</h2>
                <p>Конфиг уже скачан на устройство. Если браузер спросил путь, сохраните файл и откройте его в клиенте WireGuard.</p>
                <div class="tg-inline-callout">
                    <span>Файл</span>
                    <strong>{{ downloadedFilename }}</strong>
                </div>

                <div class="tg-stack-actions">
                    <button class="button button--secondary tg-button-full" type="button" @click="resetToList">
                        Конфиги
                    </button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
