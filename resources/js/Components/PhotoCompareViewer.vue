<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    draft1: Object, // Older draft
    draft2: Object, // Newer draft
    selectedItemIndex: {
        type: Number,
        default: 0,
    },
});

const mode = ref('side'); // 'side' | 'slider' | 'opacity' | 'diff'
const sliderPosition = ref(50); // 0% to 100%
const opacityLevel = ref(50); // 0% to 100%

const photo1 = computed(() => {
    if (!props.draft1) return null;
    const items = props.draft1.items || [];
    return items[props.selectedItemIndex] || items[0] || { file_url: props.draft1.video_url || props.draft1.thumbnail_url };
});

const photo2 = computed(() => {
    if (!props.draft2) return null;
    const items = props.draft2.items || [];
    return items[props.selectedItemIndex] || items[0] || { file_url: props.draft2.video_url || props.draft2.thumbnail_url };
});
</script>

<template>
    <div class="space-y-4">
        <!-- Comparison Mode Controls Header -->
        <div class="flex items-center justify-between bg-[#1c1b1b] p-3 rounded-xl border border-white/10 text-xs font-mono-technical flex-wrap gap-3">
            <div class="flex items-center gap-1.5 bg-[#131313] p-1 rounded-lg border border-white/5">
                <button @click="mode = 'side'"
                        :class="`px-3 py-1.5 rounded-md font-bold transition-colors ${
                            mode === 'side' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'
                        }`">
                    Side-by-Side
                </button>
                <button @click="mode = 'slider'"
                        :class="`px-3 py-1.5 rounded-md font-bold transition-colors ${
                            mode === 'slider' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'
                        }`">
                    Reveal Slider
                </button>
                <button @click="mode = 'opacity'"
                        :class="`px-3 py-1.5 rounded-md font-bold transition-colors ${
                            mode === 'opacity' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'
                        }`">
                    Opacity Blend
                </button>
                <button @click="mode = 'diff'"
                        :class="`px-3 py-1.5 rounded-md font-bold transition-colors ${
                            mode === 'diff' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'
                        }`">
                    Difference View
                </button>
            </div>

            <!-- Dynamic Sub-Controls for Sliders -->
            <div v-if="mode === 'slider'" class="flex items-center gap-2">
                <span class="text-gray-400">Reveal Position:</span>
                <input type="range" min="0" max="100" v-model="sliderPosition" class="w-32 accent-accent cursor-pointer" />
                <span class="text-accent font-bold">{{ sliderPosition }}%</span>
            </div>

            <div v-if="mode === 'opacity'" class="flex items-center gap-2">
                <span class="text-gray-400">v2 Opacity:</span>
                <input type="range" min="0" max="100" v-model="opacityLevel" class="w-32 accent-accent cursor-pointer" />
                <span class="text-accent font-bold">{{ opacityLevel }}%</span>
            </div>
        </div>

        <!-- 1. SIDE-BY-SIDE MODE -->
        <div v-if="mode === 'side'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Older Cut (v1) -->
            <div class="bg-black aspect-video rounded-xl border border-white/10 overflow-hidden relative flex flex-col justify-between p-3">
                <div class="flex justify-between items-center z-10">
                    <span class="bg-accent text-[#131313] font-mono-technical font-bold text-[10px] px-2.5 py-0.5 rounded uppercase">
                        v{{ draft1?.version_number ?? 1 }} (Original)
                    </span>
                    <span class="text-[10px] font-mono-technical text-gray-400">{{ photo1?.original_filename ?? 'Draft 1' }}</span>
                </div>
                <div class="flex-1 flex items-center justify-center py-2 overflow-hidden">
                    <img v-if="photo1?.file_url" :src="photo1.file_url" class="max-h-[50vh] object-contain rounded" alt="v1 photo" />
                    <span v-else class="text-xs text-gray-500 font-mono-technical">No photo data</span>
                </div>
            </div>

            <!-- Newer Cut (v2) -->
            <div class="bg-black aspect-video rounded-xl border border-white/10 overflow-hidden relative flex flex-col justify-between p-3">
                <div class="flex justify-between items-center z-10">
                    <span class="bg-accent text-[#131313] font-mono-technical font-bold text-[10px] px-2.5 py-0.5 rounded uppercase">
                        v{{ draft2?.version_number ?? 2 }} (Latest)
                    </span>
                    <span class="text-[10px] font-mono-technical text-emerald-400 font-bold">● Active Revision</span>
                </div>
                <div class="flex-1 flex items-center justify-center py-2 overflow-hidden">
                    <img v-if="photo2?.file_url" :src="photo2.file_url" class="max-h-[50vh] object-contain rounded" alt="v2 photo" />
                    <span v-else class="text-xs text-gray-500 font-mono-technical">No photo data</span>
                </div>
            </div>
        </div>

        <!-- 2. BEFORE-AND-AFTER SLIDER MODE -->
        <div v-else-if="mode === 'slider'" class="bg-black aspect-video rounded-xl border border-white/10 overflow-hidden relative flex items-center justify-center select-none">
            <div class="relative w-full h-full flex items-center justify-center">
                <!-- Base Image (v1) -->
                <img v-if="photo1?.file_url" :src="photo1.file_url" class="max-h-full max-w-full object-contain absolute" alt="v1 photo base" />

                <!-- Top Revealed Image (v2) with clip-path -->
                <div class="absolute inset-0 flex items-center justify-center overflow-hidden"
                     :style="{ clipPath: `inset(0 ${100 - sliderPosition}% 0 0)` }">
                    <img v-if="photo2?.file_url" :src="photo2.file_url" class="max-h-full max-w-full object-contain" alt="v2 photo reveal" />
                </div>

                <!-- Vertical Draggable Divider Line -->
                <div class="absolute top-0 bottom-0 w-1 bg-accent cursor-ew-resize shadow-[0_0_15px_rgba(195,244,0,0.8)] z-20"
                     :style="{ left: `${sliderPosition}%` }">
                    <div class="w-6 h-6 rounded-full bg-accent text-[#131313] font-bold text-[10px] flex items-center justify-center absolute top-1/2 -translate-y-1/2 -translate-x-1/2 shadow-lg">
                        ↔
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. OPACITY BLEND OVERLAY MODE -->
        <div v-else-if="mode === 'opacity'" class="bg-black aspect-video rounded-xl border border-white/10 overflow-hidden relative flex items-center justify-center select-none">
            <div class="relative w-full h-full flex items-center justify-center">
                <!-- Base Image (v1) -->
                <img v-if="photo1?.file_url" :src="photo1.file_url" class="max-h-full max-w-full object-contain absolute" alt="v1 photo base" />

                <!-- Overlay Image (v2) with opacity -->
                <img v-if="photo2?.file_url"
                     :src="photo2.file_url"
                     class="max-h-full max-w-full object-contain absolute transition-opacity duration-75"
                     :style="{ opacity: opacityLevel / 100 }"
                     alt="v2 photo overlay" />
            </div>
        </div>

        <!-- 4. DIFFERENCE VIEW MODE -->
        <div v-else-if="mode === 'diff'" class="bg-black aspect-video rounded-xl border border-white/10 overflow-hidden relative flex items-center justify-center p-6 text-center">
            <div class="space-y-3 max-w-md">
                <div class="w-12 h-12 rounded-full bg-accent/10 border border-accent/30 text-accent flex items-center justify-center mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h4 class="text-lg font-editorial font-bold text-gray-100">Visual Difference Detection</h4>
                <p class="text-xs text-gray-400 font-mono-technical leading-relaxed">
                    Compare visual shifts between v{{ draft1?.version_number }} and v{{ draft2?.version_number }}. Use the <span class="text-accent">Reveal Slider</span> or <span class="text-accent">Opacity Blend</span> modes for exact pixel comparison.
                </p>
            </div>
        </div>

    </div>
</template>
