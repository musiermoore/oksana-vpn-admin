<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    title: String,
    description: String,
    routes: Object,
    user: Object,
});

const navItems = [
    { hrefKey: 'home', label: 'Главная', icon: 'home' },
    { hrefKey: 'payments', label: 'Подписка', icon: 'crown' },
    { hrefKey: 'support', label: 'Поддержка', icon: 'chat' },
];

const currentPath = computed(() => window.location.pathname.replace(/\/+$/, '') || '/telegram-app');
const isProfileOpen = ref(false);

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

const profileName = computed(() => props.user?.name || props.user?.telegram || 'Пользователь');

const isActive = (href) => {
    const normalized = (href ?? '').replace(/\/+$/, '');

    if (normalized === '') {
        return false;
    }

    return currentPath.value === normalized || currentPath.value.startsWith(`${normalized}/`);
};

const iconPath = (name) => {
    if (name === 'home') {
        return 'M3.75 10.5 12 4l8.25 6.5v8.25a.75.75 0 0 1-.75.75h-4.5V14.25h-6V19.5H4.5a.75.75 0 0 1-.75-.75Z';
    }

    if (name === 'crown') {
        return 'M4 17.25h16l-1.4-8.25-4.6 3.6L12 6.75 8 12.6 3.4 9zM6.25 19.5h11.5';
    }

    return 'M6.75 8.25h10.5A2.25 2.25 0 0 1 19.5 10.5v5.25A2.25 2.25 0 0 1 17.25 18H11.5l-3.75 2.25V18H6.75A2.25 2.25 0 0 1 4.5 15.75V10.5a2.25 2.25 0 0 1 2.25-2.25Z';
};
</script>

<template>
    <Head :title="title" />

    <div class="tg-app">
        <div class="tg-app__backdrop"></div>
        <div class="tg-app__stars"></div>

        <header class="tg-shell">
            <section class="tg-hero">
                <div class="tg-hero__brand">
                    <div class="tg-brand-mark" aria-hidden="true">
                        <span class="tg-brand-mark__core"></span>
                    </div>

                    <div class="tg-hero__copy">
                        <span class="tg-hero__eyebrow">Telegram Mini App</span>
                        <h1>{{ title }}</h1>
                        <p>{{ description }}</p>
                    </div>
                </div>

                <button v-if="user" class="tg-profile-button" type="button" @click="isProfileOpen = !isProfileOpen">
                    <span>Профиль</span>
                    <strong>{{ profileName }}</strong>
                </button>
            </section>

            <section v-if="user && isProfileOpen" class="tg-profile-panel">
                <div class="tg-profile-panel__row">
                    <span>Имя</span>
                    <strong>{{ user.name || 'Пользователь' }}</strong>
                </div>
                <div class="tg-profile-panel__row">
                    <span>Telegram</span>
                    <strong>{{ user.telegram || 'Не указан' }}</strong>
                </div>
                <div class="tg-profile-panel__row">
                    <span>Баланс</span>
                    <strong>{{ user.balance ?? 0 }} ₽</strong>
                </div>
                <div class="tg-profile-panel__row">
                    <span>Статус</span>
                    <strong>{{ formatSubscriptionDate(user.subscription_expires_at) }}</strong>
                </div>
            </section>

            <main class="tg-main">
                <slot />
            </main>
        </header>

        <nav class="tg-nav tg-nav--bottom">
            <Link
                v-for="item in navItems"
                :key="item.hrefKey"
                :href="routes?.[item.hrefKey]"
                class="tg-nav__item"
                :class="{ 'is-active': isActive(routes?.[item.hrefKey]) }"
            >
                <svg class="tg-nav__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path :d="iconPath(item.icon)" />
                </svg>
                <span class="tg-nav__label">{{ item.label }}</span>
            </Link>
        </nav>
    </div>
</template>
