<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SubscriptionTypeForm from './Form.vue';

defineOptions({ layout: AppLayout });

defineProps({
    type: { type: Object, required: true },
    catalog: { type: Array, default: () => [] },
});
</script>

<template>
    <SubscriptionTypeForm :type="type" :catalog="catalog" />
</template>
