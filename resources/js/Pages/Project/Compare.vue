<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    project: Object,
    draft1: Object,
    draft2: Object,
});

const player1 = ref(null);
const player2 = ref(null);

const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);

// Get max duration
const maxDuration = computed(() => {
    const d1 = props.draft1?.duration || 0;
    const d2 = props.draft2?.duration || 0;
    return Math.max(d1, d2) || 10;
});

// Update timeline values during playback
const handleTimeUpdate = () => {
    if (player1.value) {
        currentTime.value = player1.value.currentTime;
    }
};

const handleLoadedMetadata = () => {
    if (player1.value) {
        duration.value = player1.value.duration;
    }
};

// Play / Pause Master Control
const togglePlay = () => {
    if (!player1.value || !player2.value) return;

    if (isPlaying.value) {
        player1.value.pause();
        player2.value.pause();
        isPlaying.value = false;
        console.log('[CompareView] Playback PAUSED for both players.');
    } else {
        player1.value.play().catch((err) => console.error('[CompareView] Player 1 play failed:', err));
        player2.value.play().catch((err) => console.error('[CompareView] Player 2 play failed:', err));
        isPlaying.value = true;
        console.log('[CompareView] Playback STARTED for both players.');
    }
};

// Seek Master Control (clamped to each player's duration to prevent errors on mismatch lengths)
const handleSeekChange = (e) => {
    const targetTime = parseFloat(e.target.value);
    currentTime.value = targetTime;

    if (player1.value) {
        player1.value.currentTime = Math.min(targetTime, player1.value.duration || targetTime);
    }
    if (player2.value) {
        player2.value.currentTime = Math.min(targetTime, player2.value.duration || targetTime);
    }
    console.log(`[CompareView] Master seek to: ${targetTime.toFixed(2)}s`);
};

// Jump to Time (called when clicking comment timestamp)
const jumpToTime = (seconds) => {
    if (seconds === null || seconds === undefined) return;
    
    currentTime.value = seconds;
    if (player1.value) {
        player1.value.currentTime = Math.min(seconds, player1.value.duration || seconds);
    }
    if (player2.value) {
        player2.value.currentTime = Math.min(seconds, player2.value.duration || seconds);
    }
    console.log(`[CompareView] Comment jump to: ${seconds.toFixed(2)}s`);

    // Optionally auto-play on jump
    if (!isPlaying.value) {
        togglePlay();
    }
};

// Format seconds to MM:SS
const formatTime = (seconds) => {
    if (seconds === null || seconds === undefined) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

// Comment Form Setup (referenced to draft2 - latest)
const commentForm = useForm({
    content: '',
    timestamp_seconds: null,
});

const submitComment = () => {
    if (!props.draft2) return;

    if (player2.value) {
        commentForm.timestamp_seconds = player2.value.currentTime;
    }

    commentForm.post(route('comments.store', props.draft2.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            console.log('[CompareView] Comment submitted successfully on latest version!');
        },
    });
};

onMounted(() => {
    if (player1.value) {
        player1.value.addEventListener('timeupdate', handleTimeUpdate);
        player1.value.addEventListener('loadedmetadata', handleLoadedMetadata);
    }
});

onUnmounted(() => {
    if (player1.value) {
        player1.value.removeEventListener('timeupdate', handleTimeUpdate);
        player1.value.removeEventListener('loadedmetadata', handleLoadedMetadata);
    }
});
</script>

<template>
    <Head :title="`Compare: ${project.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-100">Version Comparison</h2>
                    <p class="text-sm text-gray-400 mt-1">{{ project.name }}</p>
                </div>
                <Link :href="route('projects.show', project.id)" class="btn-secondary text-xs flex items-center gap-1">
                    ← Back to Project
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Two Column Layout: Left (Players & Master Bar) | Right (Revisions) -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left: Sync Video Players & Control Panel -->
                    <div class="lg:col-span-3 space-y-6">
                        
                        <!-- Dual Player Panel -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Left Player: Draft 1 -->
                            <div class="glass-card overflow-hidden">
                                <div class="p-3 bg-slate-900 border-b border-white/5 flex justify-between items-center">
                                    <span class="font-bold text-gray-200">v{{ draft1?.version_number }}</span>
                                    <span class="text-xs text-gray-400 truncate max-w-[180px]">{{ draft1?.original_filename }}</span>
                                </div>
                                <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer" @click="togglePlay">
                                    <video 
                                        ref="player1" 
                                        :src="draft1?.video_url" 
                                        class="w-full h-full"
                                        preload="auto"
                                    ></video>
                                    <div v-if="!isPlaying" class="absolute inset-0 bg-black/35 flex items-center justify-center">
                                        <div class="p-4 bg-indigo-600 rounded-full shadow-lg shadow-indigo-600/30 text-white transform hover:scale-110 transition-transform duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Player: Draft 2 -->
                            <div class="glass-card overflow-hidden">
                                <div class="p-3 bg-slate-900 border-b border-white/5 flex justify-between items-center">
                                    <span class="font-bold text-gray-200">v{{ draft2?.version_number }} (Latest)</span>
                                    <span class="text-xs text-gray-400 truncate max-w-[180px]">{{ draft2?.original_filename }}</span>
                                </div>
                                <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer" @click="togglePlay">
                                    <video 
                                        ref="player2" 
                                        :src="draft2?.video_url" 
                                        class="w-full h-full"
                                        preload="auto"
                                    ></video>
                                    <div v-if="!isPlaying" class="absolute inset-0 bg-black/35 flex items-center justify-center">
                                        <div class="p-4 bg-indigo-600 rounded-full shadow-lg shadow-indigo-600/30 text-white transform hover:scale-110 transition-transform duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Unified Master Control Panel -->
                        <div class="glass-card p-6 bg-slate-900/50 space-y-4">
                            <!-- Timeline Slider -->
                            <div class="flex items-center gap-4">
                                <span class="text-xs font-mono text-gray-400">{{ formatTime(currentTime) }}</span>
                                <input 
                                    type="range" 
                                    :min="0" 
                                    :max="maxDuration" 
                                    :step="0.1" 
                                    :value="currentTime" 
                                    @input="handleSeekChange"
                                    class="flex-1 accent-indigo-500 bg-slate-950 h-1.5 rounded-lg appearance-none cursor-pointer"
                                />
                                <span class="text-xs font-mono text-gray-400">{{ formatTime(maxDuration) }}</span>
                            </div>

                            <!-- Master controls (Play/Pause) -->
                            <div class="flex justify-center items-center gap-6">
                                <button @click="togglePlay" class="p-4 bg-indigo-600 hover:bg-indigo-500 rounded-full text-white shadow-lg shadow-indigo-600/20 hover:scale-105 transition-all duration-200">
                                    <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Comments & Revisions lists for BOTH drafts -->
                    <div class="space-y-4 lg:col-span-1">
                        <div class="glass-card p-4 flex flex-col h-[320px]">
                            <h3 class="font-bold text-base border-b border-white/5 pb-3 mb-3 text-indigo-400">Sync Revisions</h3>
                            
                            <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                                <!-- Draft 1 Comments -->
                                <div>
                                    <h4 class="text-xs uppercase font-bold text-gray-400 mb-2 tracking-wider">v{{ draft1?.version_number }} Feedback</h4>
                                    <div v-if="!draft1?.comments || draft1.comments.length === 0" class="text-xs text-gray-600 italic pl-2 mb-4">No comments.</div>
                                    <div v-else class="space-y-2 mb-4">
                                        <div v-for="c in draft1.comments" :key="c.id" class="p-2.5 bg-slate-950/40 border border-white/5 rounded-lg text-xs">
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <span v-if="c.timestamp_seconds !== null" @click="jumpToTime(c.timestamp_seconds)" class="bg-indigo-500/20 text-indigo-300 font-mono px-1.5 py-0.5 rounded cursor-pointer hover:bg-indigo-500 hover:text-white transition-all">
                                                    {{ formatTime(c.timestamp_seconds) }}
                                                </span>
                                                <span class="font-bold text-gray-400">{{ c.author_name }}</span>
                                            </div>
                                            <p class="text-gray-200 leading-relaxed">{{ c.content }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div class="border-t border-white/5 my-3"></div>

                                <!-- Draft 2 Comments -->
                                <div>
                                    <h4 class="text-xs uppercase font-bold text-gray-400 mb-2 tracking-wider">v{{ draft2?.version_number }} Feedback</h4>
                                    <div v-if="!draft2?.comments || draft2.comments.length === 0" class="text-xs text-gray-600 italic pl-2">No comments.</div>
                                    <div v-else class="space-y-2">
                                        <div v-for="c in draft2.comments" :key="c.id" class="p-2.5 bg-slate-950/40 border border-white/5 rounded-lg text-xs">
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <span v-if="c.timestamp_seconds !== null" @click="jumpToTime(c.timestamp_seconds)" class="bg-indigo-500/20 text-indigo-300 font-mono px-1.5 py-0.5 rounded cursor-pointer hover:bg-indigo-500 hover:text-white transition-all">
                                                    {{ formatTime(c.timestamp_seconds) }}
                                                </span>
                                                <span class="font-bold text-gray-400">{{ c.author_name }}</span>
                                            </div>
                                            <p class="text-gray-200 leading-relaxed">{{ c.content }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Feedback Form (referenced to draft2) -->
                        <div v-if="draft2" class="glass-card p-4">
                            <h4 class="text-xs uppercase font-bold text-gray-400 mb-2 tracking-wider">Add Feedback (v{{ draft2.version_number }})</h4>
                            <form @submit.prevent="submitComment" class="space-y-3">
                                <textarea 
                                    v-model="commentForm.content" 
                                    placeholder="Leave feedback at current frame..." 
                                    rows="2" 
                                    class="w-full bg-slate-950/50 border border-white/10 rounded-xl px-3 py-2 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500" 
                                    required
                                ></textarea>
                                <div class="flex justify-between items-center text-[10px] text-gray-400">
                                    <span>Time: <span class="font-mono text-indigo-300">{{ formatTime(currentTime) }}</span></span>
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-1 px-3 rounded-lg text-xs transition-colors">
                                        Submit Comment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
