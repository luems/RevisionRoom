<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'block w-full ps-3 pe-4 py-2.5 border-l-4 border-accent text-start text-xs font-mono-technical uppercase tracking-wider font-bold text-accent bg-[#1c1b1b] focus:outline-none transition duration-150 ease-in-out'
        : 'block w-full ps-3 pe-4 py-2.5 border-l-4 border-transparent text-start text-xs font-mono-technical uppercase tracking-wider font-bold text-gray-400 hover:text-gray-200 hover:bg-[#1c1b1b] hover:border-[#393939] focus:outline-none transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
