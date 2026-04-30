<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FlashMessages from '../Shared/FlashMessages.vue';

const page = usePage();
const navigation = computed(() => page.props.app.navigation ?? []);
const isMobile = ref(false);
const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);
const collapseStorageKey = 'vpn-admin-sidebar-collapsed';

const normalizePath = (value) => {
    if (!value) {
        return '/';
    }

    try {
        const url = new URL(value, window.location.origin);
        const path = url.pathname.replace(/\/+$/, '');

        return path || '/';
    } catch {
        const path = value.split('?')[0].replace(/\/+$/, '');

        return path || '/';
    }
};

const isActive = (href) => {
    const currentPath = normalizePath(page.url);
    const targetPath = normalizePath(href);

    return targetPath === '/'
        ? currentPath === '/'
        : currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
};

const syncViewport = () => {
    isMobile.value = window.innerWidth < 992;

    if (!isMobile.value) {
        isSidebarOpen.value = false;
    }
};

const toggleSidebar = () => {
    if (isMobile.value) {
        isSidebarOpen.value = !isSidebarOpen.value;
        return;
    }

    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    window.localStorage.setItem(collapseStorageKey, String(isSidebarCollapsed.value));
};

const closeSidebar = () => {
    isSidebarOpen.value = false;
};

onMounted(() => {
    isSidebarCollapsed.value = window.localStorage.getItem(collapseStorageKey) === 'true';
    syncViewport();
    window.addEventListener('resize', syncViewport);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncViewport);
});

watch(
    () => page.url,
    () => {
        if (isMobile.value) {
            isSidebarOpen.value = false;
        }
    },
);
</script>

<template>
    <div
        class="shell shell--app"
        :class="{
            'shell--sidebar-collapsed': isSidebarCollapsed && !isMobile,
            'shell--sidebar-open': isSidebarOpen,
        }"
    >
        <div
            v-if="isMobile"
            class="shell__backdrop"
            :class="{ 'is-visible': isSidebarOpen }"
            @click="closeSidebar"
        />

        <aside class="shell__sidebar">
            <div class="shell__sidebar-inner">
                <div class="shell__sidebar-header">
                    <Link class="brand" href="/">
                        <span class="brand__mark">WG</span>
                        <span class="brand__label">{{ page.props.app.name }}</span>
                    </Link>

                    <button
                        type="button"
                        class="shell__icon-button shell__icon-button--sidebar"
                        @click="closeSidebar"
                    >
                        <span class="sr-only">Close navigation</span>
                        <span aria-hidden="true">×</span>
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="sidebar-nav__link"
                        :class="{ 'is-active': isActive(item.href) }"
                    >
                        <span class="sidebar-nav__badge">{{ item.badge }}</span>
                        <span class="sidebar-nav__label">{{ item.label }}</span>
                    </Link>
                </nav>
            </div>
        </aside>

        <div class="shell__main">
            <header class="shell__topbar">
                <button type="button" class="shell__icon-button" @click="toggleSidebar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="shell__burger" aria-hidden="true">
                        <span />
                        <span />
                        <span />
                    </span>
                </button>

                <Link class="brand brand--mobile" href="/">
                    <span class="brand__mark">WG</span>
                    <span>{{ page.props.app.name }}</span>
                </Link>
            </header>

            <main class="shell__content stack">
                <FlashMessages />
                <slot />
            </main>
        </div>
    </div>
</template>
