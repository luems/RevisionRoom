<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="bg-slate-950 border border-white/10 rounded-sm text-sm text-white focus:outline-none focus:border-accent w-full px-3 py-2"
        v-model="model"
        ref="input"
    />
</template>
