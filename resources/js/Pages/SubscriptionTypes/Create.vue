<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SubscriptionTypeForm from './Form.vue';

defineOptions({ layout: AppLayout });

defineProps({
    catalog: { type: Array, default: () => [] },
});
</script>

<template>
    <SubscriptionTypeForm :catalog="catalog" />
</template>
