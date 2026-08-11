<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    giveaway: Object,
    allowed_durations: Array,
});

const form = useForm({
    title: props.giveaway?.title ?? 'Розыгрыш Oksana VPN',
    description: props.giveaway?.description ?? '',
    starts_at: props.giveaway?.starts_at ?? '',
    ends_at: props.giveaway?.ends_at ?? '',
    auto_repeat_enabled: props.giveaway?.series?.auto_repeat_enabled ?? false,
    repeat_delay_minutes: props.giveaway?.series?.repeat_delay_minutes ?? 60,
    repeat_limit: props.giveaway?.series?.repeat_limit ?? 5,
    prizes: (props.giveaway?.prizes ?? []).map((prize) => ({
        duration_months: prize.duration_months,
        quantity: prize.quantity,
        title: prize.title,
    })),
});

if (!form.prizes.length) {
    form.prizes = [
        { duration_months: 1, quantity: 3, title: 'Подписка на 1 месяц' },
        { duration_months: 3, quantity: 2, title: 'Подписка на 3 месяца' },
        { duration_months: 6, quantity: 1, title: 'Подписка на 6 месяцев' },
    ];
}

const submit = () => props.mode === 'edit'
    ? form.put(props.submit_url)
    : form.post(props.submit_url);

const addPrize = () => {
    form.prizes.push({
        duration_months: 1,
        quantity: 1,
        title: 'Подписка на 1 месяц',
    });
};

const removePrize = (index) => {
    form.prizes.splice(index, 1);
};

const activateGiveaway = () => router.post(props.giveaway.links.activate);
const drawGiveaway = () => router.post(props.giveaway.links.draw);
const cancelGiveaway = () => router.post(props.giveaway.links.cancel);
</script>

<template>
    <Head :title="mode === 'edit' ? `Розыгрыш ${giveaway.title}` : 'Создание розыгрыша'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? giveaway.title : 'Создание розыгрыша' }}</h1>
                <p>Поддерживаются только явное участие, реферальный вес и настраиваемые призы без хардкода в коде.</p>
            </div>
            <div class="actions">
                <AppButton variant="secondary" href="/giveaways">Назад</AppButton>
                <AppButton type="button" @click="submit">Сохранить</AppButton>
                <AppButton
                    v-if="mode === 'edit' && ['draft', 'scheduled'].includes(giveaway.status)"
                    type="button"
                    variant="secondary"
                    @click="activateGiveaway"
                >
                    Активировать
                </AppButton>
                <AppButton
                    v-if="mode === 'edit' && ['active', 'drawing'].includes(giveaway.status)"
                    type="button"
                    variant="secondary"
                    @click="drawGiveaway"
                >
                    Определить победителей
                </AppButton>
                <AppButton
                    v-if="mode === 'edit' && !['finished', 'cancelled'].includes(giveaway.status)"
                    type="button"
                    variant="danger"
                    @click="cancelGiveaway"
                >
                    Отменить
                </AppButton>
            </div>
        </div>
    </section>

    <section class="page-card stack">
        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field" style="grid-column: 1 / -1;">
                <span>Название</span>
                <AppInput v-model="form.title" required />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <textarea v-model="form.description" class="textarea"></textarea>
            </label>

            <label class="field">
                <span>Дата начала</span>
                <AppInput v-model="form.starts_at" type="datetime-local" required />
            </label>

            <label class="field">
                <span>Дата окончания</span>
                <AppInput v-model="form.ends_at" type="datetime-local" required />
            </label>

            <label class="field">
                <span>
                    <input v-model="form.auto_repeat_enabled" type="checkbox">
                    Автоповтор
                </span>
            </label>

            <div></div>

            <label class="field">
                <span>Задержка перед следующим розыгрышем (мин)</span>
                <AppInput v-model="form.repeat_delay_minutes" type="number" min="0" />
            </label>

            <label class="field">
                <span>Лимит повторов</span>
                <AppInput v-model="form.repeat_limit" type="number" min="1" />
            </label>

            <div style="grid-column: 1 / -1;" class="stack">
                <div class="page-header">
                    <div>
                        <h2>Призы</h2>
                        <p>Призы становятся неизменяемыми после активации розыгрыша.</p>
                    </div>
                    <div class="actions">
                        <AppButton
                            v-if="!giveaway?.status || ['draft', 'scheduled'].includes(giveaway.status)"
                            type="button"
                            variant="secondary"
                            @click="addPrize"
                        >
                            Добавить приз
                        </AppButton>
                    </div>
                </div>

                <div v-for="(prize, index) in form.prizes" :key="index" class="prize-row">
                    <label class="field">
                        <span>Месяцев</span>
                        <select v-model="prize.duration_months" class="input">
                            <option v-for="value in allowed_durations" :key="value" :value="value">{{ value }}</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Количество</span>
                        <AppInput v-model="prize.quantity" type="number" min="0" />
                    </label>

                    <label class="field">
                        <span>Заголовок</span>
                        <AppInput v-model="prize.title" />
                    </label>

                    <div class="actions prize-row__actions">
                        <AppButton type="button" variant="danger" @click="removePrize(index)">Удалить</AppButton>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <section v-if="mode === 'edit'" class="grid grid--two">
        <article class="page-card stack">
            <div class="page-header">
                <div>
                    <h2>Статистика</h2>
                </div>
            </div>

            <div class="stat-grid">
                <article class="stat-card stack">
                    <p class="muted">Участников</p>
                    <strong>{{ giveaway.stats.participants_count }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Подходящих рефералов</p>
                    <strong>{{ giveaway.stats.eligible_referrals_count }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Общий вес</p>
                    <strong>{{ giveaway.stats.total_weight }}</strong>
                </article>
                <article class="stat-card stack">
                    <p class="muted">Призов</p>
                    <strong>{{ giveaway.stats.prizes_count }}</strong>
                </article>
            </div>
        </article>

        <article class="page-card stack">
            <div class="page-header">
                <div>
                    <h2>Победители</h2>
                </div>
            </div>

            <div v-if="giveaway.winners.length" class="stack">
                <article v-for="winner in giveaway.winners" :key="winner.id" class="surface-card stack">
                    <strong>{{ winner.telegram || winner.name || `User #${winner.user_id}` }}</strong>
                    <p>{{ winner.duration_months }} мес. · вес {{ winner.weight_at_draw }}</p>
                    <p>Подходящих рефералов: {{ winner.eligible_referrals_count_at_draw }}</p>
                    <p>Выдача приза: {{ winner.prize_status }}</p>
                    <p v-if="winner.prize_error" class="muted">{{ winner.prize_error }}</p>
                </article>
            </div>
            <p v-else class="muted">Победителей пока нет.</p>
        </article>
    </section>

    <section v-if="mode === 'edit'" class="page-card stack">
        <div class="page-header">
            <div>
                <h2>Участники</h2>
                <p>Здесь видны финальные snapshot-значения после draw и текущие участники до завершения розыгрыша.</p>
            </div>
        </div>

        <div v-if="giveaway.participants.length" class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Вступил</th>
                        <th>Вес</th>
                        <th>Подходящих рефералов</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="participant in giveaway.participants" :key="participant.id">
                        <td>{{ participant.telegram || participant.name || `User #${participant.user_id}` }}</td>
                        <td>{{ participant.joined_at }}</td>
                        <td>{{ participant.weight_at_draw ?? '-' }}</td>
                        <td>{{ participant.eligible_referrals_count_at_draw ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="muted">Участников пока нет.</p>
    </section>
</template>

<style scoped>
.textarea {
    min-height: 120px;
    width: 100%;
    resize: vertical;
    padding: 12px 14px;
    border-radius: 16px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    background: #fff;
}

.prize-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    align-items: end;
    padding: 16px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.92);
}

.prize-row__actions {
    justify-content: flex-end;
}

.surface-card {
    padding: 16px;
    border-radius: 20px;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(248, 250, 252, 0.85);
}

@media (max-width: 960px) {
    .prize-row {
        grid-template-columns: 1fr;
    }
}
</style>
