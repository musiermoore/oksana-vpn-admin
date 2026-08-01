<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    mode: String,
    submit_url: String,
    user: Object,
    payments: Array,
});

const form = useForm({
    name: props.user?.name ?? '',
    telegram: props.user?.telegram ?? '',
    description: props.user?.description ?? '',
    join_at: props.user?.join_at ?? props.payments[0]?.start_date ?? '',
    create_configs: true,
    is_active: props.user?.is_active ?? true,
    max_devices: props.user?.max_devices ?? 10,
    traffic_limit_bytes: props.user?.traffic_limit_bytes ?? 0,
});

const submit = () => {
    if (props.mode === 'edit') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};

const toggleConfig = (config) => router.post(config.is_active ? config.links.disable : config.links.enable);
const destroyConfig = (config) => confirm(`Удалить конфиг ${config.name}?`) && router.delete(config.links.destroy);
const unlinkXrayConfig = (config) => confirm(`Отвязать Xray-конфиг ${config.name}?`) && router.delete(config.links.destroy);
const toggleXrayConfig = (config) => {
    if (!config.supports_toggle) {
        return;
    }

    router.post(config.enable ? config.links.disable : config.links.enable);
};
const approveTransaction = (transaction) => router.post(transaction.links.approve);
const declineTransaction = (transaction) => confirm('Отклонить транзакцию?') && router.delete(transaction.links.decline);
const destroyTransaction = (transaction) => confirm('Удалить транзакцию?') && router.delete(transaction.links.destroy);
const joinAtOptions = props.payments.map((payment) => ({
    label: `${payment.formatted_start_date} (${payment.amount}₽)`,
    value: payment.start_date,
}));
</script>

<template>
    <Head :title="mode === 'edit' ? 'Редактирование участника' : 'Создание участника'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ mode === 'edit' ? 'Редактирование участника' : 'Создание участника' }}</h1>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label v-if="mode === 'edit'" class="field">
                <span>Активен</span>
                <AppCheckbox v-model="form.is_active" />
            </label>

            <label class="field">
                <span>Имя</span>
                <AppInput v-model="form.name" type="text" required />
            </label>

            <label class="field">
                <span>Telegram</span>
                <AppInput v-model="form.telegram" type="text" />
            </label>

            <label class="field">
                <span>Дата присоединения</span>
                <AppSelect v-model="form.join_at" :options="joinAtOptions" />
            </label>

            <label class="field">
                <span>Лимит устройств</span>
                <AppInput v-model="form.max_devices" type="number" min="0" step="1" />
            </label>

            <label class="field">
                <span>Лимит трафика (байт)</span>
                <AppInput v-model="form.traffic_limit_bytes" type="number" min="0" step="1" />
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description"  />
            </label>

            <label v-if="mode === 'create'" class="field">
                <span>Дефолтные конфиги</span>
                <AppCheckbox v-model="form.create_configs" />
            </label>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/users">Назад</AppButton>
            </div>
        </form>
    </section>

    <template v-if="mode === 'edit' && user">
        <section class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">Конфиги</h2>
                    <p v-if="user.subscription_expires_at">
                        Подписка активна до {{ new Date(user.subscription_expires_at).toLocaleString() }}
                    </p>
                </div>
                <div class="actions">
                    <AppButton href="/configs/create">Создать</AppButton>
                </div>
            </div>

            <div v-if="user.configs.length" class="list">
                <div v-for="config in user.configs" :key="config.id" class="item-row">
                    <Link :href="config.links.edit">{{ config.server.code }}: {{ config.name }}</Link>
                    <div class="actions">
                        <AppButton variant="secondary" :href="config.links.edit">Открыть</AppButton>
                        <AppButton
                            :variant="config.is_active ? 'danger' : 'success'"
                            type="button"
                            @click="toggleConfig(config)"
                        >
                            {{ config.is_active ? 'Отключить' : 'Включить' }}
                        </AppButton>
                        <AppButton variant="danger" type="button" @click="destroyConfig(config)">Удалить</AppButton>
                    </div>
                </div>
            </div>
            <div v-else class="empty-state">У пользователя пока нет конфигов.</div>
        </section>

        <section class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">Xray конфиги</h2>
                </div>
                <div class="actions">
                    <AppButton :href="`/xray-configs/create?user_id=${user.id}`">Создать</AppButton>
                </div>
            </div>

            <div v-if="user.xray_configs?.length" class="list">
                <div v-for="config in user.xray_configs" :key="`${config.protocol}-${config.id}`" class="item-row">
                    <Link :href="config.links.edit">
                        <strong>[{{ config.protocol_label }}]</strong>
                        {{ config.server?.code }}: {{ config.name }}
                    </Link>
                    <div class="actions">
                        <AppButton variant="secondary" :href="config.links.edit">Открыть</AppButton>
                        <AppButton
                            v-if="config.supports_toggle"
                            :variant="config.enable ? 'danger' : 'success'"
                            type="button"
                            @click="toggleXrayConfig(config)"
                        >
                            {{ config.enable ? 'Отключить' : 'Включить' }}
                        </AppButton>
                        <AppButton variant="danger" type="button" @click="unlinkXrayConfig(config)">Отвязать</AppButton>
                    </div>
                </div>
            </div>
            <div v-else class="empty-state">У пользователя пока нет Xray-конфигов.</div>
        </section>

        <section class="page-card stack">
            <div class="page-header">
                <div>
                    <h2 class="section-title">Транзакции</h2>
                </div>
                <div class="actions">
                    <AppButton href="/transactions/create">Создать</AppButton>
                </div>
            </div>

            <div v-if="user.transactions.length" class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Сумма</th>
                            <th>Дата</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="transaction in user.transactions" :key="transaction.id">
                            <td>{{ transaction.amount }}<template v-if="transaction.type"> · {{ transaction.type.name }}</template></td>
                            <td>{{ transaction.formatted_created_at }}</td>
                            <td>
                                <div v-if="transaction.is_approved" class="actions">
                                    <span class="badge badge--success">Принята</span>
                                    <AppButton variant="secondary" :href="transaction.links.edit">Изменить</AppButton>
                                    <AppButton variant="danger" type="button" @click="destroyTransaction(transaction)">Удалить</AppButton>
                                </div>
                                <div v-else class="actions">
                                    <span class="badge">На рассмотрении</span>
                                    <AppButton variant="success" type="button" @click="approveTransaction(transaction)">Принять</AppButton>
                                    <AppButton variant="danger" type="button" @click="declineTransaction(transaction)">Отклонить</AppButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="empty-state">Транзакций пока нет.</div>
        </section>
    </template>
</template>
