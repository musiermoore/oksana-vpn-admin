<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const validationErrors = computed(() => Object.entries(page.props.errors ?? {})
    .flatMap(([field, messages]) => (Array.isArray(messages) ? messages : [messages])
        .filter(Boolean)
        .map((message) => ({ field, message })))
);
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
    <div v-if="messages.length || validationErrors.length" class="stack">
        <Message
            v-for="message in messages"
            :key="`${message.type}-${message.text}`"
            class="flash"
            :severity="message.type === 'success' ? 'success' : 'error'"
            variant="outlined"
        >
            {{ message.text }}
        </Message>

        <Message v-if="validationErrors.length" class="flash" severity="error" variant="outlined">
            <div class="validation-summary">
                <strong>Проверьте данные формы:</strong>
                <ul>
                    <li v-for="error in validationErrors" :key="`${error.field}-${error.message}`">
                        {{ error.message }}
                    </li>
                </ul>
            </div>
        </Message>
    </div>
</template>
