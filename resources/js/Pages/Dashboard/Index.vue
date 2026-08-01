<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppIcon from '../../Shared/AppIcon.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

defineProps({
    status_strip: {
        type: Array,
        default: () => [],
    },
    quick_actions: {
        type: Array,
        default: () => [],
    },
    create_actions: {
        type: Array,
        default: () => [],
    },
    sections: {
        type: Array,
        default: () => [],
    },
    attention_items: {
        type: Array,
        default: () => [],
    },
});

const buttonClass = (variant) => ({
    button: true,
    'button--secondary': variant === 'secondary',
    'button--ghost': variant === 'ghost',
});
</script>

<template>
    <Head title="Панель управления" />

    <AppPageHeader
        title="Панель управления"
        description="Управление инфраструктурой, пользователями, биллингом и операционными процессами VPN-сервиса."
        eyebrow="Главная"
    >
        <template #actions>
            <div class="dashboard-header-actions">
                <Link
                    v-for="action in quick_actions"
                    :key="action.href"
                    :href="action.href"
                    :class="buttonClass(action.variant)"
                >
                    {{ action.label }}
                </Link>
            </div>
        </template>

        <div class="dashboard-status-strip" role="list">
            <article
                v-for="item in status_strip"
                :key="item.label"
                class="dashboard-status-strip__item"
                :class="`is-${item.tone}`"
                role="listitem"
            >
                <span>{{ item.label }}</span>
                <strong>{{ item.value }}</strong>
                <small>{{ item.meta }}</small>
            </article>
        </div>
    </AppPageHeader>

    <section class="dashboard-workspace">
        <div class="dashboard-main-column">
            <section class="page-card dashboard-create-card">
                <div class="dashboard-create-card__header">
                    <div>
                        <p class="section-kicker">Быстрые действия</p>
                        <h2 class="section-title">Создать</h2>
                    </div>
                    <p>Основные точки входа для типовых операций по панели.</p>
                </div>

                <div class="dashboard-chip-list">
                    <Link
                        v-for="action in create_actions"
                        :key="action.href"
                        :href="action.href"
                        class="dashboard-chip"
                    >
                        {{ action.label }}
                    </Link>
                </div>
            </section>

            <section class="dashboard-section-list">
                <article
                    v-for="section in sections"
                    :key="section.label"
                    class="page-card dashboard-section-block"
                >
                    <div class="dashboard-section-block__intro">
                        <div class="dashboard-section-block__title">
                            <span class="dashboard-section-block__icon">
                                <AppIcon :name="section.icon" />
                            </span>
                            <div>
                                <h2 class="section-title">{{ section.label }}</h2>
                                <p>{{ section.description }}</p>
                            </div>
                        </div>

                        <div class="dashboard-section-block__highlights">
                            <span
                                v-for="highlight in section.highlights"
                                :key="highlight"
                                class="dashboard-inline-stat"
                            >
                                {{ highlight }}
                            </span>
                        </div>
                    </div>

                    <div class="dashboard-link-list">
                        <Link
                            v-for="link in section.links"
                            :key="link.href"
                            :href="link.href"
                            class="dashboard-link-row"
                        >
                            <span>{{ link.label }}</span>
                            <span aria-hidden="true">Открыть</span>
                        </Link>
                    </div>
                </article>
            </section>
        </div>

        <aside class="dashboard-side-column">
            <section class="page-card dashboard-attention-card">
                <div class="dashboard-attention-card__header">
                    <div>
                        <p class="section-kicker">Контроль</p>
                        <h2 class="section-title">Требует внимания</h2>
                    </div>
                    <p>Очередь важных сигналов по операциям, биллингу и интеграциям.</p>
                </div>

                <div v-if="attention_items.length" class="attention-list">
                    <Link
                        v-for="item in attention_items"
                        :key="`${item.type}-${item.description}`"
                        :href="item.href"
                        class="attention-list__item"
                    >
                        <div class="attention-list__meta">
                            <span class="attention-list__type">{{ item.type }}</span>
                            <span class="attention-list__status">{{ item.status }}</span>
                        </div>
                        <strong>{{ item.description }}</strong>
                        <div class="attention-list__footer">
                            <span>{{ item.time }}</span>
                            <span>Открыть</span>
                        </div>
                    </Link>
                </div>

                <div v-else class="empty-state">
                    <p>Критичных сигналов сейчас нет.</p>
                </div>
            </section>
        </aside>
    </section>
</template>
