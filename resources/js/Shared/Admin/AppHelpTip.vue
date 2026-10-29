<script setup>
import AppIcon from '../AppIcon.vue';

defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <span class="app-help-tip" :title="text" tabindex="0" role="img" :aria-label="text">
        <AppIcon name="circleQuestion" />
    </span>
</template>
