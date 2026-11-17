<script setup>
import { computed } from 'vue';

const model = defineModel({
    type: Boolean,
    default: false,
});

const props = defineProps({
    enabledLabel: { type: String, default: '' },
    disabledLabel: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const stateLabel = computed(() => model.value ? props.enabledLabel : props.disabledLabel);
</script>

<template>
    <span class="app-toggle">
        <ToggleSwitch v-model="model" :disabled="disabled" />
        <span v-if="stateLabel" class="app-toggle__state">{{ stateLabel }}</span>
    </span>
</template>
