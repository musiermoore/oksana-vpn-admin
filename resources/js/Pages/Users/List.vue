<script setup>
defineProps({
    users: {
        type: Array,
        default: []
    }
})
</script>

<template>
    <div>
        <h1>
            Cписок пользователей
        </h1>

        <div v-for="user in users">
            {{ user.telegram }}
        </div>
    </div>
</template>

<style scoped>

</style>
