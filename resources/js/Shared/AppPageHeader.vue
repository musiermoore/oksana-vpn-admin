<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    eyebrow: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    stats: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const breadcrumbs = computed(() => page.props.app?.breadcrumbs ?? []);
</script>

<template>
    <section class="page-hero stack">
        <div v-if="breadcrumbs.length" class="breadcrumbs" aria-label="Breadcrumb">
            <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.label}-${index}`">
                <Link v-if="crumb.href" :href="crumb.href" class="breadcrumbs__item">
                    <AppIcon v-if="crumb.icon" :name="crumb.icon" class="breadcrumbs__icon" />
                    <span>{{ crumb.label }}</span>
                </Link>

                <span v-else class="breadcrumbs__item">
                    <AppIcon v-if="crumb.icon" :name="crumb.icon" class="breadcrumbs__icon" />
                    <span>{{ crumb.label }}</span>
                </span>

                <span v-if="index < breadcrumbs.length - 1" class="breadcrumbs__separator" aria-hidden="true">/</span>
            </template>
        </div>

        <div class="page-hero__top">
            <div class="page-hero__copy">
                <span v-if="eyebrow" class="page-hero__eyebrow">{{ eyebrow }}</span>
                <h1>{{ title }}</h1>
                <p v-if="description">{{ description }}</p>
            </div>

            <div v-if="$slots.actions" class="page-hero__actions">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="stats.length" class="page-hero__stats">
            <article v-for="stat in stats" :key="stat.label" class="page-hero__stat">
                <span>{{ stat.label }}</span>
                <strong>{{ stat.value }}</strong>
                <small v-if="stat.hint">{{ stat.hint }}</small>
            </article>
        </div>

        <div v-if="$slots.default" class="page-hero__body">
            <slot />
        </div>
    </section>
</template>
