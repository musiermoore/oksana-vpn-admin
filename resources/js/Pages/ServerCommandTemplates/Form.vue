<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    template: { type: Object, default: null },
    submit_url: { type: String, required: true },
    method: { type: String, required: true },
    formats: { type: Array, default: () => [] },
});

let nextBlockKey = 0;
let nextAnswerKey = 0;

const newAnswer = () => ({ key: ++nextAnswerKey, scan: '', answer: '', send: 'enter' });
const newBlock = () => ({ key: ++nextBlockKey, command: '', answers: [] });

const existingBlocks = () => {
    if (props.template?.format !== 'blocks' || !props.template.content) return [newBlock()];

    try {
        const parsed = JSON.parse(props.template.content);
        if (!Array.isArray(parsed.blocks) || parsed.blocks.length === 0) return [newBlock()];

        return parsed.blocks.map((block) => ({
            key: ++nextBlockKey,
            command: String(block.command ?? ''),
            answers: Array.isArray(block.answers)
                ? block.answers.map((answer) => ({
                    key: ++nextAnswerKey,
                    scan: String(answer.scan ?? ''),
                    answer: String(answer.answer ?? ''),
                    send: answer.send === 'raw' ? 'raw' : 'enter',
                }))
                : [],
        }));
    } catch {
        return [newBlock()];
    }
};

const commandBlocks = ref(existingBlocks());
const sendOptions = [
    { value: 'enter', label: 'Send answer and press Enter' },
    { value: 'raw', label: 'Send answer only' },
];

const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    format: props.template?.format ?? 'script',
    content: props.template?.content ?? '',
    timeout_seconds: props.template?.timeout_seconds ?? 300,
});

const addCommand = () => commandBlocks.value.push(newBlock());
const removeCommand = (index) => commandBlocks.value.splice(index, 1);
const addCondition = (block) => block.answers.push(newAnswer());
const removeCondition = (block, index) => block.answers.splice(index, 1);

const serializeCommandBlocks = () => JSON.stringify({
    blocks: commandBlocks.value.map(({ command, answers }) => ({
        command,
        answers: answers.map(({ scan, answer, send }) => ({ scan, answer, send })),
    })),
}, null, 2);

const submit = () => {
    if (form.format === 'blocks') form.content = serializeCommandBlocks();

    if (props.method === 'put') {
        form.put(props.submit_url);
        return;
    }

    form.post(props.submit_url);
};
</script>

<template>
    <Head :title="template ? 'Редактировать шаблон' : 'Новый шаблон команд'" />

    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>{{ template ? 'Редактировать шаблон' : 'Новый шаблон команд' }}</h1>
                <p>Raw script выполняется как shell-файл. Для формата блоков добавьте команды и условия ответов на вопросы сервера.</p>
            </div>
        </div>

        <form class="grid grid--two" @submit.prevent="submit">
            <label class="field" style="grid-column: 1 / -1;">
                <span>Название</span>
                <AppInput v-model="form.name" required maxlength="255" />
                <small v-if="form.errors.name" class="field-error">{{ form.errors.name }}</small>
            </label>

            <label class="field">
                <span>Формат</span>
                <AppSelect v-model="form.format" :options="formats" />
                <small v-if="form.errors.format" class="field-error">{{ form.errors.format }}</small>
            </label>

            <label class="field">
                <span>Тайм-аут, секунды</span>
                <AppInput v-model.number="form.timeout_seconds" type="number" min="1" max="3600" required />
                <small v-if="form.errors.timeout_seconds" class="field-error">{{ form.errors.timeout_seconds }}</small>
            </label>

            <label class="field" style="grid-column: 1 / -1;">
                <span>Описание</span>
                <AppTextarea v-model="form.description" rows="2" maxlength="2000" />
                <small v-if="form.errors.description" class="field-error">{{ form.errors.description }}</small>
            </label>

            <label v-if="form.format === 'script'" class="field" style="grid-column: 1 / -1;">
                <span>Shell script</span>
                <AppTextarea
                    v-model="form.content"
                    rows="18"
                    spellcheck="false"
                    placeholder="#!/usr/bin/env bash&#10;set -euo pipefail&#10;&#10;# Add commands here"
                />
                <small class="muted">For an automatic answer, add a comment like: # [{ANSWER: &quot;y&quot;, SCAN: &quot;Continue? [Y/n]&quot;}]</small>
                <small v-if="form.errors.content" class="field-error">{{ form.errors.content }}</small>
            </label>

            <div v-else class="command-builder" style="grid-column: 1 / -1;">
                <div class="command-builder__intro">
                    <div>
                        <h2>Commands</h2>
                        <p>Commands run in order. The timeout above applies to the whole template.</p>
                    </div>
                    <AppButton type="button" variant="secondary" @click="addCommand">+ Add a command</AppButton>
                </div>

                <article v-for="(block, index) in commandBlocks" :key="block.key" class="command-card">
                    <div class="command-card__header">
                        <strong>Command {{ index + 1 }}</strong>
                        <AppButton type="button" variant="danger" :disabled="commandBlocks.length === 1" @click="removeCommand(index)">Remove</AppButton>
                    </div>

                    <label class="field">
                        <span>Command to run</span>
                        <AppTextarea v-model="block.command" rows="2" spellcheck="false" placeholder="sudo apt install nginx" />
                    </label>

                    <div class="condition-list">
                        <div v-for="(condition, conditionIndex) in block.answers" :key="condition.key" class="condition-card">
                            <div class="condition-card__header">
                                <strong>Prompt answer {{ conditionIndex + 1 }}</strong>
                                <AppButton type="button" variant="danger" @click="removeCondition(block, conditionIndex)">Remove</AppButton>
                            </div>
                            <label class="field">
                                <span>When terminal output contains</span>
                                <AppInput v-model="condition.scan" placeholder="Continue? [Y/n]" />
                            </label>
                            <div class="condition-card__fields">
                                <label class="field">
                                    <span>Answer to send</span>
                                    <AppInput v-model="condition.answer" placeholder="y" />
                                </label>
                                <label class="field">
                                    <span>After the answer</span>
                                    <AppSelect v-model="condition.send" :options="sendOptions" />
                                </label>
                            </div>
                        </div>
                        <AppButton type="button" variant="secondary" @click="addCondition(block)">+ Add a prompt condition</AppButton>
                    </div>
                </article>

                <small v-if="form.errors.content" class="field-error">{{ form.errors.content }}</small>
            </div>

            <div class="actions" style="grid-column: 1 / -1;">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
                <AppButton variant="secondary" href="/server-command-templates">Назад</AppButton>
            </div>
        </form>
    </section>
</template>

<style scoped>
.command-builder {
    display: grid;
    gap: 1rem;
}

.command-builder__intro,
.command-card__header,
.condition-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.command-builder__intro h2,
.command-builder__intro p {
    margin: 0;
}

.command-builder__intro p {
    margin-top: .25rem;
    opacity: .75;
}

.command-card,
.condition-card {
    display: grid;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--border-color, #d1d5db);
    border-radius: .75rem;
}

.condition-list {
    display: grid;
    gap: .75rem;
    padding-left: 1rem;
    border-left: 2px solid var(--border-color, #d1d5db);
}

.condition-card__fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

@media (max-width: 700px) {
    .condition-card__fields {
        grid-template-columns: 1fr;
    }

    .command-builder__intro { align-items: stretch; flex-direction: column; }
}
</style>
