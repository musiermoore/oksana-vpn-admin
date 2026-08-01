<script setup>
import { computed, useAttrs } from 'vue';
import { Link } from '@inertiajs/vue3';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps({
    href: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'primary',
    },
    type: {
        type: String,
        default: 'button',
    },
    target: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: '',
    },
    rounded: {
        type: Boolean,
        default: false,
    },
    fluid: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const attrs = useAttrs();

const isExternal = computed(() => props.target === '_blank' || props.href.startsWith('http'));

const buttonProps = computed(() => {
    if (props.variant === 'secondary') {
        return { severity: 'secondary', variant: 'outlined' };
    }

    if (props.variant === 'danger') {
        return { severity: 'danger' };
    }

    if (props.variant === 'success') {
        return { severity: 'success' };
    }

    if (props.variant === 'ghost') {
        return { severity: 'secondary', variant: 'text' };
    }

    return {};
});
</script>

<template>
    <Button
        v-if="href"
        as-child
        :size="size || undefined"
        :rounded="rounded"
        :fluid="fluid"
        :disabled="disabled"
        v-bind="buttonProps"
    >
        <template #default="slotProps">
            <a
                v-if="isExternal"
                :href="href"
                :target="target || undefined"
                :class="slotProps.class"
                v-bind="{ ...slotProps.a11yAttrs, ...attrs }"
            >
                <slot />
            </a>

            <Link
                v-else
                :href="href"
                :class="slotProps.class"
                v-bind="{ ...slotProps.a11yAttrs, ...attrs }"
            >
                <slot />
            </Link>
        </template>
    </Button>

    <Button
        v-else
        :type="type"
        :size="size || undefined"
        :rounded="rounded"
        :fluid="fluid"
        :disabled="disabled"
        v-bind="{ ...buttonProps, ...attrs }"
    >
        <slot />
    </Button>
</template>
