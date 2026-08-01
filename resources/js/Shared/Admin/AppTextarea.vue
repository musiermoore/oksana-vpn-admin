<script setup>
defineProps({
    fluid: {
        type: Boolean,
        default: true,
    },
    autoResize: {
        type: Boolean,
        default: true,
    },
});

const model = defineModel({
    type: String,
    default: '',
});
</script>

<template>
    <Textarea v-model="model" :fluid="fluid" :auto-resize="autoResize" />
</template>
