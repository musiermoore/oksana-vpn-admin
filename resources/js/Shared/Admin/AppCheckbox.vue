<script setup>
import { computed } from 'vue';

const model = defineModel({
    type: Boolean,
    default: false,
});

const props = defineProps({
    enabledLabel: { type: String, default: 'Включено' },
    disabledLabel: { type: String, default: 'Выключено' },
});

const stateLabel = computed(() => model.value ? props.enabledLabel : props.disabledLabel);
</script>

<template>
    <span class="app-toggle">
        <ToggleSwitch v-model="model" />
        <span class="app-toggle__state">{{ stateLabel }}</span>
    </span>
</template>
