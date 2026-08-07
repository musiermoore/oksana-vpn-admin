<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
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
const step = ref('hub');
const error = ref('');
const debtMessage = ref('');
const actionError = ref('');
const actionStatus = ref('');
const user = ref(null);
const configs = ref([]);
const selectedConfig = ref(null);
const loadingAction = ref(false);
const sendingToBot = ref(false);
const qrImageUrl = ref('');

const revokeQrUrl = () => {
    if (qrImageUrl.value) {
        URL.revokeObjectURL(qrImageUrl.value);
        qrImageUrl.value = '';
    }
};

const retry = () => {
    window.location.reload();
};

const goToConfigHub = () => {
    step.value = 'hub';
    selectedConfig.value = null;
    actionError.value = '';
    actionStatus.value = '';
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

const openWireGuardList = () => {
    actionError.value = '';
    actionStatus.value = '';
    selectedConfig.value = null;
    step.value = 'list';
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
        actionStatus.value = response.data?.message ?? 'Файл отправлен в Telegram.';
        step.value = 'file';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить файл.');
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
        actionStatus.value = response.data?.message ?? 'QR-код отправлен в Telegram.';
    } catch (requestError) {
        actionError.value = normalizeTelegramAppError(requestError, 'Не удалось отправить QR-код.');
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
            debtMessage.value = normalizeTelegramAppError(requestError, 'Для доступа нужна активная подписка.');
            return;
        }

        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить конфиги WireGuard.');
    }
});

onBeforeUnmount(() => {
    revokeQrUrl();
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Конфиги"
        description="Выберите VLESS или WireGuard и получите данные для подключения."
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
            <h2>Не удалось открыть WireGuard</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <section v-else-if="state === 'debt'" class="tg-state-card tg-state-card--warning">
            <div class="tg-state-card__icon">
                <AppIcon name="receipt" />
            </div>
            <h2>Сначала продлите подписку</h2>
            <p>{{ debtMessage }}</p>
            <div class="tg-actions">
                <Link :href="routes?.payments" class="tg-button">Перейти к подписке</Link>
                <Link :href="routes?.home" class="tg-button tg-button--secondary">На главную</Link>
            </div>
        </section>

        <section v-else-if="state === 'empty'" class="tg-state-card">
            <div class="tg-state-card__icon">
                <AppIcon name="shield" />
            </div>
            <h2>Конфиги пока не готовы</h2>
            <p>Для вашего аккаунта ещё нет доступных WireGuard-конфигов.</p>
            <Link :href="routes?.home" class="tg-button">На главную</Link>
        </section>

        <template v-else>
            <section v-if="step === 'hub'" class="tg-section">
                <div class="tg-page-header__copy">
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="shield" />
                        <span>Конфиги</span>
                    </div>
                    <h2>Выберите тип конфигов</h2>
                    <p>Откройте нужный вариант: обычный VLESS, белые списки VLESS или WireGuard.</p>
                </div>

                <Link :href="routes?.vless" class="tg-list-card">
                    <div class="tg-list-card__icon">
                        <AppIcon name="link" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">VLESS</div>
                        <div class="tg-list-card__description">Ссылка для приложений, deep links и QR-код.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>

                <Link v-if="routes?.vless_wl" :href="`${routes.vless_wl}?step=links`" class="tg-list-card">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="lock" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">VLESS (Белые списки)</div>
                        <div class="tg-list-card__description">Отдельные WL-ссылки для поддерживаемых клиентов.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>

                <button class="tg-list-card tg-list-card--button" type="button" @click="openWireGuardList">
                    <div class="tg-list-card__icon tg-list-card__icon--success">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">WireGuard</div>
                        <div class="tg-list-card__description">Открыть список конфигов для импорта по QR-коду или через файл.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>
            </section>

            <section v-else-if="step === 'list'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goToConfigHub">
                        <AppIcon name="chevronLeft" />
                        <span>Назад ко всем конфигам</span>
                    </button>
                    <h2>WireGuard</h2>
                    <p>Выберите конфиг, который хотите открыть, показать по QR-коду или отправить в Telegram.</p>
                </div>

                <button
                    v-for="config in configs"
                    :key="config.id"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="selectConfig(config)"
                >
                    <div class="tg-list-card__icon tg-list-card__icon--success">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ config.name }}</div>
                        <div class="tg-list-card__description">Открыть действия для подключения и импорта.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>
            </section>

            <section v-else-if="step === 'actions'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="openWireGuardList">
                        <AppIcon name="chevronLeft" />
                        <span>Назад к конфигам</span>
                    </button>
                    <h2>{{ selectedConfig?.name }}</h2>
                    <p>Самый быстрый путь: открыть QR-код и импортировать конфиг в приложение WireGuard.</p>
                </div>

                <div class="tg-actions">
                    <button class="tg-button" type="button" :disabled="loadingAction" @click="showQrCode">
                        <AppIcon name="qrcode" />
                        <span>{{ loadingAction ? 'Готовим QR...' : 'Показать QR-код' }}</span>
                    </button>
                    <button class="tg-button tg-button--secondary" type="button" :disabled="sendingToBot" @click="sendConfigToBot">
                        <AppIcon name="download" />
                        <span>{{ sendingToBot ? 'Отправляем...' : 'Отправить файл в Telegram' }}</span>
                    </button>
                    <Link :href="routes?.vless" class="tg-button tg-button--soft">
                        <AppIcon name="link" />
                        <span>Открыть VLESS вместо этого</span>
                    </Link>
                </div>

                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>

            <section v-else-if="step === 'qr'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="step = 'actions'">
                        <AppIcon name="chevronLeft" />
                        <span>Назад к действиям</span>
                    </button>
                    <h2>Сканируйте QR-код</h2>
                    <p>Откройте приложение WireGuard и импортируйте конфиг с экрана.</p>
                </div>

                <div class="tg-qr-card">
                    <img v-if="qrImageUrl" :src="qrImageUrl" alt="WireGuard QR" class="tg-qr-card__image">
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" :disabled="sendingToBot" @click="sendQrToBot">
                        <AppIcon name="send" />
                        <span>{{ sendingToBot ? 'Отправляем...' : 'Отправить QR в Telegram' }}</span>
                    </button>
                    <button class="tg-button tg-button--soft" type="button" @click="openWireGuardList">
                        <AppIcon name="shield" />
                        <span>Выбрать другой конфиг</span>
                    </button>
                </div>

                <p v-if="actionStatus" class="tg-success-text">{{ actionStatus }}</p>
                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>

            <section v-else class="tg-section">
                <div class="tg-status-card tg-status-card--success">
                    <div class="tg-status-card__top">
                        <div>
                            <div class="tg-status-card__title">Файл отправлен</div>
                            <div class="tg-status-card__meta">
                                <span>Откройте чат с ботом и скачайте конфиг оттуда.</span>
                            </div>
                        </div>

                        <div class="tg-status-card__icon">
                            <AppIcon name="circleCheck" />
                        </div>
                    </div>
                </div>

                <div class="tg-actions">
                    <button class="tg-button" type="button" :disabled="sendingToBot" @click="sendConfigToBot">
                        <AppIcon name="send" />
                        <span>{{ sendingToBot ? 'Отправляем...' : 'Отправить ещё раз' }}</span>
                    </button>
                    <button class="tg-button tg-button--secondary" type="button" @click="openWireGuardList">
                        <AppIcon name="shield" />
                        <span>К списку конфигов</span>
                    </button>
                </div>

                <p v-if="actionStatus" class="tg-success-text">{{ actionStatus }}</p>
                <p v-if="actionError" class="tg-error">{{ actionError }}</p>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
