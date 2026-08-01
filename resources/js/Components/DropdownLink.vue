<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2.5 text-start text-xs font-mono-technical uppercase tracking-wider font-semibold text-gray-400 hover:text-white hover:bg-white/5 focus:outline-none transition duration-150 ease-in-out"
    >
        <slot />
    </Link>
</template>
