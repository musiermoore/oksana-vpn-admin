<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    title: String,
    description: String,
    routes: Object,
    user: Object,
});

const navItems = [
    { hrefKey: 'home', label: 'Главная', icon: '✦' },
    { hrefKey: 'payments', label: 'Подписка', icon: '◈' },
    { hrefKey: 'support', label: 'Поддержка', icon: '✉' },
];

const currentPath = window.location.pathname.replace(/\/+$/, '') || '/telegram-app';
const isProfileOpen = ref(false);

const formatSubscriptionDate = (value) => {
    if (!value) {
        return 'Неактивна';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Неактивна'
        : date.toLocaleDateString('ru-RU');
};

const isActive = (href) => {
    const normalized = (href ?? '').replace(/\/+$/, '');

    return normalized === currentPath;
};

const toggleProfile = () => {
    isProfileOpen.value = !isProfileOpen.value;
};
</script>

<template>
    <Head :title="title" />

    <div class="tg-app">
        <div class="tg-app__backdrop"></div>

        <section class="tg-hero">
            <div class="tg-hero__top">
                <div class="tg-hero__copy">
                    <div class="tg-hero__badge">OksanaVPN</div>
                    <h1>{{ title }}</h1>
                    <p>{{ description }}</p>
                </div>

                <button v-if="user" class="tg-profile-button" type="button" @click="toggleProfile">
                    <span>Профиль</span>
                    <strong>{{ user.telegram || user.name || 'Пользователь' }}</strong>
                </button>
            </div>
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
                <span>Подписка</span>
                <strong>
                    {{ user.subscription_expires_at ? `Активна до ${formatSubscriptionDate(user.subscription_expires_at)}` : 'Неактивна' }}
                </strong>
            </div>
        </section>

        <slot />

        <nav class="tg-nav tg-nav--bottom">
            <Link
                v-for="item in navItems"
                :key="item.hrefKey"
                :href="routes?.[item.hrefKey]"
                class="tg-nav__item"
                :class="{ 'is-active': isActive(routes?.[item.hrefKey]) }"
            >
                <span class="tg-nav__icon" aria-hidden="true">{{ item.icon }}</span>
                <span class="tg-nav__label">{{ item.label }}</span>
            </Link>
        </nav>
    </div>
</template>
