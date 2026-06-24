<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    title: String,
    description: String,
    routes: Object,
    user: Object,
});

const navItems = [
    { hrefKey: 'home', label: 'Главная' },
    { hrefKey: 'payments', label: 'Оплата' },
    { hrefKey: 'support', label: 'Поддержка' },
];

const currentPath = window.location.pathname.replace(/\/+$/, '') || '/telegram-app';

const isActive = (href) => {
    const normalized = (href ?? '').replace(/\/+$/, '');

    return normalized === currentPath;
};
</script>

<template>
    <Head :title="title" />

    <div class="tg-app">
        <div class="tg-app__backdrop"></div>

        <section class="tg-hero">
            <div class="tg-hero__badge">OksanaVPN</div>
            <h1>{{ title }}</h1>
            <p>{{ description }}</p>

            <div v-if="user" class="tg-hero__meta">
                <div class="tg-mini-stat">
                    <span>Баланс</span>
                    <strong>{{ user.balance ?? 0 }} ₽</strong>
                </div>
                <div class="tg-mini-stat">
                    <span>Профиль</span>
                    <strong>{{ user.telegram || user.name || 'Пользователь' }}</strong>
                </div>
            </div>
        </section>

        <nav class="tg-nav">
            <Link
                v-for="item in navItems"
                :key="item.hrefKey"
                :href="routes?.[item.hrefKey]"
                class="tg-nav__item"
                :class="{ 'is-active': isActive(routes?.[item.hrefKey]) }"
            >
                {{ item.label }}
            </Link>
        </nav>

        <slot />
    </div>
</template>
