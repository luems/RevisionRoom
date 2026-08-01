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
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-accent text-xs font-mono-technical uppercase tracking-wider font-bold text-accent focus:outline-none focus:border-accent-hover transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-xs font-mono-technical uppercase tracking-wider font-bold text-gray-400 hover:text-gray-200 hover:border-[#393939] focus:outline-none focus:text-gray-200 transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
