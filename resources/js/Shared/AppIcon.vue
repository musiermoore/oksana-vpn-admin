<script setup>
import { computed } from 'vue';
import {
    faBars,
    faBug,
    faCalendarDays,
    faChartColumn,
    faChevronDown,
    faCircleDot,
    faClock,
    faComments,
    faDiagramProject,
    faEnvelope,
    faFileLines,
    faGift,
    faKey,
    faLink,
    faListCheck,
    faMagnifyingGlass,
    faPaperPlane,
    faPlus,
    faReceipt,
    faServer,
    faShieldHalved,
    faSliders,
    faStamp,
    faTableColumns,
    faTerminal,
    faUsers,
    faWallet,
    faWandMagicSparkles,
    faWaveSquare,
    faWrench,
    faXmark,
    faArrowRight,
} from '@fortawesome/free-solid-svg-icons';

const props = defineProps({
    name: {
        type: String,
        default: 'circle',
    },
});

const iconDefinition = computed(() => {
    const icons = {
        activity: faWaveSquare,
        arrowRight: faArrowRight,
        bars: faBars,
        bug: faBug,
        calendar: faCalendarDays,
        chart: faChartColumn,
        chat: faComments,
        chevronDown: faChevronDown,
        circle: faCircleDot,
        clock: faClock,
        close: faXmark,
        dashboard: faTableColumns,
        gift: faGift,
        key: faKey,
        link: faLink,
        log: faFileLines,
        message: faEnvelope,
        nodes: faDiagramProject,
        plus: faPlus,
        receipt: faReceipt,
        search: faMagnifyingGlass,
        send: faPaperPlane,
        server: faServer,
        shield: faShieldHalved,
        sliders: faSliders,
        spark: faWandMagicSparkles,
        stamp: faStamp,
        tasks: faListCheck,
        terminal: faTerminal,
        users: faUsers,
        wallet: faWallet,
        wrench: faWrench,
    };

    return icons[props.name] ?? icons.circle;
});
</script>

<template>
    <FontAwesomeIcon :icon="iconDefinition" aria-hidden="true" />
</template>
