<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '../Shared/AppIcon.vue';
import FlashMessages from '../Shared/FlashMessages.vue';

const page = usePage();
const navigationSections = computed(() => page.props.app.navigation ?? []);
const currentUser = computed(() => page.props.auth?.user ?? null);
const environment = computed(() => page.props.app?.environment ?? null);
const searchQuery = ref('');
const isMobile = ref(false);
const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);
const collapseStorageKey = 'vpn-admin-sidebar-collapsed';
const sectionStorageKey = 'vpn-admin-sidebar-sections';
const logoutForm = useForm({});
const openSections = ref({});

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

const sidebarSectionLabel = (section) => {
    if (!Array.isArray(section.items)) {
        return section.section;
    }

    const activeItem = section.items.find((item) => isActive(item.href));

    return activeItem?.label ?? section.section;
};

const toggleSection = (sectionName) => {
    openSections.value = {
        ...openSections.value,
        [sectionName]: !openSections.value[sectionName],
    };

    window.localStorage.setItem(sectionStorageKey, JSON.stringify(openSections.value));
};

const isSectionOpen = (sectionName) => openSections.value[sectionName] !== false;

const logout = () => {
    logoutForm.post('/logout');
};

onMounted(() => {
    isSidebarCollapsed.value = window.localStorage.getItem(collapseStorageKey) === 'true';
    const storedSections = window.localStorage.getItem(sectionStorageKey);

    if (storedSections) {
        try {
            openSections.value = JSON.parse(storedSections);
        } catch {
            openSections.value = {};
        }
    }

    if (!Object.keys(openSections.value).length) {
        openSections.value = Object.fromEntries(
            navigationSections.value.map((section) => [section.section, true]),
        );
    }

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
                        <span class="brand__copy">
                            <strong class="brand__label">{{ page.props.app.name }}</strong>
                            <span class="brand__subline">Admin Panel</span>
                        </span>
                    </Link>

                    <div class="shell__sidebar-header-actions">
                        <span v-if="environment" class="environment-badge" :class="`environment-badge--${environment.tone}`">
                            {{ environment.label }}
                        </span>

                        <button
                            type="button"
                            class="shell__icon-button shell__icon-button--sidebar"
                            @click="closeSidebar"
                        >
                            <span class="sr-only">Close navigation</span>
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>
                </div>

                <div class="sidebar-nav">
                    <section
                        v-for="section in navigationSections"
                        :key="section.section"
                        class="sidebar-nav__section"
                        :class="{ 'is-open': isSectionOpen(section.section) }"
                    >
                        <button
                            type="button"
                            class="sidebar-nav__section-toggle"
                            @click="toggleSection(section.section)"
                            :title="isSidebarCollapsed ? sidebarSectionLabel(section) : ''"
                        >
                            <span class="sidebar-nav__section-heading">
                                <AppIcon v-if="section.icon" :name="section.icon" class="sidebar-nav__section-glyph" />
                                <span class="sidebar-nav__section-title">{{ section.section }}</span>
                            </span>
                            <span class="sidebar-nav__section-icon" aria-hidden="true">⌄</span>
                        </button>

                        <nav v-show="isSectionOpen(section.section)" class="sidebar-nav__group">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="sidebar-nav__link"
                                :class="{ 'is-active': isActive(item.href) }"
                                :title="isSidebarCollapsed ? item.label : ''"
                            >
                                <span class="sidebar-nav__badge">
                                    <AppIcon v-if="item.icon" :name="item.icon" class="sidebar-nav__badge-icon" />
                                    <span v-else>{{ item.badge }}</span>
                                </span>
                                <span class="sidebar-nav__label">{{ item.label }}</span>
                            </Link>
                        </nav>
                    </section>
                </div>

                <div v-if="currentUser" class="sidebar-profile">
                    <div class="sidebar-profile__copy">
                        <strong>{{ currentUser.telegram || currentUser.name }}</strong>
                        <span>{{ currentUser.is_admin ? 'Администратор' : 'Оператор' }}</span>
                    </div>

                    <button class="button button--ghost sidebar-profile__logout" type="button" @click="logout">
                        Выйти
                    </button>
                </div>
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

                <form class="topbar-search" @submit.prevent>
                    <AppIcon name="search" class="topbar-search__icon" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Поиск по ID, username, UUID, IP или транзакции"
                        aria-label="Глобальный поиск"
                    >
                </form>

                <div class="shell__topbar-spacer" />

                <div class="shell__topbar-meta">
                    <span v-if="environment" class="environment-badge environment-badge--outline" :class="`environment-badge--${environment.tone}`">
                        {{ environment.label }}
                    </span>

                    <div v-if="currentUser" class="shell__userbar">
                        <div class="shell__usercopy">
                            <strong>{{ currentUser.telegram || currentUser.name }}</strong>
                            <span>{{ currentUser.is_admin ? 'Администратор' : currentUser.name }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="shell__content stack">
                <FlashMessages />
                <slot />
            </main>
        </div>
    </div>
</template>
