<script setup>
const model = defineModel({
    type: Boolean,
    default: false,
});
</script>

<template>
    <Checkbox v-model="model" binary />
</template>
