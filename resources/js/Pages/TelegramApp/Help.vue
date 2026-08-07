<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
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
const section = ref('menu');
const previousClientsScreen = ref('menu');

const wgClients = [
    { title: 'Amnezia для iPhone', url: 'https://apps.apple.com/app/amnezia-vpn/id1600529900' },
    { title: 'Amnezia для Android', url: 'https://play.google.com/store/apps/details?id=org.amnezia.vpn' },
    { title: 'WireGuard для iPhone', url: 'https://apps.apple.com/us/app/wireguard/id1441195209' },
    { title: 'WireGuard для Android', url: 'https://play.google.com/store/apps/details?id=com.wireguard.android' },
];

const vlessClients = [
    { title: 'V2RayTun для iPhone', url: 'https://apps.apple.com/us/app/v2raytun/id6476628951' },
    { title: 'V2RayTun для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android' },
    { title: 'Happ для Android', url: 'https://play.google.com/store/apps/details?id=su.happ.crypto' },
    { title: 'Happ для iPhone', url: 'https://apps.apple.com/us/app/happ-proxy-utility/id6504287215' },
];

const retry = () => {
    window.location.reload();
};

const openSection = (nextSection) => {
    if (nextSection === 'wg-clients' || nextSection === 'vless-clients') {
        previousClientsScreen.value = section.value;
    }

    section.value = nextSection;
};

const goBack = () => {
    if (section.value === 'wg-clients' || section.value === 'vless-clients') {
        section.value = previousClientsScreen.value;
        return;
    }

    section.value = 'menu';
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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть помощь.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Помощь"
        description="Инструкции и приложения, которые помогут подключиться без лишних шагов."
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
            <h2>Не удалось открыть помощь</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section v-if="section === 'menu'" class="tg-section">
                <div class="tg-page-header__copy">
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="circleQuestion" />
                        <span>Помощь</span>
                    </div>
                    <h2>Что вам нужно?</h2>
                    <p>Выберите короткий путь: инструкция, подходящее приложение или обращение в поддержку.</p>
                </div>

                <button class="tg-list-card tg-list-card--button" type="button" @click="openSection('wg')">
                    <div class="tg-list-card__icon tg-list-card__icon--success">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Как подключить WireGuard</div>
                        <div class="tg-list-card__description">Пошагово: QR-код или файл конфигурации.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <button class="tg-list-card tg-list-card--button" type="button" @click="openSection('vless')">
                    <div class="tg-list-card__icon">
                        <AppIcon name="shield" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Как подключить VLESS</div>
                        <div class="tg-list-card__description">Быстрое подключение, прямая ссылка и QR-код.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <button class="tg-list-card tg-list-card--button" type="button" @click="openSection('clients')">
                    <div class="tg-list-card__icon tg-list-card__icon--blue">
                        <AppIcon name="download" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Скачать приложение</div>
                        <div class="tg-list-card__description">Подберём клиент для iPhone или Android.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </button>

                <Link :href="routes?.support" class="tg-list-card">
                    <div class="tg-list-card__icon tg-list-card__icon--warning">
                        <AppIcon name="headset" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">Написать в поддержку</div>
                        <div class="tg-list-card__description">Если VPN не работает или нужен ответ оператора.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="chevronRight" />
                    </div>
                </Link>
            </section>

            <section v-else-if="section === 'wg'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goBack">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Подключение WireGuard</h2>
                    <p>Откройте экран подключения, выберите конфиг и импортируйте его по QR-коду. Если так удобнее, отправьте файл в Telegram и заберите его из чата с ботом.</p>
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" @click="openSection('wg-clients')">
                        <AppIcon name="download" />
                        <span>Скачать приложение WireGuard</span>
                    </button>
                    <Link :href="routes?.wireguard" class="tg-button">
                        <AppIcon name="shield" />
                        <span>Открыть подключение</span>
                    </Link>
                </div>
            </section>

            <section v-else-if="section === 'vless'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goBack">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Подключение VLESS</h2>
                    <p>Откройте стандартные конфиги, нажмите на подходящее приложение и импортируйте подписку. Если приложение не открывает ссылку, используйте прямую ссылку или QR-код.</p>
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" @click="openSection('vless-clients')">
                        <AppIcon name="download" />
                        <span>Скачать приложение для VLESS</span>
                    </button>
                    <Link :href="routes?.vless" class="tg-button">
                        <AppIcon name="shield" />
                        <span>Открыть стандартные</span>
                    </Link>
                </div>
            </section>

            <section v-else-if="section === 'clients'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goBack">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Выберите тип подключения</h2>
                    <p>Если нужен максимально простой импорт, чаще всего выбирают WireGuard. Если вы пользуетесь приложениями под VLESS, откройте второй список.</p>
                </div>

                <div class="tg-actions">
                    <button class="tg-button tg-button--secondary" type="button" @click="openSection('wg-clients')">
                        <AppIcon name="shield" />
                        <span>Приложения для WireGuard</span>
                    </button>
                    <button class="tg-button tg-button--soft" type="button" @click="openSection('vless-clients')">
                        <AppIcon name="link" />
                        <span>Приложения для VLESS</span>
                    </button>
                </div>
            </section>

            <section v-else-if="section === 'wg-clients'" class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goBack">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Приложения для WireGuard</h2>
                    <p>Выберите магазин приложений для вашего устройства.</p>
                </div>

                <button
                    v-for="item in wgClients"
                    :key="item.title"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-list-card__icon tg-list-card__icon--success">
                        <AppIcon name="arrowUpRight" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">Открыть внешний магазин или сайт.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="arrowUpRight" />
                    </div>
                </button>
            </section>

            <section v-else class="tg-section">
                <div class="tg-page-header__copy">
                    <button class="tg-link-button" type="button" @click="goBack">
                        <AppIcon name="chevronLeft" />
                        <span>Назад</span>
                    </button>
                    <h2>Приложения для VLESS</h2>
                    <p>Откройте нужный магазин, затем вернитесь в mini app и импортируйте подписку по ссылке.</p>
                </div>

                <button
                    v-for="item in vlessClients"
                    :key="item.title"
                    class="tg-list-card tg-list-card--button"
                    type="button"
                    @click="openTelegramExternalLink(item.url)"
                >
                    <div class="tg-list-card__icon">
                        <AppIcon name="arrowUpRight" />
                    </div>
                    <div class="tg-list-card__body">
                        <div class="tg-list-card__title">{{ item.title }}</div>
                        <div class="tg-list-card__description">Открыть внешний магазин или сайт.</div>
                    </div>
                    <div class="tg-list-card__aside">
                        <AppIcon name="arrowUpRight" />
                    </div>
                </button>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
