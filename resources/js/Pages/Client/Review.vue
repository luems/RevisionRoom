<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';

const props = defineProps({
    project: Object,
    auth_user: Object,
});

const currentDraftIndex = ref(0);
const videoPlayer = ref(null);
const commentTime = ref(null);
const includeTimestamp = ref(true);
const showApprovalModal = ref(false);

const activeDraft = computed(() => {
    if (!props.project.drafts || props.project.drafts.length === 0) return null;
    return props.project.drafts[currentDraftIndex.value];
});

// Auto-polling for processing drafts
let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({
            only: ['project'],
            onSuccess: () => {
                if (activeDraft.value && activeDraft.value.status !== 'processing') {
                    stopPolling();
                }
            }
        });
    }, 3000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

watch(activeDraft, (newDraft) => {
    if (newDraft && newDraft.status === 'processing') {
        startPolling();
    } else {
        stopPolling();
    }
}, { immediate: true });

const isVertical = ref(false);

const onVideoLoaded = (e) => {
    const video = e.target;
    isVertical.value = video.videoHeight > video.videoWidth;
    console.log(`[VideoLoaded] Dimensions: ${video.videoWidth}x${video.videoHeight}, isVertical: ${isVertical.value}`);
};

watch(currentDraftIndex, () => {
    isVertical.value = false;
});

onUnmounted(() => {
    stopPolling();
});

// Format time MM:SS
const formatTime = (seconds) => {
    if (seconds === null || seconds === undefined) return '';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

// Handle comment input focus: pause video and capture time
const handleCommentFocus = () => {
    if (videoPlayer.value && !videoPlayer.value.paused) {
        videoPlayer.value.pause();
    }
    if (videoPlayer.value && commentTime.value === null) {
        commentTime.value = videoPlayer.value.currentTime;
    }
};

const clearCommentTime = () => {
    commentTime.value = null;
};

// Comment Form
const commentForm = useForm({
    content: '',
    timestamp_seconds: null,
});

const submitComment = () => {
    if (includeTimestamp.value && commentTime.value !== null) {
        commentForm.timestamp_seconds = commentTime.value;
    } else {
        commentForm.timestamp_seconds = null;
    }

    commentForm.post(route('comments.store', activeDraft.value.id), {
        preserveScroll: true,
        onStart: () => {
            console.log('[ClientPortal] Submitting feedback comment...', commentForm.data());
        },
        onSuccess: () => {
            console.log('[ClientPortal] Comment submitted successfully!');
            commentForm.reset();
            commentTime.value = null;
        },
        onError: (errors) => {
            console.error('[ClientPortal] Failed to submit comment. Errors:', errors);
        },
    });
};

// Approval Form
const approvalForm = useForm({
    approver_name: props.auth_user?.name || '',
    remarks: '',
});

const submitApproval = () => {
    approvalForm.post(route('approvals.store', activeDraft.value.id), {
        onStart: () => {
            console.log('[ClientPortal] Submitting project approval sign-off...', approvalForm.data());
        },
        onSuccess: () => {
            console.log('[ClientPortal] Project approved successfully!');
            showApprovalModal.value = false;
            router.reload();
        },
        onError: (errors) => {
            console.error('[ClientPortal] Draft approval failed. Errors:', errors);
        },
    });
};

// Jump video to time
const seekTo = (seconds) => {
    if (videoPlayer.value && seconds !== null) {
        videoPlayer.value.currentTime = seconds;
        videoPlayer.value.play();
    }
};

const cancelApproval = () => {
    if (confirm('Are you sure you want to cancel your approval? This will return the project to review mode.')) {
        router.delete(route('approvals.cancel', props.project.id), {
            onSuccess: () => {
                console.log('[ClientPortal] Approval cancelled successfully!');
                router.reload();
            }
        });
    }
};

const showGuide = ref(false);
</script>

<template>
    <Head :title="`Review: ${project.name}`" />

    <div class="min-h-screen bg-[#0b0f19] text-gray-100 flex flex-col justify-between">
        <!-- Header -->
        <header class="border-b border-white/5 bg-slate-900/50 backdrop-blur-md px-6 py-4 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h1 class="text-xl font-black text-indigo-400 tracking-wider">REVISIONROOM</h1>
                    <div class="h-4 w-[1px] bg-white/10 hidden md:block"></div>
                    <span class="text-sm font-semibold text-gray-200 hidden md:block">{{ project.name }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <span :class="`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
                        project.status === 'approved' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : project.status === 'archived' ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'
                    }`">
                        {{ project.status }}
                    </span>

                    <a v-if="project.status === 'approved'" :href="route('projects.download-record', project.id)" target="_blank" class="btn-primary py-1.5 px-4 text-xs flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Approval PDF
                    </a>
                    
                    <button v-if="project.status === 'approved'" @click="cancelApproval" class="btn-secondary py-1.5 px-4 text-xs border-rose-500/20 text-rose-300 hover:bg-rose-500/10 transition-colors">
                        Cancel Approval
                    </button>
                    
                    <button v-else-if="activeDraft && activeDraft.status === 'ready' && project.status !== 'archived'" @click="showApprovalModal = true" class="btn-primary py-1.5 px-4 text-xs bg-gradient-to-r from-emerald-500 to-teal-600 shadow-emerald-500/30">
                        Approve Draft
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main :class="isVertical ? 'flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-2 gap-6' : 'flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6'">
            <!-- Left: Video & Selector -->
            <div :class="isVertical ? 'lg:col-span-1 space-y-6' : 'lg:col-span-2 space-y-6'">
                <!-- Video Box -->
                <div class="glass-card overflow-hidden">
                    <div v-if="activeDraft" class="bg-black max-h-[65vh] flex items-center justify-center relative aspect-auto">
                        <video v-if="activeDraft.status === 'ready'" ref="videoPlayer" :src="activeDraft.video_url" @loadedmetadata="onVideoLoaded" controls class="max-w-full max-h-[65vh] object-contain"></video>
                        <div v-else-if="activeDraft.status === 'processing'" class="text-center p-8 text-gray-400">
                            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-indigo-500 mx-auto mb-4"></div>
                            <h4 class="font-semibold text-lg text-gray-200">Processing v{{ activeDraft.version_number }}...</h4>
                            <p class="text-sm">Transcoding and extracting thumbnails in background. Please wait.</p>
                        </div>
                        <div v-else class="text-center text-red-400 p-8">
                            <h4 class="font-semibold">Failed to process video draft</h4>
                        </div>
                    </div>
                    <div v-else class="bg-slate-900 aspect-video flex items-center justify-center p-8 text-gray-500">
                        No draft version uploaded yet.
                    </div>

                    <div v-if="activeDraft" class="p-6 bg-slate-900/50 border-t border-white/5 flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <span class="text-xs uppercase font-bold text-indigo-400">Reviewing Version</span>
                            <h4 class="text-lg font-bold text-gray-200">Draft v{{ activeDraft.version_number }}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400">Version History:</span>
                            <select v-model="currentDraftIndex" class="bg-slate-950 border border-white/10 rounded-lg text-xs py-1.5 px-3 text-white focus:outline-none focus:border-indigo-500">
                                <option v-for="(draft, idx) in project.drafts" :key="draft.id" :value="idx">
                                    v{{ draft.version_number }} ({{ draft.status }})
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Comment Input Area -->
                <div v-if="activeDraft && activeDraft.status === 'ready' && project.status !== 'approved'" class="glass-card p-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-bold text-lg">Add Feedback</h3>
                        <button @click="showGuide = !showGuide" type="button" class="text-gray-400 hover:text-indigo-400 transition-colors p-1.5 rounded-full border border-white/10 hover:border-indigo-500/20 flex items-center justify-center h-7 w-7" title="Guide on how to comment">
                            <span class="text-sm font-bold">?</span>
                        </button>
                    </div>

                    <div v-if="showGuide" class="p-4 bg-indigo-500/5 border border-indigo-500/10 rounded-xl mb-4 text-xs text-indigo-300 leading-relaxed animate-fade-in space-y-1.5">
                        <p class="font-bold text-indigo-200">💡 Quick Guide: How to Comment</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Play the video to the moment you want to give feedback.</li>
                            <li>Start typing in the comment box—the video will **automatically pause** and lock in the current time.</li>
                            <li>Ensure the checkbox is checked to attach the timestamp (uncheck it for general feedback).</li>
                            <li>Click **Submit Comment** to post it to the checklist.</li>
                        </ol>
                    </div>

                    <form @submit.prevent="submitComment" class="space-y-4">
                        <textarea v-model="commentForm.content" @focus="handleCommentFocus" placeholder="Type here... (Video pauses automatically to let you type)" rows="3" class="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500" required></textarea>

                        <div class="flex justify-between items-center flex-wrap gap-3">
                            <!-- Timestamp Checkbox -->
                            <div v-if="commentTime !== null" class="flex items-center gap-2 text-xs bg-indigo-500/10 px-3 py-1.5 rounded-lg border border-indigo-500/20">
                                <input type="checkbox" v-model="includeTimestamp" id="time-chk" class="rounded text-indigo-600 bg-slate-900 border-white/10" />
                                <label for="time-chk" class="text-indigo-300 font-medium cursor-pointer">
                                    Attach current time: <span class="font-mono">{{ formatTime(commentTime) }}</span>
                                </label>
                                <button type="button" @click="clearCommentTime" class="text-gray-400 hover:text-white ml-1">×</button>
                            </div>
                            <div v-else class="text-xs text-gray-500 italic">
                                Start typing to capture current video time.
                            </div>

                            <button type="submit" class="btn-primary py-2 px-6" :disabled="commentForm.processing">
                                Submit Comment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Revision Checklist -->
            <div class="space-y-6">
                <div class="glass-card p-6 flex flex-col h-[600px]">
                    <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
                        <h3 class="font-bold text-lg">Revision Checklist</h3>
                        <span v-if="activeDraft" class="bg-indigo-500/10 text-indigo-400 text-xs px-2.5 py-0.5 rounded-full font-semibold">
                            v{{ activeDraft.version_number }}
                        </span>
                    </div>

                    <div v-if="!activeDraft" class="text-center text-gray-500 my-auto">
                        No draft version uploaded yet.
                    </div>
                    
                    <div v-else-if="activeDraft.comments.length === 0" class="text-center text-gray-500 my-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 opacity-35" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <p class="text-sm">No feedback left yet.</p>
                        <p class="text-xs text-gray-400 mt-1">Leave a timestamped comment to request changes.</p>
                    </div>

                    <!-- Checklist list -->
                    <div v-else class="flex-1 overflow-y-auto space-y-4 pr-2">
                        <div v-for="comment in activeDraft.comments" :key="comment.id" :class="`p-4 rounded-xl border transition-all ${
                            comment.is_resolved ? 'bg-emerald-950/10 border-emerald-500/10 opacity-70' : comment.is_rejected ? 'bg-rose-950/10 border-rose-500/10' : 'bg-slate-900/60 border-white/5'
                        }`">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span v-if="comment.timestamp_seconds !== null" @click="seekTo(comment.timestamp_seconds)" class="bg-indigo-500/20 text-indigo-300 font-mono text-xs px-2 py-0.5 rounded cursor-pointer hover:bg-indigo-500 hover:text-white transition-all">
                                            {{ formatTime(comment.timestamp_seconds) }}
                                        </span>
                                        <span class="text-xs text-gray-400 font-bold">{{ comment.author_name }}</span>
                                    </div>
                                    <p class="text-sm text-gray-200 leading-relaxed">{{ comment.content }}</p>
                                    
                                    <!-- Decline reason banner -->
                                    <div v-if="comment.is_rejected" class="mt-2 p-2 bg-rose-500/5 border border-rose-500/10 rounded-lg text-xs text-rose-300 flex items-start gap-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-rose-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        <span><span class="font-bold text-rose-200">Editor:</span> {{ comment.rejection_reason || 'No reason provided.' }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0 font-semibold text-xs">
                                    <span v-if="comment.is_resolved" class="text-emerald-400 flex items-center gap-1">
                                        ✓ Resolved
                                    </span>
                                    <span v-else-if="comment.is_rejected" class="text-rose-400 flex items-center gap-1">
                                        🚫 Declined
                                    </span>
                                    <span v-else class="text-amber-500 flex items-center gap-1">
                                        ○ Open
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 py-4 px-6 text-center text-xs text-gray-500">
            Powered by RevisionRoom © 2026.
        </footer>

        <!-- Approval Modal -->
        <div v-if="showApprovalModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-8 relative">
                <button @click="showApprovalModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-2xl font-bold mb-6 text-gray-100">Confirm Draft Approval</h3>
                <p class="text-sm text-gray-400 mb-4">By approving this draft, you indicate that the project is complete and ready for final delivery. The editor will be notified.</p>

                <form @submit.prevent="submitApproval" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Your Name</label>
                        <input type="text" v-model="approvalForm.approver_name" class="w-full bg-slate-900/50 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-indigo-500" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Remarks / Feedback (Optional)</label>
                        <textarea v-model="approvalForm.remarks" rows="3" class="w-full bg-slate-900/50 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-indigo-500" placeholder="e.g. Great work! Love the colors."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showApprovalModal = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary bg-gradient-to-r from-emerald-500 to-teal-600" :disabled="approvalForm.processing">
                            Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
