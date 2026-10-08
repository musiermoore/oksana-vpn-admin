<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    modelValue: { type: String, required: true },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const jsonMode = ref(false);
const jsonValue = ref(props.modelValue);
watch(() => props.modelValue, (value) => { jsonValue.value = value; });
const updateJson = (value) => { jsonValue.value = value; emit('update:modelValue', value); };

const toggleMode = () => {
    jsonMode.value = !jsonMode.value;
};
</script>

<template>
    <section class="json-section page-card stack">
        <div class="json-section__header">
            <div>
                <h2>{{ title }}</h2>
                <p v-if="description">{{ description }}</p>
            </div>
            <AppButton variant="secondary" type="button" @click="toggleMode">
                {{ jsonMode ? 'Открыть builder' : 'Edit in JSON' }}
            </AppButton>
        </div>

        <AppTextarea v-if="jsonMode" :model-value="jsonValue" rows="12" spellcheck="false" @update:model-value="updateJson" />
        <div v-else class="json-section__builder">
            <slot />
        </div>

        <small v-if="error" class="field-error">{{ error }}</small>
    </section>
</template>

<style scoped>
.json-section__header {
    align-items: flex-start;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
}

.json-section__header h2,
.json-section__header p {
    margin: 0;
}

.json-section__header p {
    margin-top: 0.35rem;
}
</style>
