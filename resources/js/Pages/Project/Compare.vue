<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PhotoCompareViewer from '@/Components/PhotoCompareViewer.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
    project: Object,
    draft1: Object,
    draft2: Object,
    is_client: Boolean,
});

const isPhotoProject = computed(() => props.project.media_type === 'photo');

const player1 = ref(null);
const player2 = ref(null);

const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(0);

// Video Compare States
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

const desyncNotification = ref(null);
let desyncTimeout = null;

const showDesyncNotice = (message) => {
    desyncNotification.value = message;
    if (desyncTimeout) clearTimeout(desyncTimeout);
    desyncTimeout = setTimeout(() => {
        desyncNotification.value = null;
    }, 5000);
};

const maxDuration = computed(() => {
    const d1 = props.draft1?.duration || 0;
    const d2 = props.draft2?.duration || 0;
    return Math.max(d1, d2) || duration.value || 10;
});

const handleTimeUpdate = () => {
    if (player1.value) {
        currentTime.value = player1.value.currentTime;
    }
};

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

    if (operation !== syncOperation || !isPlaying.value) return false;

    p1Buffering.value = false;
    p2Buffering.value = false;

    const firstPlay = first.play();
    const secondPlay = second.play();
    await Promise.all([firstPlay, secondPlay]);

    return operation === syncOperation && isPlaying.value;
};

const recoverSynchronization = async (showNotice = false) => {
    if (isRecoveringSync.value || !isPlaying.value || !player1.value || !player2.value) return;

    const operation = ++syncOperation;
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

const togglePlay = () => {
    if (!player1.value || !player2.value) return;
    if (!bothLoaded.value) return;

    if (isPlaying.value) {
        player1.value.pause();
        player2.value.pause();
        isPlaying.value = false;
        stopSyncCorrection();
    } else {
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
        });
    }
};

const handleEnded = () => {
    if (player1.value) player1.value.pause();
    if (player2.value) player2.value.pause();
    isPlaying.value = false;
    stopSyncCorrection();
};

const handleSeekChange = (e) => {
    const targetTime = parseFloat(e.target.value);
    currentTime.value = targetTime;

    if (player1.value) player1.value.currentTime = Math.min(targetTime, player1.value.duration || targetTime);
    if (player2.value) player2.value.currentTime = Math.min(targetTime, player2.value.duration || targetTime);
};

const jumpToTime = (seconds) => {
    if (seconds === null || seconds === undefined) return;
    if (!bothLoaded.value) return;
    
    currentTime.value = seconds;
    if (player1.value) player1.value.currentTime = Math.min(seconds, player1.value.duration || seconds);
    if (player2.value) player2.value.currentTime = Math.min(seconds, player2.value.duration || seconds);

    if (!isPlaying.value) togglePlay();
};

const formatTime = (seconds) => {
    if (seconds === null || seconds === undefined) return '00:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

const commentForm = useForm({
    content: '',
    timestamp_seconds: null,
});

const submitComment = () => {
    if (!props.draft2) return;
    if (player2.value) commentForm.timestamp_seconds = player2.value.currentTime;

    commentForm.post(route('comments.store', props.draft2.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
};

onMounted(() => {
    if (!isPhotoProject.value) {
        nextTick(() => {
            if (player1.value) {
                player1.value.addEventListener('timeupdate', handleTimeUpdate);
                player1.value.addEventListener('canplaythrough', () => {
                    duration.value = player1.value.duration;
                    p1Loaded.value = true;
                });
                player1.value.addEventListener('loadedmetadata', () => { duration.value = player1.value.duration; });
                player1.value.addEventListener('ended', handleEnded);
                player1.value.addEventListener('waiting', () => handleBufferStart(1));
                player1.value.addEventListener('playing', () => { p1Buffering.value = false; });
                if (player1.value.readyState >= 4) {
                    duration.value = player1.value.duration;
                    p1Loaded.value = true;
                }
            }
            if (player2.value) {
                player2.value.addEventListener('canplaythrough', () => { p2Loaded.value = true; });
                player2.value.addEventListener('ended', handleEnded);
                player2.value.addEventListener('waiting', () => handleBufferStart(2));
                player2.value.addEventListener('playing', () => { p2Buffering.value = false; });
                if (player2.value.readyState >= 4) p2Loaded.value = true;
            }
        });
    }
});

onUnmounted(() => {
    stopSyncCorrection();
    if (desyncTimeout) clearTimeout(desyncTimeout);
});

const selectedLightboxImage = ref(null);
</script>

<template>
    <Head :title="`Compare: ${project.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center border-b border-white/5 pb-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">{{ isPhotoProject ? '🖼️' : '🎬' }}</span>
                        <h2 class="text-3xl font-editorial tracking-tight text-gray-100">
                            {{ isPhotoProject ? 'Photo Version Comparison' : 'Video Version Comparison' }}
                        </h2>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5 font-mono-technical uppercase tracking-wider">{{ project.name }}</p>
                </div>
                <Link :href="is_client ? route('client.projects.show', project.share_token) : route('projects.show', project.id)" class="btn-secondary text-xs flex items-center gap-1">
                    ← Return to Project
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- 1. PHOTO PROJECT COMPARISON VIEWER -->
                <template v-if="isPhotoProject">
                    <PhotoCompareViewer :draft1="draft1" :draft2="draft2" />

                    <!-- Checklist Comparison Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4">
                        <div class="glass-card p-4">
                            <h4 class="text-xs uppercase font-bold text-accent mb-3 tracking-wider font-mono-technical">v{{ draft1?.version_number }} Feedback Logs</h4>
                            <div v-if="!draft1?.comments || draft1.comments.length === 0" class="text-xs text-gray-500 font-mono-technical italic">No comments on this version.</div>
                            <div v-else class="space-y-2">
                                <div v-for="c in draft1.comments" :key="c.id" class="p-3 bg-[#1a1a1a] border border-white/5 rounded-sm text-xs">
                                    <span class="font-bold text-gray-300 font-mono-technical">{{ c.author_name }}:</span> {{ c.content }}
                                </div>
                            </div>
                        </div>

                        <div class="glass-card p-4">
                            <h4 class="text-xs uppercase font-bold text-accent mb-3 tracking-wider font-mono-technical">v{{ draft2?.version_number }} Feedback Logs</h4>
                            <div v-if="!draft2?.comments || draft2.comments.length === 0" class="text-xs text-gray-500 font-mono-technical italic">No comments on this version.</div>
                            <div v-else class="space-y-2">
                                <div v-for="c in draft2.comments" :key="c.id" class="p-3 bg-[#1a1a1a] border border-white/5 rounded-sm text-xs">
                                    <span class="font-bold text-gray-300 font-mono-technical">{{ c.author_name }}:</span> {{ c.content }}
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- 2. VIDEO PROJECT COMPARISON VIEWER -->
                <template v-else>
                    <!-- Initial Loading Gate -->
                    <div v-if="!bothLoaded" class="flex flex-col items-center justify-center py-24 space-y-6">
                        <div class="w-16 h-16 rounded-full border-4 border-white/10 border-t-accent animate-spin"></div>
                        <div class="text-center space-y-2">
                            <h3 class="text-lg font-editorial text-gray-200">Preparing Synchronized Playback</h3>
                            <p class="text-xs text-gray-500 font-mono-technical uppercase tracking-wider">Loading both video versions before comparison…</p>
                        </div>
                    </div>

                    <!-- Main Video Compare UI -->
                    <div v-show="bothLoaded" class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        <div class="lg:col-span-3 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="glass-card overflow-hidden rounded-none border-flat">
                                    <div class="p-3 bg-[#1c1b1b] border-b border-white/5 flex justify-between items-center">
                                        <span class="font-bold text-xs uppercase tracking-wider font-mono-technical text-accent">v{{ draft1?.version_number }}</span>
                                    </div>
                                    <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer" @click="togglePlay">
                                        <video ref="player1" :src="draft1?.video_url" class="w-full h-full" preload="auto"></video>
                                    </div>
                                </div>

                                <div class="glass-card overflow-hidden rounded-none border-flat">
                                    <div class="p-3 bg-[#1c1b1b] border-b border-white/5 flex justify-between items-center">
                                        <span class="font-bold text-xs uppercase tracking-wider font-mono-technical text-accent">v{{ draft2?.version_number }} (Latest)</span>
                                    </div>
                                    <div class="bg-black aspect-video flex items-center justify-center relative cursor-pointer" @click="togglePlay">
                                        <video ref="player2" :src="draft2?.video_url" class="w-full h-full" preload="auto"></video>
                                    </div>
                                </div>
                            </div>

                            <!-- Controls Panel -->
                            <div class="glass-card p-6 bg-[#1a1a1a]/50 space-y-4">
                                <div class="flex items-center gap-4">
                                    <span class="text-xs font-mono-technical text-gray-400 w-12 text-right">{{ formatTime(currentTime) }}</span>
                                    <input type="range" :min="0" :max="maxDuration" :step="0.1" :value="currentTime" @input="handleSeekChange" class="flex-1 accent-accent h-1.5 bg-slate-950 rounded-lg appearance-none cursor-pointer" />
                                    <span class="text-xs font-mono-technical text-gray-400 w-12">{{ formatTime(maxDuration) }}</span>
                                </div>

                                <div class="flex justify-center items-center gap-6">
                                    <button @click="togglePlay" class="w-12 h-12 flex items-center justify-center rounded-full shadow-lg border-2 bg-accent border-accent text-[#131313]">
                                        <svg v-if="isPlaying" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 9v6m4-6v6" /></svg>
                                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Comments -->
                        <div class="space-y-4 lg:col-span-1">
                            <div class="glass-card p-4 flex flex-col h-[320px]">
                                <h3 class="font-bold text-xs uppercase tracking-wider font-mono-technical text-gray-400 border-b border-white/5 pb-3 mb-3">Sync Revisions</h3>
                                <div class="flex-1 overflow-y-auto space-y-4 pr-1">
                                    <div v-for="c in (draft2?.comments || [])" :key="c.id" class="p-2.5 bg-[#1a1a1a] border border-white/5 rounded-sm text-xs">
                                        <span class="font-bold text-gray-400 font-mono-technical">{{ c.author_name }}:</span> {{ c.content }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
