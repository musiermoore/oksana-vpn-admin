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

const buttonSeverity = (variant) => {
    if (variant === 'secondary') {
        return 'secondary';
    }

    return 'contrast';
};

const buttonVariant = (variant) => {
    if (variant === 'ghost') {
        return 'outlined';
    }

    return undefined;
};

const itemSeverity = (tone) => {
    if (tone === 'danger') {
        return 'danger';
    }

    if (tone === 'warning') {
        return 'warn';
    }

    if (tone === 'success') {
        return 'success';
    }

    return 'secondary';
};
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
                <Button
                    v-for="action in quick_actions"
                    :key="action.href"
                    as-child
                    :severity="buttonSeverity(action.variant)"
                    :variant="buttonVariant(action.variant)"
                >
                    <template #default="slotProps">
                        <Link :href="action.href" :class="slotProps.class" v-bind="slotProps.a11yAttrs">
                            {{ action.label }}
                        </Link>
                    </template>
                </Button>
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
                <Tag :value="item.label" :severity="itemSeverity(item.tone)" rounded />
                <strong>{{ item.value }}</strong>
                <small>{{ item.meta }}</small>
            </article>
        </div>
    </AppPageHeader>

    <section class="dashboard-workspace">
        <div class="dashboard-main-column">
            <Panel class="dashboard-panel dashboard-create-card">
                <template #header>
                    <div class="dashboard-create-card__header">
                        <div>
                            <p class="section-kicker">Быстрые действия</p>
                            <h2 class="section-title">Создать</h2>
                        </div>
                        <p>Основные точки входа для типовых операций по панели.</p>
                    </div>
                </template>

                <div class="dashboard-chip-list">
                    <Button
                        v-for="action in create_actions"
                        :key="action.href"
                        as-child
                        size="small"
                        severity="secondary"
                        variant="outlined"
                    >
                        <template #default="slotProps">
                            <Link :href="action.href" :class="[slotProps.class, 'dashboard-chip']" v-bind="slotProps.a11yAttrs">
                                {{ action.label }}
                            </Link>
                        </template>
                    </Button>
                </div>
            </Panel>

            <section class="dashboard-section-list">
                <Panel
                    v-for="section in sections"
                    :key="section.label"
                    class="dashboard-panel dashboard-section-block"
                >
                    <template #header>
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
                                <Chip
                                    v-for="highlight in section.highlights"
                                    :key="highlight"
                                    :label="highlight"
                                    class="dashboard-inline-stat"
                                />
                            </div>
                        </div>
                    </template>

                    <div class="dashboard-link-list">
                        <Button
                            v-for="link in section.links"
                            :key="link.href"
                            as-child
                            severity="secondary"
                            variant="outlined"
                        >
                            <template #default="slotProps">
                                <Link
                                    :href="link.href"
                                    :class="[slotProps.class, 'dashboard-link-row']"
                                    v-bind="slotProps.a11yAttrs"
                                >
                                    <span>{{ link.label }}</span>
                                    <span class="dashboard-link-row__action" aria-hidden="true">
                                        <span>Открыть</span>
                                        <AppIcon name="arrowRight" />
                                    </span>
                                </Link>
                            </template>
                        </Button>
                    </div>
                </Panel>
            </section>
        </div>

        <aside class="dashboard-side-column">
            <Panel class="dashboard-panel dashboard-attention-card">
                <template #header>
                    <div class="dashboard-attention-card__header">
                        <div>
                            <p class="section-kicker">Контроль</p>
                            <h2 class="section-title">Требует внимания</h2>
                        </div>
                        <p>Очередь важных сигналов по операциям, биллингу и интеграциям.</p>
                    </div>
                </template>

                <div v-if="attention_items.length" class="attention-list">
                    <Button
                        v-for="item in attention_items"
                        :key="`${item.type}-${item.description}`"
                        as-child
                        severity="secondary"
                        variant="outlined"
                    >
                        <template #default="slotProps">
                            <Link
                                :href="item.href"
                                :class="[slotProps.class, 'attention-list__item']"
                                v-bind="slotProps.a11yAttrs"
                            >
                                <div class="attention-list__meta">
                                    <Tag :value="item.type" severity="secondary" rounded />
                                    <Tag :value="item.status" :severity="item.status === 'Ошибка' ? 'danger' : 'warn'" rounded />
                                </div>
                                <strong>{{ item.description }}</strong>
                                <div class="attention-list__footer">
                                    <span>{{ item.time }}</span>
                                    <span class="dashboard-link-row__action">
                                        <span>Открыть</span>
                                        <AppIcon name="arrowRight" />
                                    </span>
                                </div>
                            </Link>
                        </template>
                    </Button>
                </div>

                <div v-else class="empty-state">
                    <Message severity="success" variant="outlined">
                        Критичных сигналов сейчас нет.
                    </Message>
                </div>
            </Panel>
        </aside>
    </section>
</template>
