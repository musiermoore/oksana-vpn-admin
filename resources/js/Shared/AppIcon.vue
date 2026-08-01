<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        default: 'circle',
    },
});

const iconPath = computed(() => {
    const icons = {
        activity: 'M4 12h4l2.5-6 3 12 2.5-6H20',
        bug: 'M9 9V7a3 3 0 1 1 6 0v2m-8 0h10a2 2 0 0 1 2 2v3a7 7 0 1 1-14 0v-3a2 2 0 0 1 2-2Zm-3-3 2 2m12-2-2 2M3 13h2m14 0h2M6 19l1.5-1.5M18 19l-1.5-1.5',
        calendar: 'M7 3v3m10-3v3M4 8h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
        chart: 'M5 19V9m7 10V5m7 14v-7',
        chat: 'M7 8h10a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-6l-4 3v-3H7a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z',
        circle: 'M12 12h.01',
        clock: 'M12 7v5l3 3M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        dashboard: 'M4 13h7V4H4v9Zm9 7h7v-5h-7v5Zm0-9h7V4h-7v7ZM4 20h7v-5H4v5Z',
        gift: 'M20 12v8H4v-8m16 0H4m16 0h-3.5M4 12h3.5m0 0H12m-4.5 0a2.5 2.5 0 1 1 0-5c2 0 4.5 5 4.5 5m0 0h4.5m-4.5 0s2.5-5 4.5-5a2.5 2.5 0 1 1 0 5',
        key: 'M14 11a4 4 0 1 1 1.17 2.83L21 19.66V21h-1.34l-.83-.83-.83.83h-1.34v-1.34l-.83-.83-.83.83h-1.34v-1.34l1.66-1.66A4 4 0 0 1 14 11Z',
        link: 'M10 13a5 5 0 0 1 0-7l1.5-1.5a5 5 0 0 1 7 7L17 13m-3 1a5 5 0 0 1 0 7L12.5 22.5a5 5 0 0 1-7-7L7 14m3-2h4',
        log: 'M8 6h11M8 12h11M8 18h11M4 6h.01M4 12h.01M4 18h.01',
        message: 'M5 6h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8l-4 3v-3H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z',
        nodes: 'M6 6h4v4H6V6Zm8 0h4v4h-4V6ZM6 14h4v4H6v-4Zm8 0h4v4h-4v-4ZM10 8h4m-6 8h8m-8-8v8m8-8v8',
        plus: 'M12 5v14M5 12h14',
        receipt: 'M7 4h10v16l-2-1.5L13 20l-2-1.5L9 20l-2-1.5L5 20V6a2 2 0 0 1 2-2Zm2 5h6m-6 4h6',
        search: 'm21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z',
        send: 'M21 3 10 14m11-11-7 18-4-7-7-4 18-7Z',
        server: 'M4 5h16v5H4V5Zm0 9h16v5H4v-5Zm3-6h.01M7 17h.01',
        shield: 'M12 3 19 6v6c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3Z',
        sliders: 'M4 7h6m4 0h6M8 7v10m8-4v4M16 7v2M4 17h10m4 0h2',
        spark: 'm12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9Z',
        stamp: 'M8 8a4 4 0 1 1 8 0c0 1.3-.6 2.2-1.2 3.1-.5.7-.8 1.4-.8 2.4h1a2 2 0 0 1 2 2V17H7v-1.5a2 2 0 0 1 2-2h1c0-1-.3-1.7-.8-2.4C8.6 10.2 8 9.3 8 8Zm-2 11h12v2H6v-2Z',
        tasks: 'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
        terminal: 'M4 17V7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Zm3-7 3 2.5L7 15m5 0h5',
        users: 'M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2m18 0v-2a4 4 0 0 0-3-3.87M14 4.13a4 4 0 1 1 0 7.75',
        wallet: 'M4 7.5A2.5 2.5 0 0 1 6.5 5H18a2 2 0 0 1 2 2v2H6.5a1.5 1.5 0 0 0 0 3H20v5a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5v-9Zm13 6h.01',
        wrench: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L4 17v3h3l5.3-5.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2-2 2.4-2.4Z',
    };

    return icons[props.name] ?? icons.circle;
});
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path :d="iconPath" />
    </svg>
</template>
