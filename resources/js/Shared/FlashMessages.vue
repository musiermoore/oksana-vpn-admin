<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const messages = computed(() => [
    page.props.flash.success
        ? { type: 'success', text: page.props.flash.success }
        : null,
    page.props.flash.error
        ? { type: 'error', text: page.props.flash.error }
        : null,
].filter(Boolean));
</script>

<template>
    <div v-if="messages.length" class="stack">
        <div
            v-for="message in messages"
            :key="`${message.type}-${message.text}`"
            class="flash"
            :class="message.type === 'success' ? 'flash--success' : 'flash--error'"
        >
            {{ message.text }}
        </div>
    </div>
</template>
