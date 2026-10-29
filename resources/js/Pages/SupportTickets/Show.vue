<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ticket: Object,
    supportUser: Object,
});

const form = useForm({
    message: '',
});
const copiedLink = ref('');
const activeGalleryIndex = ref(null);
let copyResetTimer = null;

const galleryImages = computed(() => props.ticket.messages.flatMap((message) =>
    (message.attachments || [])
        .filter((file) => file.preview_url)
        .map((file) => ({ ...file, messageId: message.id, senderName: message.sender_name || message.sender_type })),
));

const openGallery = (image) => {
    activeGalleryIndex.value = galleryImages.value.findIndex((item) => item.preview_url === image.preview_url);
};

const closeGallery = () => {
    activeGalleryIndex.value = null;
};

const showPreviousImage = () => {
    if (activeGalleryIndex.value !== null) {
        activeGalleryIndex.value = (activeGalleryIndex.value - 1 + galleryImages.value.length) % galleryImages.value.length;
    }
};

const showNextImage = () => {
    if (activeGalleryIndex.value !== null) {
        activeGalleryIndex.value = (activeGalleryIndex.value + 1) % galleryImages.value.length;
    }
};

const handleGalleryKeydown = (event) => {
    if (activeGalleryIndex.value === null) return;
    if (event.key === 'Escape') closeGallery();
    if (event.key === 'ArrowLeft') showPreviousImage();
    if (event.key === 'ArrowRight') showNextImage();
};

onMounted(() => window.addEventListener('keydown', handleGalleryKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleGalleryKeydown);
    window.clearTimeout(copyResetTimer);
});

const submit = () => {
    form.post(`/support-tickets/${props.ticket.id}/reply`);
};

const closeTicket = () => router.post(`/support-tickets/${props.ticket.id}/close`);

const copyLink = async (url) => {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        } else {
            const input = document.createElement('textarea');
            input.value = url;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            document.body.append(input);
            input.select();
            const copied = document.execCommand('copy');
            input.remove();

            if (!copied) {
                throw new Error('Clipboard copy failed');
            }
        }

        copiedLink.value = url;
        window.clearTimeout(copyResetTimer);
        copyResetTimer = window.setTimeout(() => {
            copiedLink.value = '';
        }, 2000);
    } catch {
        copiedLink.value = `error:${url}`;
    }
};

const statusLabel = (status) => ({
    open: 'Открыт',
    answered: 'Отвечен',
    closed: 'Закрыт',
}[status] || status);

const formatDate = (value) => value ? new Date(value).toLocaleString('ru-RU') : '—';
</script>

<template>
    <Head :title="`Тикет #${ticket.id}`" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Тикет #{{ ticket.id }}</h1>
                <p>{{ ticket.subject || 'Без темы' }}</p>
            </div>

            <div class="actions">
                <AppButton variant="secondary" href="/support-tickets">Назад</AppButton>
                <AppButton v-if="ticket.status !== 'closed'" variant="secondary" @click="closeTicket">Закрыть обращение</AppButton>
                <AppButton
                    v-if="ticket.user?.chat_url"
                    :href="ticket.user.chat_url"
                    target="_blank"
                >
                    Открыть чат
                </AppButton>
            </div>
        </div>

        <div class="grid-form grid-form--two">
            <div>
                <span class="field-label">Пользователь</span>
                <p>{{ ticket.user?.name || '—' }}</p>
                <p>{{ ticket.user?.telegram || ticket.user?.telegram_id || '—' }}</p>
            </div>
            <div>
                <span class="field-label">Статус</span>
                <p>{{ statusLabel(ticket.status) }}</p>
            </div>
        </div>
    </section>

    <section v-if="supportUser" class="page-card stack">
        <h2>Информация о пользователе</h2>
        <div class="grid-form grid-form--two">
            <div>
                <span class="field-label">Подписка действует до</span>
                <p>{{ formatDate(supportUser.subscription_expires_at) }}</p>
            </div>
            <div>
                <span class="field-label">Ссылки подключения</span>
                <div v-for="link in supportUser.connect_links" :key="link.label" class="support-link-row">
                    <a :href="link.url" target="_blank">{{ link.label }}</a>
                    <AppButton type="button" variant="secondary" @click="copyLink(link.url)">
                        {{ copiedLink === link.url ? 'Скопировано' : copiedLink === `error:${link.url}` ? 'Не скопировано' : 'Копировать' }}
                    </AppButton>
                </div>
            </div>
        </div>
        <div>
            <span class="field-label">Конфигурации Amnezia</span>
            <p v-if="!supportUser.amnezia_configs.length">Нет конфигов</p>
            <div v-for="config in supportUser.amnezia_configs" :key="config.url" class="support-link-row">
                <a :href="config.url">{{ config.label }}</a>
                <AppButton type="button" variant="secondary" @click="copyLink(config.url)">
                    {{ copiedLink === config.url ? 'Скопировано' : copiedLink === `error:${config.url}` ? 'Не скопировано' : 'Копировать' }}
                </AppButton>
            </div>
        </div>
        <details class="support-user-details">
            <summary>
                <span>Последние 5 транзакций</span>
                <span class="support-user-details__count">{{ supportUser.transactions.length }}</span>
            </summary>
            <p v-if="!supportUser.transactions.length" class="support-user-details__empty">Транзакций пока нет</p>
            <div v-else class="support-compact-list">
                <article v-for="transaction in supportUser.transactions" :key="transaction.id" class="support-compact-item">
                    <div class="support-compact-item__head">
                        <strong>{{ transaction.type }} · {{ transaction.amount }}</strong>
                        <span>{{ transaction.approved ? 'Одобрена' : 'Ожидает' }}</span>
                    </div>
                    <div class="support-compact-item__meta">
                        <span>{{ formatDate(transaction.date) }}</span>
                        <span v-if="transaction.description">{{ transaction.description }}</span>
                    </div>
                </article>
            </div>
        </details>
        <details class="support-user-details">
            <summary>
                <span>Последние подключения</span>
                <span class="support-user-details__count">{{ supportUser.connected_devices.length }}</span>
            </summary>
            <p v-if="!supportUser.connected_devices.length" class="support-user-details__empty">Данных о подключениях нет</p>
            <div v-else class="support-compact-list">
                <article v-for="(device, index) in supportUser.connected_devices" :key="`${device.label}-${index}`" class="support-compact-item">
                    <div class="support-compact-item__head">
                        <strong>{{ device.label || device.device || 'Устройство' }}</strong>
                        <span>{{ formatDate(device.last_connection_at) }}</span>
                    </div>
                    <div class="support-compact-item__meta">
                        <span>{{ device.device || 'Устройство неизвестно' }} · {{ device.connection_route || 'Маршрут неизвестен' }}</span>
                        <span>{{ device.user_agent || 'Версия приложения неизвестна' }}</span>
                        <span>{{ device.ip_address || 'IP не записан' }} · {{ device.connection_count }} подключений</span>
                    </div>
                </article>
            </div>
        </details>
    </section>

    <section class="page-card stack">
        <h2>Переписка</h2>

        <div class="stack">
            <article
                v-for="message in ticket.messages"
                :key="message.id"
                class="page-card"
                :style="{ borderLeft: message.sender_type === 'admin' ? '4px solid #1f6feb' : '4px solid #2ea043' }"
            >
                <strong>{{ message.sender_name || message.sender_type }}</strong>
                <p style="white-space: pre-wrap">{{ message.message }}</p>
                <div v-if="message.attachments?.length" class="ticket-attachments">
                    <button
                        v-for="file in message.attachments.filter((attachment) => attachment.preview_url)"
                        :key="file.url"
                        class="ticket-image-thumb"
                        type="button"
                        :aria-label="`Открыть изображение ${file.name}`"
                        @click="openGallery(file)"
                    >
                        <img :src="file.preview_url" :alt="file.name" loading="lazy">
                    </button>
                    <a
                        v-for="file in message.attachments.filter((attachment) => !attachment.preview_url)"
                        :key="file.url"
                        :href="file.url"
                        target="_blank"
                        rel="noopener"
                    >{{ file.name }}</a>
                </div>
                <small>{{ message.created_at }}</small>
            </article>
        </div>
    </section>

    <section class="page-card stack">
        <h2>Ответить</h2>

        <form class="stack" @submit.prevent="submit">
            <label class="field">
                <span class="field-label">Сообщение</span>
                <AppTextarea v-model="form.message" rows="6" />
            </label>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Отправить ответ</AppButton>
            </div>
        </form>
    </section>

    <div v-if="activeGalleryIndex !== null && galleryImages[activeGalleryIndex]" class="ticket-gallery" @click.self="closeGallery">
        <button class="ticket-gallery__close" type="button" aria-label="Закрыть галерею" @click="closeGallery">×</button>
        <button
            v-if="galleryImages.length > 1"
            class="ticket-gallery__nav ticket-gallery__nav--previous"
            type="button"
            aria-label="Предыдущее изображение"
            @click="showPreviousImage"
        >‹</button>
        <figure class="ticket-gallery__figure">
            <img :src="galleryImages[activeGalleryIndex].preview_url" :alt="galleryImages[activeGalleryIndex].name">
            <figcaption>
                {{ galleryImages[activeGalleryIndex].name }}
                <span>{{ galleryImages[activeGalleryIndex].senderName }} · {{ activeGalleryIndex + 1 }} / {{ galleryImages.length }}</span>
            </figcaption>
        </figure>
        <button
            v-if="galleryImages.length > 1"
            class="ticket-gallery__nav ticket-gallery__nav--next"
            type="button"
            aria-label="Следующее изображение"
            @click="showNextImage"
        >›</button>
    </div>
</template>

<style scoped>
.ticket-attachments {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.ticket-image-thumb {
    width: 76px;
    height: 76px;
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--border-color, #d0d7de);
    border-radius: 8px;
    background: transparent;
    cursor: zoom-in;
}

.ticket-image-thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.ticket-gallery {
    position: fixed;
    z-index: 1000;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 5rem;
    background: rgb(0 0 0 / 88%);
}

.ticket-gallery__figure {
    display: flex;
    max-width: min(100%, 1200px);
    max-height: 100%;
    flex-direction: column;
    align-items: center;
    margin: 0;
}

.ticket-gallery__figure img {
    display: block;
    max-width: 100%;
    max-height: calc(100vh - 9rem);
    object-fit: contain;
}

.ticket-gallery__figure figcaption {
    display: flex;
    width: 100%;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.75rem;
    color: white;
    overflow-wrap: anywhere;
}

.ticket-gallery__figure figcaption span {
    flex-shrink: 0;
    color: rgb(255 255 255 / 70%);
}

.ticket-gallery__close,
.ticket-gallery__nav {
    position: absolute;
    display: grid;
    width: 44px;
    height: 44px;
    place-items: center;
    border: 0;
    border-radius: 50%;
    background: rgb(255 255 255 / 16%);
    color: white;
    font-size: 2rem;
    cursor: pointer;
}

.ticket-gallery__close {
    top: 1rem;
    right: 1rem;
}

.ticket-gallery__nav--previous {
    left: 1rem;
}

.ticket-gallery__nav--next {
    right: 1rem;
}

@media (max-width: 640px) {
    .ticket-gallery {
        padding: 3.5rem 1rem;
    }

    .ticket-gallery__nav--previous {
        left: 0.5rem;
    }

    .ticket-gallery__nav--next {
        right: 0.5rem;
    }
}
</style>
