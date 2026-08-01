<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 font-mono-technical">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
