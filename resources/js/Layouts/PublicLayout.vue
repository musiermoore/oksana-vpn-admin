<script setup>
import FlashMessages from '../Shared/FlashMessages.vue';
</script>

<template>
    <div class="shell">
        <main class="shell__content stack">
            <FlashMessages />
            <slot />
        </main>
    </div>
</template>
