<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppIcon from '../../Shared/AppIcon.vue';
import AppPageHeader from '../../Shared/AppPageHeader.vue';

defineOptions({ layout: AppLayout });

const page = usePage();

const sections = computed(() => page.props.app.navigation ?? []);
</script>

<template>
    <Head title="Панель управления" />

    <AppPageHeader
        title="Панель управления"
        description="Быстрый вход в ключевые зоны админки: сеть, пользователи, биллинг, операции и диагностика."
        :stats="[
            { label: 'Разделов', value: sections.length },
            { label: 'Точек входа', value: sections.reduce((total, section) => total + section.items.length, 0) },
            { label: 'Режим', value: 'Навигационный хаб' },
        ]"
    />

    <section class="dashboard-sections">
        <article
            v-for="section in sections"
            :key="section.section"
            class="dashboard-section-card stack"
        >
            <div class="page-header">
                <div>
                    <h2 class="section-title dashboard-section-title">
                        <AppIcon v-if="section.icon" :name="section.icon" class="dashboard-section-title__icon" />
                        <span>{{ section.section }}</span>
                    </h2>
                    <p>Основные страницы и сценарии внутри раздела.</p>
                </div>
            </div>

            <div class="dashboard-links">
                <Link
                    v-for="item in section.items"
                    :key="item.href"
                    :href="item.href"
                    class="dashboard-link-card"
                >
                    <span class="dashboard-link-card__badge">
                        <AppIcon v-if="item.icon" :name="item.icon" class="dashboard-link-card__icon" />
                        <span v-else>{{ item.badge }}</span>
                    </span>
                    <div class="dashboard-link-card__copy">
                        <strong>{{ item.label }}</strong>
                        <span>Открыть раздел</span>
                    </div>
                    <span class="dashboard-link-card__arrow" aria-hidden="true">→</span>
                </Link>
            </div>

            <div class="dashboard-section-footer">
                <AppButton v-if="section.items[0]" variant="secondary" :href="section.items[0].href">
                    Перейти в {{ section.section }}
                </AppButton>
            </div>
        </article>
    </section>

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h2 class="section-title">Как дальше развивать</h2>
                <p>Эта главная уже работает как навигационный хаб. Следующим шагом можно превратить ключевые разделы в полноценные рабочие панели.</p>
            </div>
        </div>

        <div class="grid grid--cards">
            <article class="stat-card stack">
                <div>
                    <h3>Рабочая зона сервера</h3>
                    <p class="muted">Статус, конфиги, прокси, inbounds, порядок connect и история цен в одном экране.</p>
                </div>
            </article>

            <article class="stat-card stack">
                <div>
                    <h3>Рабочая зона пользователя</h3>
                    <p class="muted">Профиль, доступ, баланс, конфиги, подписки и обращения в поддержку без прыжков по CRUD-страницам.</p>
                </div>
            </article>

            <article class="stat-card stack">
                <div>
                    <h3>Рабочая зона биллинга</h3>
                    <p class="muted">Подписки, инвойсы, транзакции, лимиты и налоги как одна операционная зона.</p>
                </div>
            </article>
        </div>
    </section>
</template>
