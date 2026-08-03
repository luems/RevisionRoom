<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    photoUrl: String,
    comments: {
        type: Array,
        default: () => [],
    },
    selectedCommentId: [Number, String],
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['add-pin', 'select-comment']);

// Interactive state
const canvasMode = ref('navigate'); // 'navigate' | 'pin'
const zoomLevel = ref(1);
const panX = ref(0);
const panY = ref(0);
const isPanning = ref(false);
const startPan = ref({ x: 0, y: 0 });
const canvasContainer = ref(null);
const imageElement = ref(null);

// Temporary pin placement state
const tempPin = ref(null); // { x: 42.5, y: 68.2 }

// Normalized comments with pins
const pinnedComments = computed(() => {
    return props.comments.filter(c => c.position_x !== null && c.position_y !== null);
});

// Canvas controls
const zoomIn = () => {
    zoomLevel.value = Math.min(zoomLevel.value + 0.25, 4);
};

const zoomOut = () => {
    zoomLevel.value = Math.max(zoomLevel.value - 0.25, 0.5);
};

const resetZoomPan = () => {
    zoomLevel.value = 1;
    panX.value = 0;
    panY.value = 0;
};

// Handle Mouse Wheel Zooming in Navigate Mode
const handleWheel = (e) => {
    e.preventDefault();
    const delta = e.deltaY < 0 ? 0.15 : -0.15;
    const newZoom = Math.min(Math.max(zoomLevel.value + delta, 0.5), 4);
    zoomLevel.value = newZoom;
};

// Handle Mouse Down (Panning or Pin placement)
const handleMouseDown = (e) => {
    if (canvasMode.value === 'navigate') {
        isPanning.value = true;
        startPan.value = { x: e.clientX - panX.value, y: e.clientY - panY.value };
    }
};

const handleMouseMove = (e) => {
    if (isPanning.value && canvasMode.value === 'navigate') {
        panX.value = e.clientX - startPan.value.x;
        panY.value = e.clientY - startPan.value.y;
    }
};

const handleMouseUp = () => {
    isPanning.value = false;
};

// Handle Click to drop pin in Add Pin Mode
const handleCanvasClick = (e) => {
    if (props.readonly) return;
    if (canvasMode.value !== 'pin') return;
    if (!imageElement.value) return;

    const rect = imageElement.value.getBoundingClientRect();
    if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) {
        return;
    }

    // Calculate percentage relative to actual image element size
    const percentX = ((e.clientX - rect.left) / rect.width) * 100;
    const percentY = ((e.clientY - rect.top) / rect.height) * 100;

    const clampedX = Math.min(Math.max(percentX, 0), 100);
    const clampedY = Math.min(Math.max(percentY, 0), 100);

    tempPin.value = {
        x: parseFloat(clampedX.toFixed(2)),
        y: parseFloat(clampedY.toFixed(2)),
    };

    emit('add-pin', tempPin.value);
};

const cancelTempPin = () => {
    tempPin.value = null;
};

defineExpose({
    cancelTempPin,
    resetZoomPan,
});
</script>

<template>
    <div class="space-y-3">
        <!-- Canvas Control Toolbar -->
        <div class="flex items-center justify-between bg-[#1c1b1b] px-4 py-2.5 rounded-lg border border-white/5 text-xs font-mono-technical flex-wrap gap-2">
            
            <!-- Mode Switcher: slim borderless text-link style -->
            <div class="flex items-center gap-0">
                <button @click="canvasMode = 'navigate'"
                        :class="`flex items-center gap-1.5 px-3 py-1.5 transition-all font-bold rounded-l-md ${
                            canvasMode === 'navigate'
                                ? 'text-accent border border-accent/30 bg-accent/8'
                                : 'text-white/50 border border-white/10 hover:text-white/80 bg-transparent'
                        }`">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5" />
                    </svg>
                    <span>Navigate</span>
                </button>
                <button v-if="!readonly"
                        @click="canvasMode = 'pin'"
                        :class="`flex items-center gap-1.5 px-3 py-1.5 transition-all font-bold rounded-r-md border-l-0 ${
                            canvasMode === 'pin'
                                ? 'text-accent border border-accent/30 bg-accent/8'
                                : 'text-white/50 border border-white/10 hover:text-white/80 bg-transparent'
                        }`">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Pin</span>
                </button>
            </div>

            <!-- Zoom Controls & Help Text -->
            <div class="flex items-center gap-3">
                <span class="text-[11px] text-white/30 hidden sm:inline-block">
                    {{ canvasMode === 'pin' ? 'Click image to drop pin' : 'Drag to pan • Wheel to zoom' }}
                </span>
                <div class="flex items-center gap-0 border border-white/10 rounded-md overflow-hidden">
                    <button @click="zoomOut" class="px-2.5 py-1.5 text-white/50 hover:text-white font-bold border-r border-white/10 transition-colors" title="Zoom Out">−</button>
                    <span class="px-3 py-1.5 text-accent font-bold tabular-nums">{{ Math.round(zoomLevel * 100) }}%</span>
                    <button @click="zoomIn" class="px-2.5 py-1.5 text-white/50 hover:text-white font-bold border-l border-white/10 transition-colors" title="Zoom In">+</button>
                    <button @click="resetZoomPan" class="px-2.5 py-1.5 text-[10px] text-white/30 hover:text-white uppercase font-bold border-l border-white/10 transition-colors" title="Reset View">Reset</button>
                </div>
            </div>

        </div>

        <!-- Interactive Main Photo Canvas Area -->
        <div ref="canvasContainer"
             @wheel="handleWheel"
             @mousedown="handleMouseDown"
             @mousemove="handleMouseMove"
             @mouseup="handleMouseUp"
             @mouseleave="handleMouseUp"
             @click="handleCanvasClick"
             :class="`bg-black aspect-video rounded-xl border border-white/10 relative overflow-hidden flex items-center justify-center select-none ${
                 canvasMode === 'pin' ? 'cursor-crosshair' : isPanning ? 'cursor-grabbing' : 'cursor-grab'
             }`">
            
            <!-- Scalable Image & Pins Wrapper -->
            <div class="relative transition-transform duration-75 ease-out"
                 :style="{ transform: `translate(${panX}px, ${panY}px) scale(${zoomLevel})` }">
                
                <!-- High Resolution Image -->
                <img ref="imageElement"
                     :src="photoUrl"
                     class="max-h-[70vh] w-auto object-contain rounded-sm shadow-2xl pointer-events-auto"
                     alt="Review Photo Canvas" />

                <!-- Existing Saved Pins Layer -->
                <div v-for="(comment, index) in pinnedComments"
                     :key="comment.id"
                     @click.stop="$emit('select-comment', comment)"
                     :style="{ left: `${comment.position_x}%`, top: `${comment.position_y}%` }"
                     class="absolute z-20 cursor-pointer flex flex-col items-center transition-transform hover:scale-110"
                     style="transform: translateX(-50%) translateY(-100%)">
                    <!-- Pin head -->
                    <div :class="`w-6 h-6 rounded-full flex items-center justify-center font-mono-technical font-bold text-[11px] shadow-lg transition-all ${
                        selectedCommentId === comment.id
                            ? 'ring-4 ring-white/30 scale-125'
                            : ''
                    }`"
                         :style="{
                            backgroundColor: selectedCommentId === comment.id
                                ? 'var(--accent)'
                                : comment.is_resolved ? '#10b981'
                                : comment.is_rejected ? '#f59e0b'
                                : '#f43f5e',
                            color: selectedCommentId === comment.id ? '#131313' : '#fff'
                         }">
                        {{ index + 1 }}
                    </div>
                    <!-- Pin tail (triangle pointing down) -->
                    <div class="w-0 h-0"
                         style="border-left: 5px solid transparent; border-right: 5px solid transparent; border-top-width: 8px; border-top-style: solid; margin-top: -1px;"
                         :style="{
                            borderTopColor: selectedCommentId === comment.id
                                ? 'var(--accent)'
                                : comment.is_resolved ? '#10b981'
                                : comment.is_rejected ? '#f59e0b'
                                : '#f43f5e'
                         }">
                    </div>
                </div>

                <!-- Temporary Dropped Pin Marker (no bounce) -->
                <div v-if="tempPin"
                     :style="{ left: `${tempPin.x}%`, top: `${tempPin.y}%` }"
                     class="absolute z-30 flex flex-col items-center"
                     style="transform: translateX(-50%) translateY(-100%)">
                    <!-- Pin head -->
                    <div class="w-7 h-7 rounded-full flex items-center justify-center shadow-2xl ring-4 ring-white/20"
                         style="background-color: var(--accent); color: #131313;">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                    </div>
                    <!-- Pin tail -->
                    <div class="w-0 h-0"
                         style="border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 8px solid var(--accent); margin-top: -1px;">
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>
