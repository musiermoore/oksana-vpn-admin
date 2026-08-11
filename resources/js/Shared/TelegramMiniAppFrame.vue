<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { telegramAppHeaders } from '../lib/telegramMiniApp';

const THEME_STORAGE_KEY = 'telegram-mini-app-theme';
const GIVEAWAY_SUMMARY_REFRESH_EVENT = 'telegram-app:refresh-giveaway-summary';

const props = defineProps({
    title: String,
    description: String,
    routes: Object,
    user: Object,
});

const theme = ref('light');
const giveawaySummary = ref({
    active_giveaways_count: 0,
    pending_participation_count: 0,
});

const navItems = computed(() => ([
    { href: props.routes?.home, label: 'Главная', icon: 'home', keys: ['/telegram-app'], exact: true },
    { href: props.routes?.wireguard, label: 'Конфиги', icon: 'shield', keys: ['/telegram-app/wireguard', '/telegram-app/vless', '/telegram-app/vless-wl'] },
    { href: props.routes?.payments, label: 'Подписка', icon: 'receipt', keys: ['/telegram-app/payments'] },
    { href: props.routes?.help, label: 'Помощь', icon: 'circleQuestion', keys: ['/telegram-app/help', '/telegram-app/support', '/telegram-app/chats'] },
    {
        href: props.routes?.giveaway,
        label: 'Розыгрыш',
        icon: 'gift',
        keys: ['/telegram-app/giveaway'],
        counter: giveawaySummary.value.pending_participation_count > 0
            ? giveawaySummary.value.pending_participation_count
            : null,
    },
]));

const currentPath = computed(() => window.location.pathname.replace(/\/+$/, '') || '/telegram-app');
const themeLabel = computed(() => theme.value === 'dark' ? 'Светлая тема' : 'Тёмная тема');

const resolveInitialTheme = () => {
    const savedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);

    if (savedTheme === 'dark' || savedTheme === 'light') {
        return savedTheme;
    }

    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const toggleTheme = () => {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
};

const loadGiveawaySummary = async () => {
    if (!props.user?.id || !props.routes?.giveaway_summary) {
        giveawaySummary.value = {
            active_giveaways_count: 0,
            pending_participation_count: 0,
        };
        return;
    }

    try {
        const response = await window.axios.get(props.routes.giveaway_summary, {
            headers: telegramAppHeaders(),
        });

        giveawaySummary.value = {
            active_giveaways_count: Number(response.data?.summary?.active_giveaways_count ?? 0),
            pending_participation_count: Number(response.data?.summary?.pending_participation_count ?? 0),
        };
    } catch {
        giveawaySummary.value = {
            active_giveaways_count: 0,
            pending_participation_count: 0,
        };
    }
};

const isActive = (item) => item.keys.some((prefix) => (
    item.exact
        ? currentPath.value === prefix
        : currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`)
));

onMounted(() => {
    theme.value = resolveInitialTheme();
    window.addEventListener(GIVEAWAY_SUMMARY_REFRESH_EVENT, loadGiveawaySummary);
    void loadGiveawaySummary();
});

watch(theme, (value) => {
    window.localStorage.setItem(THEME_STORAGE_KEY, value);
    document.documentElement.style.colorScheme = value;
});

watch(() => props.user?.id, () => {
    void loadGiveawaySummary();
});
</script>

<template>
    <Head :title="title" />

    <div class="tg-app" :data-theme="theme">
        <div class="tg-shell">
            <header class="tg-topbar">
                <div class="tg-brand">
                    <div class="tg-brand__mark" aria-hidden="true">
                        <AppIcon name="shield" />
                    </div>

                    <div class="tg-brand__copy">
                        <h1>{{ title }}</h1>
                        <p>{{ description }}</p>
                    </div>
                </div>

                <button class="tg-icon-button tg-theme-toggle" type="button" :aria-label="themeLabel" @click="toggleTheme">
                    <AppIcon name="lightbulb" />
                </button>
            </header>

            <main class="tg-main">
                <slot />
            </main>
        </div>

        <nav class="tg-bottom-nav">
            <div class="tg-bottom-nav__grid">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="tg-bottom-nav__item"
                    :class="{ 'is-active': isActive(item) }"
                >
                    <AppIcon :name="item.icon" />
                    <span class="tg-bottom-nav__label">{{ item.label }}</span>
                    <span v-if="item.counter" class="tg-bottom-nav__badge">{{ item.counter }}</span>
                </Link>
            </div>
        </nav>
    </div>
</template>

<style scoped>
.tg-bottom-nav__item {
    position: relative;
}

.tg-bottom-nav__badge {
    position: absolute;
    top: 6px;
    right: 12px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    line-height: 1;
}
</style>
