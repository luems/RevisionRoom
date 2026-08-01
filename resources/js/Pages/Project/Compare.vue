<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    project: Object,
    draft1: Object,
    draft2: Object,
    is_client: Boolean,
});

const player1 = ref(null);
const player2 = ref(null);

const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);

// === Loading & Buffering States ===
const p1Loaded = ref(false);
const p2Loaded = ref(false);
const bothLoaded = computed(() => p1Loaded.value && p2Loaded.value);

const p1Buffering = ref(false);
const p2Buffering = ref(false);
const isRecoveringSync = ref(false);
const isBuffering = computed(() => p1Buffering.value || p2Buffering.value || isRecoveringSync.value);
const bufferingLabel = computed(() => {
    if (p1Buffering.value && p2Buffering.value) return 'Both versions buffering…';
    if (p1Buffering.value) return `v${props.draft1?.version_number} is buffering…`;
    if (p2Buffering.value) return `v${props.draft2?.version_number} is buffering…`;
    if (isRecoveringSync.value) return 'Re-synchronizing both versions…';
    return '';
});

// === Desync Notification ===
const desyncNotification = ref(null);
let desyncTimeout = null;

const showDesyncNotice = (message) => {
    desyncNotification.value = message;
    if (desyncTimeout) clearTimeout(desyncTimeout);
    desyncTimeout = setTimeout(() => {
        desyncNotification.value = null;
    }, 5000);
};

// Get max duration
const maxDuration = computed(() => {
    const d1 = props.draft1?.duration || 0;
    const d2 = props.draft2?.duration || 0;
    return Math.max(d1, d2) || duration.value || 10;
});

// Update timeline from player1 as master clock
const handleTimeUpdate = () => {
    if (player1.value) {
        currentTime.value = player1.value.currentTime;
    }
};

// === Frame-level synchronization ===
// Seeking is asynchronous. Playback must not resume until both seeks have
// completed and both decoders have enough data for the next frame.
const SOFT_DRIFT_SECONDS = 0.025;
const HARD_DRIFT_SECONDS = 0.08;
const PLAYBACK_RATE_ADJUSTMENT = 0.05;
let syncAnimationFrame = null;
let syncOperation = 0;

const pauseBoth = () => {
    player1.value?.pause();
    player2.value?.pause();
};

const resetPlaybackRates = () => {
    if (player1.value) player1.value.playbackRate = 1;
    if (player2.value) player2.value.playbackRate = 1;
};

const clampToPlayerDuration = (player, time) => {
    if (!Number.isFinite(player.duration)) return Math.max(0, time);
    return Math.min(Math.max(0, time), player.duration);
};

const seekAndWait = (player, time) => {
    const target = clampToPlayerDuration(player, time);

    if (!player.seeking && Math.abs(player.currentTime - target) <= 0.005) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const cleanup = () => {
            player.removeEventListener('seeked', handleSeeked);
            player.removeEventListener('error', handleError);
        };
        const handleSeeked = () => {
            cleanup();
            resolve();
        };
        const handleError = () => {
            cleanup();
            reject(new Error('Video seek failed.'));
        };

        player.addEventListener('seeked', handleSeeked);
        player.addEventListener('error', handleError);
        player.currentTime = target;
    });
};

const waitUntilPlayable = (player) => {
    // HAVE_FUTURE_DATA: the current frame and at least the next are available.
    if (player.readyState >= 3) return Promise.resolve();

    return new Promise((resolve, reject) => {
        const cleanup = () => {
            player.removeEventListener('canplay', handleCanPlay);
            player.removeEventListener('error', handleError);
        };
        const handleCanPlay = () => {
            cleanup();
            resolve();
        };
        const handleError = () => {
            cleanup();
            reject(new Error('Video buffering failed.'));
        };

        player.addEventListener('canplay', handleCanPlay);
        player.addEventListener('error', handleError);
    });
};

const startTogetherAt = async (time, operation) => {
    const first = player1.value;
    const second = player2.value;
    if (!first || !second) return false;

    pauseBoth();
    resetPlaybackRates();

    await Promise.all([
        seekAndWait(first, time),
        seekAndWait(second, time),
    ]);
    await Promise.all([
        waitUntilPlayable(first),
        waitUntilPlayable(second),
    ]);

    // Manual pause, end, or unmount invalidates an in-flight recovery.
    if (operation !== syncOperation || !isPlaying.value) return false;

    p1Buffering.value = false;
    p2Buffering.value = false;

    // Invoke both play calls in the same task, after the shared seek barrier.
    const firstPlay = first.play();
    const secondPlay = second.play();
    await Promise.all([firstPlay, secondPlay]);

    return operation === syncOperation && isPlaying.value;
};

const recoverSynchronization = async (showNotice = false) => {
    if (isRecoveringSync.value || !isPlaying.value || !player1.value || !player2.value) return;

    const operation = ++syncOperation;
    // Rewind to the lagging video rather than skipping it into unbuffered data.
    const sharedTime = Math.min(player1.value.currentTime, player2.value.currentTime);
    isRecoveringSync.value = true;
    pauseBoth();

    try {
        const resumed = await startTogetherAt(sharedTime, operation);
        if (resumed && showNotice) {
            showDesyncNotice('Playback was out of sync and has been re-synchronized.');
        }
    } catch (error) {
        if (operation === syncOperation) {
            isPlaying.value = false;
            pauseBoth();
            showDesyncNotice('Synchronized playback could not resume. Press play to try again.');
            console.error('[CompareView] Synchronization recovery failed:', error);
        }
    } finally {
        if (operation === syncOperation) {
            isRecoveringSync.value = false;
        }
    }
};

const handleBufferStart = (playerNumber) => {
    if (playerNumber === 1) p1Buffering.value = true;
    if (playerNumber === 2) p2Buffering.value = true;

    // Pause the healthy player immediately; polling allows it to run ahead.
    if (isPlaying.value && !isRecoveringSync.value) {
        pauseBoth();
        recoverSynchronization();
    }
};

const monitorSynchronization = () => {
    syncAnimationFrame = requestAnimationFrame(monitorSynchronization);

    const first = player1.value;
    const second = player2.value;
    if (!first || !second || !isPlaying.value || isRecoveringSync.value || isBuffering.value) return;

    const signedDrift = second.currentTime - first.currentTime;
    const drift = Math.abs(signedDrift);

    if (drift >= HARD_DRIFT_SECONDS) {
        recoverSynchronization(true);
        return;
    }

    // Gently converge sub-frame drift without repeated disruptive seeks.
    if (drift > SOFT_DRIFT_SECONDS) {
        second.playbackRate = signedDrift > 0
            ? 1 - PLAYBACK_RATE_ADJUSTMENT
            : 1 + PLAYBACK_RATE_ADJUSTMENT;
    } else if (second.playbackRate !== 1) {
        second.playbackRate = 1;
    }
};

const startSyncCorrection = () => {
    if (syncAnimationFrame === null) {
        syncAnimationFrame = requestAnimationFrame(monitorSynchronization);
    }
};

const stopSyncCorrection = () => {
    if (syncAnimationFrame !== null) {
        cancelAnimationFrame(syncAnimationFrame);
        syncAnimationFrame = null;
    }
    ++syncOperation;
    isRecoveringSync.value = false;
    resetPlaybackRates();
};

// Play / Pause Master Control
const togglePlay = () => {
    if (!player1.value || !player2.value) return;
    if (!bothLoaded.value) return; // Block play until both loaded

    if (isPlaying.value) {
        player1.value.pause();
        player2.value.pause();
        isPlaying.value = false;
        stopSyncCorrection();
    } else {
        // Sync player2 to player1 before starting
        player2.value.currentTime = player1.value.currentTime;
        resetPlaybackRates();
        const operation = ++syncOperation;
        isPlaying.value = true;

        const p1 = player1.value.play();
        const p2 = player2.value.play();
        startSyncCorrection();

        Promise.all([p1, p2]).catch((error) => {
            if (operation !== syncOperation) return;
            isPlaying.value = false;
            stopSyncCorrection();
            pauseBoth();
            console.error('[CompareView] Synchronized playback failed to start:', error);
        });
    }
};

// Handle video ended — pause both
const handleEnded = () => {
    if (player1.value) player1.value.pause();
    if (player2.value) player2.value.pause();
    isPlaying.value = false;
    stopSyncCorrection();
};

// Seek Master Control
const handleSeekChange = (e) => {
    const targetTime = parseFloat(e.target.value);
    currentTime.value = targetTime;

    if (player1.value) {
        player1.value.currentTime = Math.min(targetTime, player1.value.duration || targetTime);
    }
    if (player2.value) {
        player2.value.currentTime = Math.min(targetTime, player2.value.duration || targetTime);
    }
};

// Jump to Time (called when clicking comment timestamp)
const jumpToTime = (seconds) => {
    if (seconds === null || seconds === undefined) return;
    if (!bothLoaded.value) return;
    
    currentTime.value = seconds;
    if (player1.value) {
        player1.value.currentTime = Math.min(seconds, player1.value.duration || seconds);
    }
    if (player2.value) {
        player2.value.currentTime = Math.min(seconds, player2.value.duration || seconds);
    }

    // Auto-play on jump
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
        },
    });
};

onMounted(() => {
    nextTick(() => {
        if (player1.value) {
            player1.value.addEventListener('timeupdate', handleTimeUpdate);
            
            // Use canplaythrough — means enough data buffered for uninterrupted playback
            player1.value.addEventListener('canplaythrough', () => {
                duration.value = player1.value.duration;
                p1Loaded.value = true;
            });
            player1.value.addEventListener('loadedmetadata', () => {
                duration.value = player1.value.duration;
            });
            player1.value.addEventListener('ended', handleEnded);
            
            // Buffering events
            player1.value.addEventListener('waiting', () => handleBufferStart(1));
            player1.value.addEventListener('stalled', () => handleBufferStart(1));
            player1.value.addEventListener('playing', () => { p1Buffering.value = false; });
            player1.value.addEventListener('canplay', () => { p1Buffering.value = false; });

            // If already loaded (cached)
            if (player1.value.readyState >= 4) {
                duration.value = player1.value.duration;
                p1Loaded.value = true;
            }
        }
        if (player2.value) {
            player2.value.addEventListener('canplaythrough', () => {
                p2Loaded.value = true;
            });
            player2.value.addEventListener('ended', handleEnded);
            
            // Buffering events
            player2.value.addEventListener('waiting', () => handleBufferStart(2));
            player2.value.addEventListener('stalled', () => handleBufferStart(2));
            player2.value.addEventListener('playing', () => { p2Buffering.value = false; });
            player2.value.addEventListener('canplay', () => { p2Buffering.value = false; });

            if (player2.value.readyState >= 4) {
                p2Loaded.value = true;
            }
        }
    });
});

onUnmounted(() => {
    stopSyncCorrection();
    if (desyncTimeout) clearTimeout(desyncTimeout);
    // Event listeners are cleaned up when DOM is destroyed
});

const selectedLightboxImage = ref(null);
</script>

<template>
    <Head :title="`Compare: ${project.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center border-b border-white/5 pb-6">
                <div>
                    <h2 class="text-3xl font-editorial tracking-tight text-gray-100">Version Comparison</h2>
                    <p class="text-xs text-gray-400 mt-1.5 font-mono-technical uppercase tracking-wider">{{ project.name }}</p>
                </div>
                <Link :href="is_client ? route('client.projects.show', project.share_token) : route('projects.show', project.id)" class="btn-secondary text-xs flex items-center gap-1">
                    ← Return to Project
                </Link>
            </div>
        </template>

        <!-- Desync Toast Notification -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="translate-y-[-20px] opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-[-20px] opacity-0"
        >
            <div v-if="desyncNotification" class="fixed top-6 left-1/2 -translate-x-1/2 z-[200] max-w-lg">
                <div class="flex items-center gap-3 px-5 py-3 rounded-lg bg-amber-500/15 border border-amber-500/30 backdrop-blur-xl shadow-2xl">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <span class="text-sm text-amber-100 font-medium">{{ desyncNotification }}</span>
                    <button @click="desyncNotification = null" class="ml-2 text-amber-400/60 hover:text-amber-300 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Initial Loading Gate — shown until BOTH videos are ready -->
                <div v-if="!bothLoaded" class="flex flex-col items-center justify-center py-24 space-y-6">
                    <div class="relative">
                        <div class="w-16 h-16 rounded-full border-4 border-white/10 border-t-accent animate-spin"></div>
                    </div>
                    <div class="text-center space-y-2">
                        <h3 class="text-lg font-editorial text-gray-200">Preparing Synchronized Playback</h3>
                        <p class="text-xs text-gray-500 font-mono-technical uppercase tracking-wider">Loading both video versions before comparison…</p>
                    </div>
                    <div class="flex items-center gap-6 mt-4">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full transition-colors duration-300" :class="p1Loaded ? 'bg-green-400' : 'bg-white/20 animate-pulse'"></div>
                            <span class="text-xs font-mono-technical" :class="p1Loaded ? 'text-green-400' : 'text-gray-500'">
                                v{{ draft1?.version_number }} {{ p1Loaded ? 'Ready' : 'Loading…' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full transition-colors duration-300" :class="p2Loaded ? 'bg-green-400' : 'bg-white/20 animate-pulse'"></div>
                            <span class="text-xs font-mono-technical" :class="p2Loaded ? 'text-green-400' : 'text-gray-500'">
                                v{{ draft2?.version_number }} {{ p2Loaded ? 'Ready' : 'Loading…' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main Compare UI — shown once both loaded -->
                <div v-show="bothLoaded" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    
                    <!-- Left: Sync Video Players & Control Panel -->
                    <div class="lg:col-span-3 space-y-6">
                        
                        <!-- Dual Player Panel -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Left Player: Draft 1 -->
                            <div class="glass-card overflow-hidden rounded-none border-flat">
                                <div class="p-3 bg-[#1c1b1b] border-b border-white/5 flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs uppercase tracking-wider font-mono-technical text-accent">v{{ draft1?.version_number }}</span>
                                        <div v-if="p1Buffering" class="flex items-center gap-1.5">
                                            <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
                                            <span class="text-[10px] text-amber-400 font-mono-technical">Buffering</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono-technical truncate max-w-[180px]">{{ draft1?.original_filename }}</span>
                                </div>
                                <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer group" @click="togglePlay">
                                    <video 
                                        ref="player1" 
                                        :src="draft1?.video_url" 
                                        class="w-full h-full"
                                        preload="auto"
                                    ></video>
                                    
                                    <!-- Buffering spinner overlay -->
                                    <div v-if="p1Buffering && isPlaying" class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center gap-3 z-10">
                                        <div class="w-10 h-10 rounded-full border-3 border-white/20 border-t-white animate-spin"></div>
                                        <span class="text-[11px] text-white/70 font-mono-technical">Buffering…</span>
                                    </div>

                                    <!-- Play/pause overlay -->
                                    <div v-if="!p1Buffering || !isPlaying" class="absolute inset-0 flex items-center justify-center transition-opacity duration-200"
                                         :class="isPlaying ? 'opacity-0 group-hover:opacity-100' : 'opacity-100'">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center shadow-2xl backdrop-blur-sm transition-transform duration-200 hover:scale-110"
                                             :class="isPlaying ? 'bg-white/20 border border-white/30' : 'bg-white/90'">
                                            <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#131313] ml-1" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Player: Draft 2 -->
                            <div class="glass-card overflow-hidden rounded-none border-flat">
                                <div class="p-3 bg-[#1c1b1b] border-b border-white/5 flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs uppercase tracking-wider font-mono-technical text-accent">v{{ draft2?.version_number }} (Latest)</span>
                                        <div v-if="p2Buffering" class="flex items-center gap-1.5">
                                            <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
                                            <span class="text-[10px] text-amber-400 font-mono-technical">Buffering</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono-technical truncate max-w-[180px]">{{ draft2?.original_filename }}</span>
                                </div>
                                <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer group" @click="togglePlay">
                                    <video 
                                        ref="player2" 
                                        :src="draft2?.video_url" 
                                        class="w-full h-full"
                                        preload="auto"
                                    ></video>
                                    
                                    <!-- Buffering spinner overlay -->
                                    <div v-if="p2Buffering && isPlaying" class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center gap-3 z-10">
                                        <div class="w-10 h-10 rounded-full border-3 border-white/20 border-t-white animate-spin"></div>
                                        <span class="text-[11px] text-white/70 font-mono-technical">Buffering…</span>
                                    </div>

                                    <!-- Play/pause overlay -->
                                    <div v-if="!p2Buffering || !isPlaying" class="absolute inset-0 flex items-center justify-center transition-opacity duration-200"
                                         :class="isPlaying ? 'opacity-0 group-hover:opacity-100' : 'opacity-100'">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center shadow-2xl backdrop-blur-sm transition-transform duration-200 hover:scale-110"
                                             :class="isPlaying ? 'bg-white/20 border border-white/30' : 'bg-white/90'">
                                            <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                                            </svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#131313] ml-1" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Buffering Status Bar (visible during playback when either is buffering) -->
                        <Transition
                            enter-active-class="transition-all duration-200"
                            enter-from-class="opacity-0 -translate-y-2"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition-all duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <div v-if="isBuffering && isPlaying" class="flex items-center gap-3 px-4 py-2.5 rounded-md bg-amber-500/10 border border-amber-500/20">
                                <div class="w-4 h-4 rounded-full border-2 border-amber-400/30 border-t-amber-400 animate-spin shrink-0"></div>
                                <span class="text-xs text-amber-300 font-mono-technical">{{ bufferingLabel }} — Playback paused until ready.</span>
                            </div>
                        </Transition>

                        <!-- Unified Master Control Panel -->
                        <div class="glass-card p-6 bg-[#1a1a1a]/50 space-y-4">
                            <!-- Timeline Slider -->
                            <div class="flex items-center gap-4">
                                <span class="text-xs font-mono-technical text-gray-400 w-12 text-right">{{ formatTime(currentTime) }}</span>
                                <input 
                                    type="range" 
                                    :min="0" 
                                    :max="maxDuration" 
                                    :step="0.1" 
                                    :value="currentTime" 
                                    @input="handleSeekChange"
                                    class="flex-1 accent-accent h-1.5 bg-slate-950 rounded-lg appearance-none cursor-pointer"
                                    :disabled="!bothLoaded"
                                />
                                <span class="text-xs font-mono-technical text-gray-400 w-12">{{ formatTime(maxDuration) }}</span>
                            </div>

                            <!-- Master controls (Play/Pause) -->
                            <div class="flex justify-center items-center gap-6">
                                <button @click="togglePlay" 
                                    :disabled="!bothLoaded"
                                    class="w-12 h-12 flex items-center justify-center rounded-full shadow-lg transition-all duration-200 hover:scale-110 border-2 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:scale-100"
                                    :class="isPlaying ? 'bg-white/10 border-white/20 text-white hover:bg-white/20' : 'bg-accent border-accent text-[#131313] hover:bg-[#d6ff1a]'">
                                    <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-0.5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Comments & Revisions lists for BOTH drafts -->
                    <div class="space-y-4 lg:col-span-1">
                        <div class="glass-card p-4 flex flex-col h-[320px]">
                            <h3 class="font-bold text-xs uppercase tracking-wider font-mono-technical text-gray-400 border-b border-white/5 pb-3 mb-3">Sync Revisions</h3>
                            
                            <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                                <!-- Draft 1 Comments -->
                                <div>
                                    <h4 class="text-[10px] uppercase font-bold text-accent mb-2 tracking-wider font-mono-technical">v{{ draft1?.version_number }} Feedback</h4>
                                    <div v-if="!draft1?.comments || draft1.comments.length === 0" class="text-[10px] text-gray-600 italic pl-2 mb-4 font-mono-technical">No comments.</div>
                                    <div v-else class="space-y-2 mb-4">
                                        <div v-for="c in draft1.comments" :key="c.id" class="p-2.5 bg-[#1a1a1a] border border-white/5 rounded-sm text-xs">
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <span v-if="c.timestamp_seconds !== null" @click="jumpToTime(c.timestamp_seconds)" class="font-mono-technical bg-white/5 border border-white/5 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-sm hover:border-accent hover:text-accent transition-all cursor-pointer">
                                                    {{ formatTime(c.timestamp_seconds) }}
                                                </span>
                                                <span class="font-bold text-gray-400 font-mono-technical">{{ c.author_name }}</span>
                                            </div>
                                            <p class="text-gray-200 leading-relaxed">{{ c.content }}</p>
                                            <div v-if="c.image_url" class="mt-2 text-left">
                                                <img :src="c.image_url" @click="selectedLightboxImage = c.image_url" class="h-10 w-16 object-cover rounded border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all hover:scale-105" alt="Attachment" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div class="border-t border-white/5 my-3"></div>

                                <!-- Draft 2 Comments -->
                                <div>
                                    <h4 class="text-[10px] uppercase font-bold text-accent mb-2 tracking-wider font-mono-technical">v{{ draft2?.version_number }} Feedback</h4>
                                    <div v-if="!draft2?.comments || draft2.comments.length === 0" class="text-[10px] text-gray-600 italic pl-2 font-mono-technical">No comments.</div>
                                    <div v-else class="space-y-2">
                                        <div v-for="c in draft2.comments" :key="c.id" class="p-2.5 bg-[#1a1a1a] border border-white/5 rounded-sm text-xs">
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <span v-if="c.timestamp_seconds !== null" @click="jumpToTime(c.timestamp_seconds)" class="font-mono-technical bg-white/5 border border-white/5 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-sm hover:border-accent hover:text-accent transition-all cursor-pointer">
                                                    {{ formatTime(c.timestamp_seconds) }}
                                                </span>
                                                <span class="font-bold text-gray-400 font-mono-technical">{{ c.author_name }}</span>
                                            </div>
                                            <p class="text-gray-200 leading-relaxed">{{ c.content }}</p>
                                            <div v-if="c.image_url" class="mt-2 text-left">
                                                <img :src="c.image_url" @click="selectedLightboxImage = c.image_url" class="h-10 w-16 object-cover rounded border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all hover:scale-105" alt="Attachment" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Feedback Form (referenced to draft2) -->
                        <div v-if="draft2" class="glass-card p-4">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 mb-2 tracking-wider font-mono-technical">Add Feedback (v{{ draft2.version_number }})</h4>
                            <form @submit.prevent="submitComment" class="space-y-3">
                                <textarea 
                                    v-model="commentForm.content" 
                                    placeholder="Leave feedback at current frame..." 
                                    rows="2" 
                                    class="w-full bg-slate-950 border border-white/10 rounded-sm px-3 py-2 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-accent" 
                                    required
                                ></textarea>
                                <div class="flex justify-between items-center text-[10px] text-gray-400">
                                    <span class="font-mono-technical">Time: <span class="font-bold text-accent">{{ formatTime(currentTime) }}</span></span>
                                    <button type="submit" class="btn-primary py-1 px-3 text-xs">
                                        Submit Comment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Hidden video elements for preloading (rendered even during loading gate) -->
        <!-- Videos are in the v-show="bothLoaded" block but use v-show so DOM stays mounted -->

        <!-- Fullscreen Image Lightbox Modal -->
        <div v-if="selectedLightboxImage" @click="selectedLightboxImage = null" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md cursor-pointer animate-fade-in">
            <div class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </div>
            <img :src="selectedLightboxImage" class="max-w-full max-h-[90vh] object-contain rounded-xl shadow-2xl border border-white/5 cursor-default" @click.stop />
        </div>
    </AuthenticatedLayout>
</template>
