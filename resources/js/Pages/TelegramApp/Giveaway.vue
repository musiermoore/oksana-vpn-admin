<script setup>
import { computed, onMounted, ref } from 'vue';
import AppIcon from '../../Shared/AppIcon.vue';
import TelegramMiniAppFrame from '../../Shared/TelegramMiniAppFrame.vue';
import {
    ensureTelegramAppSession,
    normalizeTelegramAppError,
    telegramAppHeaders,
} from '../../lib/telegramMiniApp';

const props = defineProps({
    routes: Object,
    auth_url: String,
    profile_url: String,
    giveaway_url: String,
    giveaway_participate_url: String,
});

const state = ref('loading');
const error = ref('');
const user = ref(null);
const giveaway = ref(null);
const participant = ref(null);
const actionStatus = ref('');

const hasGiveaway = computed(() => Boolean(giveaway.value));
const isParticipant = computed(() => Boolean(participant.value?.is_participant));
const winners = computed(() => giveaway.value?.winners ?? []);

const loadGiveaway = async () => {
    const response = await window.axios.get(props.giveaway_url, {
        headers: telegramAppHeaders(),
    });

    giveaway.value = response.data?.giveaway ?? null;
    participant.value = response.data?.participant ?? null;
};

const participate = async () => {
    actionStatus.value = '';

    const response = await window.axios.post(
        props.giveaway_participate_url,
        {},
        { headers: telegramAppHeaders() },
    );

    participant.value = response.data?.participant ?? null;
    actionStatus.value = 'Вы участвуете в розыгрыше.';
};

const retry = () => window.location.reload();

const statusTitle = computed(() => {
    if (!giveaway.value) {
        return 'Сейчас нет активного розыгрыша';
    }

    if (giveaway.value.status === 'scheduled') {
        return 'Розыгрыш скоро начнётся';
    }

    if (giveaway.value.status === 'drawing') {
        return 'Розыгрыш завершён, определяем победителей';
    }

    if (giveaway.value.status === 'finished') {
        return 'Розыгрыш завершён';
    }

    return giveaway.value.title;
});

const statusDescription = computed(() => {
    if (!giveaway.value) {
        return 'Новый конкурс появится здесь, как только администратор его запланирует.';
    }

    if (giveaway.value.status === 'scheduled') {
        return 'Подготовка уже завершена. Как только старт наступит, кнопка участия появится автоматически.';
    }

    if (giveaway.value.status === 'drawing') {
        return 'Мы уже зафиксировали итоговый вес участников и сейчас сохраняем победителей.';
    }

    if (giveaway.value.status === 'finished') {
        return 'Итоговые результаты уже зафиксированы и больше не меняются.';
    }

    return 'Нажмите «Участвовать» и приглашайте друзей по своей реферальной ссылке, чтобы увеличить шанс на победу.';
});

onMounted(async () => {
    try {
        user.value = await ensureTelegramAppSession({
            authUrl: props.auth_url,
            profileUrl: props.profile_url,
        });
        await loadGiveaway();
        state.value = 'ready';
    } catch (requestError) {
        state.value = 'error';
        error.value = normalizeTelegramAppError(requestError, 'Не удалось открыть страницу розыгрыша.');
    }
});
</script>

<template>
    <TelegramMiniAppFrame
        title="Розыгрыш"
        description="Явное участие, реферальный вес и призы на подписку."
        :routes="routes"
        :user="user"
    >
        <section v-if="state === 'loading'" class="tg-section">
            <div class="tg-skeleton tg-skeleton--hero"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
            <div class="tg-skeleton tg-skeleton--row"></div>
        </section>

        <section v-else-if="state === 'error'" class="tg-state-card tg-state-card--danger">
            <div class="tg-state-card__icon">
                <AppIcon name="circleExclamation" />
            </div>
            <h2>Не удалось открыть розыгрыш</h2>
            <p>{{ error }}</p>
            <button class="tg-button" type="button" @click="retry">Повторить</button>
        </section>

        <template v-else>
            <section class="tg-page-header">
                <div class="tg-page-header__copy">
                    <div class="tg-tag tg-tag--primary">
                        <AppIcon name="gift" />
                        <span>{{ giveaway?.status_label ?? 'Нет розыгрыша' }}</span>
                    </div>
                    <h2>{{ statusTitle }}</h2>
                    <p>{{ statusDescription }}</p>
                </div>

                <div v-if="giveaway" class="tg-status-card__meta">
                    <span>Старт: {{ new Date(giveaway.starts_at).toLocaleString('ru-RU') }}</span>
                    <span>Финиш: {{ new Date(giveaway.ends_at).toLocaleString('ru-RU') }}</span>
                </div>
            </section>

            <section v-if="hasGiveaway" class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Призы</div>
                        <div class="tg-section__subtitle">Количество и длительность полностью задаются в админке.</div>
                    </div>
                </div>

                <div class="tg-stack">
                    <article v-for="prize in giveaway.prizes" :key="`${prize.duration_months}-${prize.quantity}`" class="tg-list-card">
                        <div class="tg-list-card__icon tg-list-card__icon--warning">
                            <AppIcon name="gift" />
                        </div>
                        <div class="tg-list-card__body">
                            <div class="tg-list-card__title">{{ prize.title }}</div>
                            <div class="tg-list-card__description">Количество: {{ prize.quantity }}</div>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="giveaway?.status === 'active'" class="tg-section">
                <div v-if="!isParticipant" class="tg-surface-card tg-stack">
                    <div class="tg-note">
                        <strong>Как участвовать</strong>
                        <p class="tg-page-note">1. Нажмите «Участвовать». 2. Приглашайте друзей. 3. +1 голос начисляется за пользователя, подключённого по вашей реферальной ссылке в период розыгрыша, если на момент окончания розыгрыша у него действует подписка.</p>
                    </div>
                    <button class="tg-button" type="button" @click="participate">
                        <AppIcon name="gift" />
                        <span>Участвовать</span>
                    </button>
                </div>

                <div v-else class="tg-surface-card tg-stack">
                    <div class="tg-note">
                        <strong>Вы участвуете</strong>
                        <p class="tg-page-note">Ваш вес сейчас считает backend и меняется только за счёт подходящих рефералов текущего розыгрыша.</p>
                    </div>

                    <div class="tg-stat-grid">
                        <article class="tg-mini-stat">
                            <strong>{{ participant.total_weight }}</strong>
                            <span>Всего голосов</span>
                        </article>
                        <article class="tg-mini-stat">
                            <strong>{{ participant.base_votes }}</strong>
                            <span>Базовый голос</span>
                        </article>
                        <article class="tg-mini-stat">
                            <strong>{{ participant.eligible_referrals }}</strong>
                            <span>Подходящих рефералов</span>
                        </article>
                    </div>

                    <div class="tg-inline-actions">
                        <a class="tg-button tg-button--secondary" :href="routes.referrals">
                            <AppIcon name="copy" />
                            <span>Скопировать ссылку</span>
                        </a>
                        <a class="tg-button tg-button--soft" :href="routes.referrals">
                            <AppIcon name="send" />
                            <span>Поделиться</span>
                        </a>
                    </div>

                    <p v-if="actionStatus" class="tg-success-text">{{ actionStatus }}</p>
                </div>
            </section>

            <section v-if="giveaway?.status === 'finished'" class="tg-section">
                <div class="tg-section__head">
                    <div>
                        <div class="tg-section__title">Победители</div>
                        <div class="tg-section__subtitle">Результаты уже зафиксированы и не пересчитываются по текущему состоянию базы.</div>
                    </div>
                </div>

                <div v-if="winners.length" class="tg-stack">
                    <article v-for="winner in winners" :key="`${winner.telegram}-${winner.duration_months}`" class="tg-list-card">
                        <div class="tg-list-card__icon tg-list-card__icon--success">
                            <AppIcon name="circleCheck" />
                        </div>
                        <div class="tg-list-card__body">
                            <div class="tg-list-card__title">{{ winner.telegram || winner.name || 'Победитель' }}</div>
                            <div class="tg-list-card__description">{{ winner.duration_months }} мес. · вес {{ winner.weight_at_draw }}</div>
                        </div>
                    </article>
                </div>

                <div v-else class="tg-note">
                    <strong>Победителей нет</strong>
                    <p class="tg-page-note">Так бывает, если никто не успел подтвердить участие до завершения кампании.</p>
                </div>
            </section>
        </template>
    </TelegramMiniAppFrame>
</template>

<style scoped>
.tg-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.tg-mini-stat {
    display: grid;
    gap: 6px;
    padding: 14px;
    border-radius: 18px;
    background: rgba(248, 250, 252, 0.9);
    border: 1px solid rgba(148, 163, 184, 0.18);
    text-align: center;
}

@media (max-width: 720px) {
    .tg-stat-grid {
        grid-template-columns: 1fr;
    }
}
</style>
