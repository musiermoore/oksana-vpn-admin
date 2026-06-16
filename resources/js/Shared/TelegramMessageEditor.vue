<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: 'Текст',
    },
    placeholder: {
        type: String,
        default: 'Введите текст сообщения',
    },
    error: {
        type: String,
        default: '',
    },
    help: {
        type: String,
        default: '',
    },
    previewTitle: {
        type: String,
        default: 'Предпросмотр',
    },
});

const emit = defineEmits(['update:modelValue']);

const textareaRef = ref(null);
const previewHtml = computed(() => props.modelValue || '');

const setSelection = (nextValue, start, end) => {
    emit('update:modelValue', nextValue);

    requestAnimationFrame(() => {
        if (!textareaRef.value) {
            return;
        }

        textareaRef.value.focus();
        textareaRef.value.setSelectionRange(start, end);
    });
};

const wrapSelection = (openTag, closeTag = openTag, fallback = 'текст') => {
    const textarea = textareaRef.value;

    if (!textarea) {
        return;
    }

    const { selectionStart, selectionEnd, value } = textarea;
    const selectedText = value.slice(selectionStart, selectionEnd) || fallback;
    const nextValue = `${value.slice(0, selectionStart)}${openTag}${selectedText}${closeTag}${value.slice(selectionEnd)}`;
    const cursorStart = selectionStart + openTag.length;
    const cursorEnd = cursorStart + selectedText.length;

    setSelection(nextValue, cursorStart, cursorEnd);
};

const insertLink = () => {
    const href = window.prompt('Введите ссылку');

    if (!href) {
        return;
    }

    wrapSelection(`<a href="${href}">`, '</a>', href);
};

const updateValue = (event) => {
    emit('update:modelValue', event.target.value);
};
</script>

<template>
    <div class="stack">
        <div class="actions">
            <button class="button button--secondary" type="button" @click="wrapSelection('<b>', '</b>')">Жирный</button>
            <button class="button button--secondary" type="button" @click="wrapSelection('<i>', '</i>')">Курсив</button>
            <button class="button button--secondary" type="button" @click="wrapSelection('<u>', '</u>')">Подчерк.</button>
            <button class="button button--secondary" type="button" @click="wrapSelection('<s>', '</s>')">Зачерк.</button>
            <button class="button button--secondary" type="button" @click="wrapSelection('<code>', '</code>')">Код</button>
            <button class="button button--secondary" type="button" @click="insertLink">Ссылка</button>
        </div>

        <label class="field">
            <span>{{ label }}</span>
            <textarea
                ref="textareaRef"
                :value="modelValue"
                class="notification-editor"
                :placeholder="placeholder"
                @input="updateValue"
            />
            <small v-if="help" class="muted">{{ help }}</small>
            <small v-if="error" class="field-error">{{ error }}</small>
        </label>

        <section class="message-preview stack">
            <div class="page-header">
                <div>
                    <h3 class="message-preview__title">{{ previewTitle }}</h3>
                    <p>Так сообщение будет выглядеть в HTML-представлении перед сохранением или отправкой.</p>
                </div>
            </div>

            <div v-if="previewHtml.trim()" class="message-preview__body" v-html="previewHtml" />
            <div v-else class="empty-state">
                Предпросмотр появится, когда вы введёте текст.
            </div>
        </section>
    </div>
</template>
