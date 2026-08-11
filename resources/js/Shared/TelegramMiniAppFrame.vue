<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';

const THEME_STORAGE_KEY = 'telegram-mini-app-theme';

const props = defineProps({
    title: String,
    description: String,
    routes: Object,
    user: Object,
});

const theme = ref('light');

const navItems = computed(() => ([
    { href: props.routes?.home, label: 'Главная', icon: 'home', keys: ['/telegram-app'], exact: true },
    { href: props.routes?.wireguard, label: 'WG', icon: 'shield', keys: ['/telegram-app/wireguard'] },
    { href: props.routes?.vless, label: 'VLESS', icon: 'link', keys: ['/telegram-app/vless', '/telegram-app/vless-wl'] },
    { href: props.routes?.payments, label: 'Подписка', icon: 'receipt', keys: ['/telegram-app/payments'] },
    { href: props.routes?.help, label: 'Помощь', icon: 'circleQuestion', keys: ['/telegram-app/help', '/telegram-app/support'] },
    { href: props.routes?.chats, label: 'Чаты', icon: 'chat', keys: ['/telegram-app/chats'] },
    { href: props.routes?.giveaway, label: 'Розыгрыш', icon: 'gift', keys: ['/telegram-app/giveaway'] },
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

const isActive = (item) => item.keys.some((prefix) => (
    item.exact
        ? currentPath.value === prefix
        : currentPath.value === prefix || currentPath.value.startsWith(`${prefix}/`)
));

onMounted(() => {
    theme.value = resolveInitialTheme();
});

watch(theme, (value) => {
    window.localStorage.setItem(THEME_STORAGE_KEY, value);
    document.documentElement.style.colorScheme = value;
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
                </Link>
            </div>
        </nav>
    </div>
</template>
