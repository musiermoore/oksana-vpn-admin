<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import JsonSectionEditor from '../../Shared/JsonSectionEditor.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    submit_url: { type: String, required: true },
    config: { type: Object, default: () => ({}) },
});

const pretty = (value) => JSON.stringify(value ?? {}, null, 2);
const form = useForm({
    dns_json: pretty(props.config.dns),
    routing_json: pretty(props.config.routing),
    rules_json: pretty(props.config.rules ?? []),
});

const dns = computed(() => {
    try { return JSON.parse(form.dns_json); } catch { return {}; }
});
const routing = computed(() => {
    try { return JSON.parse(form.routing_json); } catch { return {}; }
});
const rules = computed(() => {
    try { return JSON.parse(form.rules_json); } catch { return []; }
});

const updateSection = (field, value) => {
    form[field] = pretty(value);
};

const setDnsStrategy = (value) => updateSection('dns_json', { ...dns.value, queryStrategy: value });
const setRoutingStrategy = (value) => updateSection('routing_json', { ...routing.value, domainStrategy: value });
const addRule = () => updateSection('rules_json', [...(Array.isArray(rules.value) ? rules.value : []), { domain: ['domain:example.com'], outboundTag: 'direct' }]);
const removeRule = (index) => updateSection('rules_json', rules.value.filter((_, itemIndex) => itemIndex !== index));
const ruleText = (rule) => JSON.stringify(rule, null, 2);
const updateRule = (index, value) => {
    try {
        const nextRules = [...rules.value];
        nextRules[index] = JSON.parse(value);
        updateSection('rules_json', nextRules);
    } catch {
        // The JSON mode remains available for incomplete rule edits.
    }
};
</script>

<template>
    <section class="page-card stack">
        <div class="page-header">
            <div>
                <h1>Общая конфигурация JSON</h1>
                <p>DNS, стратегия маршрутизации и общие правила для стандартных JSON-профилей.</p>
            </div>
        </div>

        <form class="stack" @submit.prevent="form.put(props.submit_url)">
            <JsonSectionEditor
                v-model="form.dns_json"
                title="Настройки DNS"
                description="Используется в стандартных JSON-конфигурациях."
                :error="form.errors.dns_json"
            >
                <label class="field">
                    <span>Стратегия DNS-запросов</span>
                    <AppSelect
                        :model-value="dns.queryStrategy || 'UseIPv4'"
                        :options="[
                            { value: 'UseIPv4', label: 'Только IPv4' },
                            { value: 'UseIPv6', label: 'Только IPv6' },
                            { value: 'UseIP', label: 'IPv4 и IPv6' },
                            { value: 'AsIs', label: 'Без изменений' },
                        ]"
                        @update:model-value="setDnsStrategy"
                    />
                </label>
                <label class="field">
                    <span>Серверы DNS в формате JSON</span>
                    <AppTextarea :model-value="pretty(dns.servers || [])" rows="8" spellcheck="false" @update:model-value="updateSection('dns_json', { ...dns, servers: JSON.parse($event) })" />
                </label>
            </JsonSectionEditor>

            <JsonSectionEditor
                v-model="form.routing_json"
                title="Маршрутизация"
                description="Определяет, когда разрешать домены в IP-адреса для стандартных профилей."
                :error="form.errors.routing_json"
            >
                <label class="field">
                    <span>Стратегия обработки доменов</span>
                    <AppSelect
                        :model-value="routing.domainStrategy || 'AsIs'"
                        :options="[
                            { value: 'AsIs', label: 'AsIs — без изменения' },
                            { value: 'IPIfNonMatch', label: 'IPIfNonMatch — определять IP, если правила не совпали' },
                            { value: 'IPOnDemand', label: 'IPOnDemand — определять IP по запросу' },
                        ]"
                        @update:model-value="setRoutingStrategy"
                    />
                </label>
            </JsonSectionEditor>

            <JsonSectionEditor
                v-model="form.rules_json"
                title="Общие правила"
                description="Эти правила применяются ко всем стандартным JSON-профилям."
                :error="form.errors.rules_json"
            >
                <div class="stack">
                    <div v-for="(rule, index) in rules" :key="index" class="rule-editor">
                        <AppTextarea :model-value="ruleText(rule)" rows="6" spellcheck="false" @change="updateRule(index, $event.target.value)" />
                        <AppButton variant="danger" type="button" @click="removeRule(index)">Удалить правило</AppButton>
                    </div>
                    <AppButton variant="secondary" type="button" @click="addRule">Добавить правило</AppButton>
                </div>
            </JsonSectionEditor>

            <div class="actions">
                <AppButton type="submit" :disabled="form.processing">Сохранить</AppButton>
            </div>
        </form>
    </section>
</template>

<style scoped>
.rule-editor {
    border: 1px solid var(--surface-border, #d1d5db);
    border-radius: 0.5rem;
    padding: 1rem;
}
</style>
