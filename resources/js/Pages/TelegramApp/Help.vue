<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
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
    { title: 'Amnezia iOS', url: 'https://apps.apple.com/app/amnezia-vpn/id1600529900' },
    { title: 'Amnezia Android', url: 'https://play.google.com/store/apps/details?id=org.amnezia.vpn' },
    { title: 'Сайт Amnezia', url: 'https://amnezia.org/' },
    { title: 'WireGuard iOS', url: 'https://apps.apple.com/us/app/wireguard/id1441195209' },
    { title: 'WireGuard Android', url: 'https://play.google.com/store/apps/details?id=com.wireguard.android' },
    { title: 'Сайт WireGuard', url: 'https://www.wireguard.com/install/' },
];

const vlessClients = [
    { title: 'v2raytun iOS', url: 'https://apps.apple.com/us/app/v2raytun/id6476628951' },
    { title: 'v2raytun Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android' },
    { title: 'Сайт v2raytun', url: 'https://v2raytun.com/' },
    { title: 'Happ Android', url: 'https://play.google.com/store/apps/details?id=su.happ.crypto' },
    { title: 'Happ iOS', url: 'https://apps.apple.com/us/app/happ-proxy-utility/id6504287215' },
    { title: 'Сайт Happ', url: 'https://happ.su/' },
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
    if (section.value === 'wg') {
        section.value = 'menu';
        return;
    }

    if (section.value === 'vless') {
        section.value = 'menu';
        return;
    }

    if (section.value === 'clients') {
        section.value = 'menu';
        return;
    }

    if (section.value === 'wg-clients' || section.value === 'vless-clients') {
        section.value = previousClientsScreen.value;
    }
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
        error.value = normalizeTelegramAppError(requestError, 'Не удалось загрузить раздел помощи.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Помощь"
        description="Инструкции по настройке, список клиентов и полезные ссылки."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-state-panel">
            <div class="tg-state-orbit">
                <span class="tg-state-orbit__core"></span>
            </div>
            <h2>Открываем помощь...</h2>
            <p>Подгружаем инструкции и список клиентов.</p>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-panel">
            <div class="tg-state-orbit tg-state-orbit--danger">
                <span class="tg-state-orbit__core">!</span>
            </div>
            <h2>Не удалось открыть помощь</h2>
            <p>{{ error }}</p>
            <button class="button tg-button-full" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section v-if="section === 'menu'" class="tg-panel tg-panel-stack">
                <h2 class="tg-help-menu-title">Чем помочь?</h2>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openSection('wg')">WG</button>
                    <button class="button tg-button-full" type="button" @click="openSection('vless')">VLESS</button>
                    <button class="button tg-button-full" type="button" @click="openSection('clients')">Клиенты</button>
                    <Link :href="routes?.support" class="button button--secondary tg-button-full">Поддержка</Link>
                    <Link :href="routes?.home" class="button button--secondary tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else-if="section === 'wg'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">WG</span>
                <h2>Как подключить WireGuard</h2>
                <p>Установите клиент, откройте экран WireGuard в mini-app и выберите один из способов импорта: QR Code или файл конфигурации.</p>
                <p>После импорта убедитесь, что туннель активирован и подключение запущено.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openSection('wg-clients')">WG клиенты</button>
                    <button class="button button--secondary tg-button-full" type="button" @click="goBack">Назад</button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else-if="section === 'vless'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">VLESS</span>
                <h2>Как подключить VLESS</h2>
                <p>Откройте экран VLESS, нажмите `Link` и импортируйте подписку в поддерживаемый клиент через deep link.</p>
                <p>Если приложение не поддерживает deep link, используйте raw-ссылку или QR-код.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openSection('vless-clients')">VLESS клиенты</button>
                    <button class="button button--secondary tg-button-full" type="button" @click="goBack">Назад</button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else-if="section === 'clients'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">Клиенты</span>
                <h2>Выберите тип клиента</h2>
                <p>Здесь собраны приложения для обеих схем подключения.</p>

                <div class="tg-stack-actions">
                    <button class="button tg-button-full" type="button" @click="openSection('wg-clients')">WG клиенты</button>
                    <button class="button tg-button-full" type="button" @click="openSection('vless-clients')">VLESS клиенты</button>
                    <button class="button button--secondary tg-button-full" type="button" @click="goBack">Назад</button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else-if="section === 'wg-clients'" class="tg-panel tg-panel-stack">
                <span class="tg-section-label">WG клиенты</span>
                <h2>Приложения для WireGuard</h2>

                <div class="tg-link-list">
                    <button
                        v-for="item in wgClients"
                        :key="item.title"
                        class="tg-row-link tg-row-link--button"
                        type="button"
                        @click="openTelegramExternalLink(item.url)"
                    >
                        <div class="tg-row-link__copy">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.url }}</span>
                        </div>
                        <span class="tg-link-pill">Открыть</span>
                    </button>
                </div>

                <div class="tg-stack-actions">
                    <button class="button button--secondary tg-button-full" type="button" @click="goBack">Назад</button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>

            <section v-else class="tg-panel tg-panel-stack">
                <span class="tg-section-label">VLESS клиенты</span>
                <h2>Приложения для VLESS</h2>

                <div class="tg-link-list">
                    <button
                        v-for="item in vlessClients"
                        :key="item.title"
                        class="tg-row-link tg-row-link--button"
                        type="button"
                        @click="openTelegramExternalLink(item.url)"
                    >
                        <div class="tg-row-link__copy">
                            <strong>{{ item.title }}</strong>
                            <span>{{ item.url }}</span>
                        </div>
                        <span class="tg-link-pill">Открыть</span>
                    </button>
                </div>

                <div class="tg-stack-actions">
                    <button class="button button--secondary tg-button-full" type="button" @click="goBack">Назад</button>
                    <Link :href="routes?.home" class="button tg-button-full">К началу</Link>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>
