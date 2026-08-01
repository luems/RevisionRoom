<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[#131313] pt-6 sm:justify-center sm:pt-0"
    >
        <div class="mb-4">
            <Link href="/" class="text-4xl font-editorial font-bold text-accent tracking-tight select-none">
                RevisionRoom
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-[#1c1b1b] border border-[#2a2a2a] px-8 py-8 sm:max-w-md rounded-sm"
        >
            <slot />
        </div>
    </div>
</template>
