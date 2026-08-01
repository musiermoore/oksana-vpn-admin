<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useLocalStorage, useWindowSize } from '@vueuse/core';
import AppIcon from '../Shared/AppIcon.vue';
import FlashMessages from '../Shared/FlashMessages.vue';

const page = usePage();
const navigationSections = computed(() => page.props.app.navigation ?? []);
const currentUser = computed(() => page.props.auth?.user ?? null);
const searchQuery = ref(typeof page.props.query === 'string' ? page.props.query : '');
const { width } = useWindowSize();
const isSidebarOpen = ref(false);
const isSidebarCollapsed = useLocalStorage('vpn-admin-sidebar-collapsed', false);
const logoutForm = useForm({});
const openSections = useLocalStorage('vpn-admin-sidebar-sections', {});
const isMobile = computed(() => width.value < 992);

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
};

const closeSidebar = () => {
    isSidebarOpen.value = false;
};

const environmentSeverity = (tone) => {
    if (tone === 'danger') {
        return 'danger';
    }

    if (tone === 'warning') {
        return 'warn';
    }

    return 'secondary';
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
};

const isSectionOpen = (sectionName) => openSections.value[sectionName] !== false;

const logout = () => {
    logoutForm.post('/logout');
};

const submitSearch = () => {
    const query = searchQuery.value.trim();

    if (!query) {
        return;
    }

    router.get('/search', { q: query }, {
        preserveState: true,
    });
};

onMounted(() => {
    if (!Object.keys(openSections.value).length) {
        openSections.value = Object.fromEntries(
            navigationSections.value.map((section) => [section.section, true]),
        );
    }

    syncViewport();
});

watch(
    () => page.url,
    () => {
        if (isMobile.value) {
            isSidebarOpen.value = false;
        }
    },
);

watch(isMobile, () => {
    syncViewport();
});
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
                        <span class="brand__copy">
                            <strong class="brand__label">{{ page.props.app.name }}</strong>
                            <span class="brand__subline">Admin Panel</span>
                        </span>
                    </Link>

                    <div class="shell__sidebar-header-actions">
                        <Button
                            type="button"
                            variant="text"
                            rounded
                            class="shell__icon-button shell__icon-button--sidebar"
                            aria-label="Close navigation"
                            @click="closeSidebar"
                        >
                            <AppIcon name="close" />
                        </Button>
                    </div>
                </div>

                <div class="sidebar-nav">
                    <section
                        v-for="section in navigationSections"
                        :key="section.section"
                        class="sidebar-nav__section"
                        :class="{ 'is-open': isSectionOpen(section.section) }"
                    >
                        <Button
                            type="button"
                            variant="text"
                            class="sidebar-nav__section-toggle"
                            :title="isSidebarCollapsed ? sidebarSectionLabel(section) : ''"
                            @click="toggleSection(section.section)"
                        >
                            <span class="sidebar-nav__section-heading">
                                <AppIcon v-if="section.icon" :name="section.icon" class="sidebar-nav__section-glyph" />
                                <span class="sidebar-nav__section-title">{{ section.section }}</span>
                            </span>
                            <AppIcon name="chevronDown" class="sidebar-nav__section-icon" />
                        </Button>

                        <nav v-show="isSectionOpen(section.section)" class="sidebar-nav__group">
                            <Button
                                v-for="item in section.items"
                                :key="item.href"
                                as-child
                                variant="text"
                                v-slot="slotProps"
                            >
                                <Link
                                    :href="item.href"
                                    :class="[slotProps.class, 'sidebar-nav__link', { 'is-active': isActive(item.href) }]"
                                    :title="isSidebarCollapsed ? item.label : ''"
                                    v-bind="slotProps.a11yAttrs"
                                >
                                    <span class="sidebar-nav__badge">
                                        <AppIcon v-if="item.icon" :name="item.icon" class="sidebar-nav__badge-icon" />
                                        <span v-else>{{ item.badge }}</span>
                                    </span>
                                    <span class="sidebar-nav__label">{{ item.label }}</span>
                                </Link>
                            </Button>
                        </nav>
                    </section>
                </div>

                <div v-if="currentUser" class="sidebar-profile">
                    <div class="sidebar-profile__copy">
                        <strong>{{ currentUser.telegram || currentUser.name }}</strong>
                        <span>{{ currentUser.is_admin ? 'Администратор' : 'Оператор' }}</span>
                    </div>

                    <Button type="button" variant="outlined" severity="secondary" class="sidebar-profile__logout" @click="logout">
                        Выйти
                    </Button>
                </div>
            </div>
        </aside>

        <div class="shell__main">
            <header class="shell__topbar">
                <Button type="button" variant="outlined" rounded class="shell__icon-button" aria-label="Toggle navigation" @click="toggleSidebar">
                    <AppIcon name="bars" />
                </Button>

                <Link class="brand brand--mobile" href="/">
                    <span>{{ page.props.app.name }}</span>
                </Link>

                <form class="topbar-search" @submit.prevent="submitSearch">
                    <AppIcon name="search" class="topbar-search__icon" />
                    <InputText
                        v-model="searchQuery"
                        type="search"
                        fluid
                        placeholder="Поиск по ID, username, UUID, IP или транзакции"
                        aria-label="Глобальный поиск"
                    />
                </form>

                <div class="shell__topbar-spacer" />

                <div class="shell__topbar-meta">
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
