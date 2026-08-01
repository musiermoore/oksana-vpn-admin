<script setup>
defineProps({
    options: {
        type: Array,
        default: () => [],
    },
    optionLabel: {
        type: String,
        default: 'label',
    },
    optionValue: {
        type: String,
        default: 'value',
    },
    optionDisabled: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    fluid: {
        type: Boolean,
        default: true,
    },
    filter: {
        type: Boolean,
        default: false,
    },
});

const model = defineModel({
    type: [String, Number, Boolean, null],
    default: '',
});
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        :option-label="optionLabel"
        :option-value="optionValue"
        :option-disabled="optionDisabled || undefined"
        :placeholder="placeholder || undefined"
        :fluid="fluid"
        :filter="filter"
    />
</template>
