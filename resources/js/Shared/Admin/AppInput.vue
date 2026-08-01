<script setup>
defineProps({
    fluid: {
        type: Boolean,
        default: true,
    },
});

const model = defineModel({
    type: [String, Number],
    default: '',
});
</script>

<template>
    <InputText v-model="model" :fluid="fluid" />
</template>
