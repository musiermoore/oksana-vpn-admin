<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    users: Array,
});

const textareaRef = ref(null);
const search = ref('');
const imagePreviewUrl = ref(null);

const form = useForm({
    send_to_all: false,
    user_ids: [],
    message_html: '',
    image: null,
});

const filteredUsers = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.users;
    }

    return props.users.filter((user) => {
        const haystack = [user.id, user.telegram, user.name]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const selectedCount = computed(() => form.user_ids.length);

const toggleUser = (userId) => {
    if (form.user_ids.includes(userId)) {
        form.user_ids = form.user_ids.filter((id) => id !== userId);
        return;
    }

    form.user_ids = [...form.user_ids, userId];
};

const selectVisible = () => {
    form.user_ids = Array.from(new Set([
        ...form.user_ids,
        ...filteredUsers.value.map((user) => user.id),
    ]));
};

const clearSelection = () => {
    form.user_ids = [];
};

const setSelection = (nextValue, start, end) => {
    form.message_html = nextValue;

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

const updateImage = (event) => {
    const [file] = event.target.files ?? [];
    form.image = file ?? null;

    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
        imagePreviewUrl.value = null;
    }

    if (file) {
        imagePreviewUrl.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    form.post('/notifications', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            search.value = '';

            if (imagePreviewUrl.value) {
                URL.revokeObjectURL(imagePreviewUrl.value);
                imagePreviewUrl.value = null;
            }
        },
    });
};

onBeforeUnmount(() => {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value);
    }
});
</script>

<template>
    <Head title="Рассылка" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Рассылка в Telegram</h1>
                <p>Можно отправить сообщение всем пользователям или только выбранным ID. По умолчанию никто не выбран.</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="submit">
            <section class="notification-section stack">
                <div class="page-header">
                    <div>
                        <h2 class="section-title">Получатели</h2>
                        <p>Выберите всех или отметьте конкретных пользователей вручную.</p>
                    </div>

                    <div class="actions">
                        <span class="chip">Выбрано: {{ selectedCount }}</span>
                    </div>
                </div>

                <div class="field">
                    <span>Режим отправки</span>
                    <label class="notification-toggle">
                        <input v-model="form.send_to_all" type="checkbox">
                        <span>Отправить всем пользователям</span>
                    </label>
                </div>

                <template v-if="!form.send_to_all">
                    <div class="grid grid--two">
                        <label class="field">
                            <span>Поиск</span>
                            <input v-model="search" type="search" placeholder="ID, Telegram или имя">
                        </label>

                        <div class="field">
                            <span>Быстрые действия</span>
                            <div class="actions">
                                <button class="button button--secondary" type="button" @click="selectVisible">Выбрать видимых</button>
                                <button class="button button--ghost" type="button" @click="clearSelection">Очистить</button>
                            </div>
                        </div>
                    </div>

                    <small v-if="form.errors.user_ids" class="field-error">{{ form.errors.user_ids }}</small>

                    <div class="notification-recipient-list">
                        <label
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="notification-user"
                            :class="{ 'is-selected': form.user_ids.includes(user.id) }"
                        >
                            <input
                                :checked="form.user_ids.includes(user.id)"
                                type="checkbox"
                                @change="toggleUser(user.id)"
                            >

                            <div class="notification-user__copy">
                                <strong>#{{ user.id }} · {{ user.telegram }}</strong>
                                <span>{{ user.name }}</span>
                            </div>

                            <div class="actions">
                                <span class="chip" :class="{ 'is-active': user.has_telegram_chat }">
                                    {{ user.has_telegram_chat ? 'Telegram OK' : 'Нет chat id' }}
                                </span>
                                <span class="chip" :class="{ 'is-active': user.is_active }">
                                    {{ user.is_active ? 'Активен' : 'Неактивен' }}
                                </span>
                            </div>
                        </label>

                        <div v-if="!filteredUsers.length" class="empty-state">
                            Пользователи по этому запросу не найдены.
                        </div>
                    </div>
                </template>
            </section>

            <section class="notification-section stack">
                <div class="page-header">
                    <div>
                        <h2 class="section-title">Сообщение</h2>
                        <p>Кнопки вставляют Telegram HTML-теги: жирный, курсив, подчёркивание, зачёркивание, код и ссылка.</p>
                    </div>
                </div>

                <div class="actions">
                    <button class="button button--secondary" type="button" @click="wrapSelection('<b>', '</b>')">Жирный</button>
                    <button class="button button--secondary" type="button" @click="wrapSelection('<i>', '</i>')">Курсив</button>
                    <button class="button button--secondary" type="button" @click="wrapSelection('<u>', '</u>')">Подчерк.</button>
                    <button class="button button--secondary" type="button" @click="wrapSelection('<s>', '</s>')">Зачерк.</button>
                    <button class="button button--secondary" type="button" @click="wrapSelection('<code>', '</code>')">Код</button>
                    <button class="button button--secondary" type="button" @click="insertLink">Ссылка</button>
                </div>

                <label class="field">
                    <span>Текст</span>
                    <textarea
                        ref="textareaRef"
                        v-model="form.message_html"
                        class="notification-editor"
                        placeholder="Введите текст сообщения"
                    />
                    <small v-if="form.errors.message_html" class="field-error">{{ form.errors.message_html }}</small>
                </label>

                <label class="field">
                    <span>Изображение</span>
                    <input accept="image/*" type="file" @change="updateImage">
                    <small class="muted">Необязательно. Если текста много, изображение и текст будут отправлены отдельными сообщениями.</small>
                    <small v-if="form.errors.image" class="field-error">{{ form.errors.image }}</small>
                </label>

                <img
                    v-if="imagePreviewUrl"
                    :src="imagePreviewUrl"
                    alt="Предпросмотр изображения"
                    class="notification-image-preview"
                >
            </section>

            <div class="actions">
                <button class="button" type="submit" :disabled="form.processing">Отправить</button>
            </div>
        </form>
    </section>
</template>
