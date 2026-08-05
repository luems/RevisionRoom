<script setup>
import PhotoCanvas from '@/Components/PhotoCanvas.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';

const props = defineProps({
    project: Object,
    auth_user: Object,
});

const currentDraftIndex = ref(0);
const selectedItemIndex = ref(0);
const selectedDraft1 = ref('');
const selectedDraft2 = ref('');
const videoPlayer = ref(null);
const photoCanvasRef = ref(null);
const commentTime = ref(null);
const includeTimestamp = ref(true);
const showApprovalModal = ref(false);
const selectedCommentId = ref(null);
const selectedLightboxImage = ref(null);
const pendingPin = ref(null);

const isPhotoProject = computed(() => props.project.media_type === 'photo');

const activeDraft = computed(() => {
    if (!props.project.drafts || props.project.drafts.length === 0) return null;
    return props.project.drafts[currentDraftIndex.value];
});

const activePhotoItem = computed(() => {
    if (!activeDraft.value || !isPhotoProject.value) return null;
    const items = activeDraft.value.items || [];
    return items[selectedItemIndex.value] || items[0] || null;
});

const activeComments = computed(() => {
    if (!activeDraft.value) return [];
    if (!isPhotoProject.value) return activeDraft.value.comments || [];
    if (!activePhotoItem.value) return activeDraft.value.comments || [];
    return (activeDraft.value.comments || []).filter(c => !c.draft_item_id || c.draft_item_id === activePhotoItem.value.id);
});

const isSubmitted = computed(() => {
    return !!props.project.changes_submitted_at;
});

// Live polling for comments and project updates
let pollInterval = null;

const startPolling = () => {
    if (pollInterval) return;
    pollInterval = setInterval(() => {
        router.reload({
            only: ['project'],
            preserveScroll: true,
            preserveState: true,
        });
    }, 4000);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onMounted(() => {
    startPolling();
    if (props.project.drafts && props.project.drafts.length >= 2) {
        selectedDraft1.value = props.project.drafts[props.project.drafts.length - 1].id;
        selectedDraft2.value = props.project.drafts[0].id;
    }
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

// Video playback handlers for accurate comment timestamping
const onVideoSeeked = () => {
    if (videoPlayer.value) {
        commentTime.value = videoPlayer.value.currentTime;
    }
};

const onVideoPaused = () => {
    if (videoPlayer.value && commentTime.value === null) {
        commentTime.value = videoPlayer.value.currentTime;
    }
};

// Handle comment input focus: pause video and capture time
const handleCommentFocus = () => {
    if (videoPlayer.value) {
        if (!videoPlayer.value.paused) {
            videoPlayer.value.pause();
        }
        commentTime.value = videoPlayer.value.currentTime;
    }
};

const clearCommentTime = () => {
    commentTime.value = null;
};

// Handle Pin Dropped on Photo Canvas
const handleAddPin = (pin) => {
    pendingPin.value = pin;
};

const cancelPin = () => {
    pendingPin.value = null;
    if (photoCanvasRef.value) {
        photoCanvasRef.value.cancelTempPin();
    }
};

// Image Upload & Lightbox state
const fileInput = ref(null);
const imagePreviewUrl = ref(null);

const handleImageSelect = (e) => {
    const file = e.target.files[0];
    commentForm.image = file;
    if (file) {
        imagePreviewUrl.value = URL.createObjectURL(file);
    } else {
        imagePreviewUrl.value = null;
    }
};

const handlePaste = (e) => {
    const items = e.clipboardData?.items;
    if (!items) return;

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();
            if (file) {
                commentForm.image = file;
                imagePreviewUrl.value = URL.createObjectURL(file);
                break;
            }
        }
    }
};

const removeSelectedImage = () => {
    commentForm.image = null;
    imagePreviewUrl.value = null;
    if (fileInput.value) fileInput.value.value = '';
};

// Comment Form
const commentForm = useForm({
    content: '',
    timestamp_seconds: null,
    position_x: null,
    position_y: null,
    draft_item_id: null,
    image: null,
});

const submitComment = () => {
    if (isPhotoProject.value) {
        if (pendingPin.value) {
            commentForm.position_x = pendingPin.value.x;
            commentForm.position_y = pendingPin.value.y;
            commentForm.draft_item_id = activePhotoItem.value ? activePhotoItem.value.id : null;
        } else {
            commentForm.position_x = null;
            commentForm.position_y = null;
            commentForm.draft_item_id = activePhotoItem.value ? activePhotoItem.value.id : null;
        }
        commentForm.timestamp_seconds = null;
    } else {
        if (includeTimestamp.value && commentTime.value !== null) {
            commentForm.timestamp_seconds = commentTime.value;
        } else {
            commentForm.timestamp_seconds = null;
        }
        commentForm.position_x = null;
        commentForm.position_y = null;
        commentForm.draft_item_id = null;
    }

    commentForm.post(route('comments.store', activeDraft.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            commentForm.reset();
            commentTime.value = null;
            pendingPin.value = null;
            imagePreviewUrl.value = null;
            if (photoCanvasRef.value) photoCanvasRef.value.cancelTempPin();
            if (fileInput.value) fileInput.value.value = '';
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
        onSuccess: () => {
            showApprovalModal.value = false;
            router.reload();
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
    if (confirm('Are you sure you want to cancel your approval?')) {
        router.delete(route('approvals.cancel', props.project.id), {
            onSuccess: () => router.reload()
        });
    }
};

const deleteComment = (commentId) => {
    if (confirm('Are you sure you want to delete this comment?')) {
        router.delete(route('comments.destroy', commentId), { preserveScroll: true });
    }
};

const markingChanges = ref(false);
const markedSuccess = ref(false);

const markCurrentChanges = () => {
    markingChanges.value = true;
    router.post(route('projects.mark-changes', props.project.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            markingChanges.value = false;
            markedSuccess.value = true;
            setTimeout(() => markedSuccess.value = false, 4000);
        },
        onError: () => markingChanges.value = false
    });
};

const showGuide = ref(false);
</script>

<template>
    <Head :title="`Review: ${project.name}`" />

    <div class="min-h-screen bg-[#131313] text-gray-100 flex flex-col justify-between">
        <!-- Header -->
        <header class="border-b border-white/5 bg-[#1c1b1b]/80 backdrop-blur-md px-6 py-4 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <img src="/RevisionRoomLogo.png" alt="RevisionRoom" class="h-8 w-auto" />
                    <div class="h-4 w-[1px] bg-white/10 hidden md:block"></div>
                    <div class="flex items-center gap-2">
                        <svg v-if="isPhotoProject" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        <span class="text-xs font-mono-technical uppercase tracking-wider text-gray-400 font-bold hidden md:block">{{ project.name }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="bg-[#1c1b1b] border border-white/5 px-2.5 py-1 rounded-sm text-[10px] font-mono-technical font-semibold uppercase tracking-wider flex items-center gap-1.5 text-gray-200">
                        <span :class="`w-1.5 h-1.5 rounded-full ${
                            project.status === 'approved' ? 'bg-[#10b981]' : project.status === 'archived' ? 'bg-[#6366f1]' : 'bg-[#f59e0b]'
                        }`"></span>
                        {{ project.status }}
                    </span>

                    <a v-if="project.status === 'approved' || project.status === 'archived'" :href="route('projects.download-record', project.id)" target="_blank" class="btn-secondary py-1.5 px-4 text-xs flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        Approval PDF
                    </a>
                    
                    <button v-if="project.status === 'approved'" @click="cancelApproval" class="btn-secondary py-1.5 px-4 text-xs border-rose-500/20 text-rose-300 hover:bg-rose-500/10">
                        Cancel Approval
                    </button>
                    
                    <button v-else-if="activeDraft && project.status !== 'archived'" @click="showApprovalModal = true" class="btn-primary py-1.5 px-4 text-xs">
                        Approve Project
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Photo Canvas or Video Player -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Media Canvas Display Box -->
                <div class="glass-card overflow-hidden p-4 space-y-4">
                    <!-- Photo Canvas (If Photo Project) -->
                    <template v-if="isPhotoProject">
                        <div v-if="activePhotoItem" class="space-y-4">
                            <PhotoCanvas 
                                ref="photoCanvasRef"
                                :photo-url="activePhotoItem.file_url"
                                :comments="activeComments"
                                :selected-comment-id="selectedCommentId"
                                @add-pin="handleAddPin"
                                @select-comment="comment => selectedCommentId = comment.id"
                                :readonly="project.status === 'approved' || project.status === 'archived'"
                            />

                            <!-- Multi-Photo Thumbnail Navigation Strip -->
                            <div v-if="activeDraft && activeDraft.items && activeDraft.items.length > 1" class="flex items-center gap-2 overflow-x-auto pb-2 pt-2 border-t border-white/5">
                                <span class="text-xs text-gray-400 font-mono-technical font-bold uppercase shrink-0">Select Photo:</span>
                                <button v-for="(item, idx) in activeDraft.items"
                                        :key="item.id"
                                        @click="selectedItemIndex = idx"
                                        :class="`relative rounded-lg overflow-hidden border-2 transition-all shrink-0 ${
                                            selectedItemIndex === idx ? 'border-accent scale-105 shadow-lg' : 'border-white/10 opacity-60 hover:opacity-100'
                                        }`">
                                    <img :src="item.thumbnail_url" class="w-16 h-12 object-cover" :alt="item.original_filename" />
                                    <span class="absolute bottom-0 right-0 bg-black/80 text-white font-mono-technical text-[9px] px-1 font-bold">#{{ idx + 1 }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-else class="bg-[#1c1b1b] aspect-video flex flex-col items-center justify-center p-8 text-gray-500 rounded-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <h4 class="font-editorial text-lg font-bold text-gray-300">No photo draft uploaded yet</h4>
                        </div>
                    </template>

                    <!-- Video Player (If Video Project) -->
                    <template v-else>
                        <div v-if="activeDraft" class="bg-black max-h-[65vh] flex items-center justify-center relative aspect-auto">
                            <video v-if="activeDraft.status === 'ready'" ref="videoPlayer" :src="activeDraft.video_url" @seeked="onVideoSeeked" @pause="onVideoPaused" controls class="max-w-full max-h-[65vh] object-contain"></video>
                            <div v-else class="text-center p-8 text-gray-400">Processing video draft...</div>
                        </div>
                        <div v-else class="bg-slate-900 aspect-video flex items-center justify-center p-8 text-gray-500">
                            No draft version uploaded yet.
                        </div>
                    </template>

                    <!-- Version Switcher Bar -->
                    <div v-if="activeDraft" class="p-4 bg-[#1a1a1a]/50 border-t border-white/5 flex justify-between items-center flex-wrap gap-4">
                        <div>
                            <span class="text-xs uppercase font-semibold text-accent font-mono-technical">Reviewing Version</span>
                            <h4 class="text-base font-editorial font-bold text-gray-200">Draft Version v{{ activeDraft.version_number }}</h4>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-white/40 font-mono-technical uppercase tracking-widest font-semibold">Versions</span>
                            <div class="flex gap-1">
                                <button 
                                    v-for="(draft, idx) in project.drafts" 
                                    :key="draft.id" 
                                    @click="currentDraftIndex = idx" 
                                    :class="`px-2.5 py-0.5 text-[11px] font-mono-technical font-bold rounded-full transition-all ${
                                        currentDraftIndex === idx 
                                            ? 'text-accent border border-accent/50 bg-transparent' 
                                            : 'text-white/40 border border-transparent hover:text-white/70 hover:border-white/20'
                                    }`"
                                >
                                    v{{ draft.version_number }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comment Input Area -->
                <div v-if="activeDraft && project.status !== 'approved' && project.status !== 'archived'" class="glass-card p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <h3 class="font-bold text-sm uppercase tracking-wider font-mono-technical text-gray-300 flex items-center gap-2">
                            <svg v-if="pendingPin" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-accent shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            {{ pendingPin ? 'Adding Pinned Comment' : 'Add Feedback' }}
                        </h3>
                        <button @click="showGuide = !showGuide" type="button" class="text-gray-400 hover:text-accent p-1 border border-white/10 rounded-full h-7 w-7 flex items-center justify-center font-bold text-xs">?</button>
                    </div>

                    <!-- Pending Pin Tag Banner -->
                    <div v-if="pendingPin" class="p-3 bg-accent/10 border border-accent/30 rounded-xl text-xs text-accent flex justify-between items-center font-mono-technical">
                        <span class="flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            Pin placed at coordinates: ({{ pendingPin.x }}%, {{ pendingPin.y }}%)
                        </span>
                        <button type="button" @click="cancelPin" class="text-rose-400 hover:text-rose-300 font-bold underline">Cancel Pin</button>
                    </div>

                    <form @submit.prevent="submitComment" class="space-y-4">
                        <textarea 
                            v-model="commentForm.content" 
                            @focus="handleCommentFocus" 
                            @paste="handlePaste" 
                            :placeholder="isPhotoProject ? 'Type feedback here or paste screenshot (Ctrl+V)...' : 'Type feedback here...'" 
                            rows="3" 
                            class="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-accent" 
                            required
                        ></textarea>

                        <!-- Attach Image -->
                        <div class="flex items-center gap-4 flex-wrap">
                            <label class="btn-secondary py-1.5 px-4 text-xs flex items-center gap-2 cursor-pointer border-white/10 text-gray-300">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                <span>Attach Reference Image</span>
                                <input type="file" ref="fileInput" @change="handleImageSelect" accept="image/*" class="hidden" />
                            </label>

                            <div v-if="imagePreviewUrl" class="flex items-center gap-2 bg-slate-950 p-1.5 rounded-lg border border-white/10">
                                <img :src="imagePreviewUrl" class="h-8 w-12 object-cover rounded" alt="Preview" />
                                <span class="text-xs text-gray-400 truncate max-w-[120px]">{{ commentForm.image?.name }}</span>
                                <button type="button" @click="removeSelectedImage" class="text-rose-500 hover:text-rose-400 text-sm font-bold pl-1 font-mono">×</button>
                            </div>
                        </div>

                        <div class="flex justify-between items-center flex-wrap gap-3 pt-2 border-t border-white/5">
                            <div v-if="!isPhotoProject && commentTime !== null" class="flex items-center gap-2 text-xs bg-indigo-500/10 px-3 py-1.5 rounded-lg border border-indigo-500/20">
                                <input type="checkbox" v-model="includeTimestamp" id="time-chk" class="rounded text-indigo-600 bg-slate-900 border-white/10" />
                                <label for="time-chk" class="text-indigo-300 font-medium cursor-pointer">
                                    Attach video time: <span class="font-mono">{{ formatTime(commentTime) }}</span>
                                </label>
                            </div>

                            <button type="submit" class="btn-primary py-2 px-6 ml-auto" :disabled="commentForm.processing">
                                Submit Feedback
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Version Comparison Control for Client -->
                <div v-if="project.drafts && project.drafts.length > 1" class="glass-card p-6">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div>
                            <h3 class="font-bold text-sm uppercase tracking-wider font-mono-technical text-gray-200 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                Version Comparison
                            </h3>
                            <p class="text-xs text-gray-400 mt-1 font-mono-technical">Compare draft versions side-by-side.</p>
                        </div>
                        <div class="flex items-center gap-3 flex-wrap">
                            <div class="flex items-center gap-2 text-xs font-mono-technical">
                                <span class="text-gray-400">Compare v:</span>
                                <select v-model="selectedDraft1" class="bg-slate-950 border border-white/10 rounded-sm text-xs py-1.5 px-3 text-white font-mono-technical focus:border-accent">
                                    <option v-for="d in project.drafts" :key="d.id" :value="d.id">v{{ d.version_number }}</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2 text-xs font-mono-technical">
                                <span class="text-gray-400">with v:</span>
                                <select v-model="selectedDraft2" class="bg-slate-950 border border-white/10 rounded-sm text-xs py-1.5 px-3 text-white font-mono-technical focus:border-accent">
                                    <option v-for="d in project.drafts" :key="d.id" :value="d.id">v{{ d.version_number }}</option>
                                </select>
                            </div>
                            <Link :href="route('client.projects.compare', [project.share_token])" :data="{ draft1: selectedDraft1, draft2: selectedDraft2 }" class="btn-primary py-1.5 px-4 text-xs font-mono-technical">
                                Compare Side-by-Side →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Revision Checklist -->
            <div class="space-y-6">
                <div class="glass-card p-6 flex flex-col h-[600px]">
                    <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
                        <h3 class="font-bold text-sm uppercase tracking-wider font-mono-technical text-gray-400">Feedback Checklist</h3>
                        <span v-if="activeDraft" class="bg-accent/10 text-accent text-xs px-2 py-0.5 rounded-sm border border-accent/20 font-bold font-mono-technical">
                            v{{ activeDraft.version_number }}
                        </span>
                    </div>

                    <div v-if="!activeDraft" class="text-center text-gray-500 my-auto">
                        No draft version uploaded yet.
                    </div>
                    
                    <div v-else-if="activeComments.length === 0" class="text-center text-gray-500 my-auto">
                        <p class="text-sm">No feedback left yet.</p>
                    </div>

                    <!-- Checklist items -->
                    <div v-else class="flex-1 overflow-y-auto space-y-4 pr-2">
                        <div v-for="(comment, index) in activeComments" 
                             :key="comment.id" 
                             @click="selectedCommentId = comment.id"
                             :class="`p-4 rounded-sm border transition-all duration-300 cursor-pointer ${
                                 selectedCommentId === comment.id ? 'ring-2 ring-accent border-accent' : ''
                             } ${
                                 comment.is_resolved 
                                     ? 'bg-[#1a1a1a]/40 border-emerald-500/20 opacity-60' 
                                     : comment.is_rejected 
                                     ? 'bg-[#1a1a1a]/40 border-rose-500/20' 
                                     : 'bg-[#1a1a1a] border-white/5'
                             }`">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <span v-if="comment.timestamp_seconds !== null" @click.stop="seekTo(comment.timestamp_seconds)" class="font-mono-technical bg-white/5 border border-white/5 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-sm hover:border-accent hover:text-accent transition-all">
                                            {{ formatTime(comment.timestamp_seconds) }}
                                        </span>
                                        <span v-else-if="comment.position_x !== null && comment.position_y !== null" class="font-mono-technical bg-accent/10 border border-accent/30 text-accent font-bold text-[10px] px-1.5 py-0.5 rounded-sm">
                                            Pin #{{ index + 1 }} ({{ Math.round(comment.position_x) }}%, {{ Math.round(comment.position_y) }}%)
                                        </span>
                                        <span v-else class="font-mono-technical bg-white/5 border border-white/5 text-gray-400 text-[10px] px-1.5 py-0.5 rounded-sm">
                                            General
                                        </span>
                                        <span class="text-xs text-gray-400 font-bold">{{ comment.author_name }}</span>
                                    </div>
                                    <p class="text-sm text-gray-200 leading-relaxed">{{ comment.content }}</p>
                                    
                                    <div v-if="comment.image_url" class="mt-2.5">
                                        <img :src="comment.image_url" @click.stop="selectedLightboxImage = comment.image_url" class="h-16 w-24 object-cover rounded-lg border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all hover:scale-105" alt="Attachment" />
                                    </div>
                                    
                                    <div v-if="comment.is_rejected" class="mt-2 p-2 bg-rose-500/5 border border-rose-500/10 rounded-lg text-xs text-rose-300">
                                        <span class="font-bold text-rose-200">Editor:</span> {{ comment.rejection_reason || 'No reason provided.' }}
                                    </div>
                                </div>

                                <div class="shrink-0 font-semibold text-xs flex items-center gap-2">
                                    <span v-if="comment.is_resolved" class="text-emerald-400 font-bold flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Resolved
                                    </span>
                                    <span v-else-if="comment.is_rejected" class="text-rose-400 font-bold flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        Declined
                                    </span>
                                    <span v-else class="text-amber-500 font-bold">○ Open</span>

                                    <button @click.stop="deleteComment(comment.id)" title="Delete" class="text-gray-500 hover:text-rose-400 p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mark current changes / Submit Revision Requests button -->
                    <div v-if="activeDraft && project.status !== 'approved' && project.status !== 'archived'" class="mt-4 pt-3 border-t border-white/5 flex justify-between items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2 text-xs font-mono-technical">
                            <span v-if="isSubmitted" class="text-sky-400 font-medium">● Editor notified of requested changes</span>
                            <span v-else class="text-gray-400">Finished leaving feedback?</span>
                        </div>

                        <button 
                            type="button" 
                            @click="markCurrentChanges" 
                            :class="`py-2 px-4 text-xs font-bold font-mono-technical uppercase tracking-wider rounded-sm transition-all shadow-md ${
                                isSubmitted ? 'bg-sky-500/10 text-sky-300 border border-sky-500/30' : 'bg-accent text-[#131313]'
                            }`"
                            :disabled="markingChanges"
                        >
                            {{ markingChanges ? 'Submitting...' : isSubmitted ? 'Update & Re-send Batch' : 'Submit Revision Requests' }}
                        </button>
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <h3 class="text-2xl font-bold mb-6 text-gray-100 font-editorial">Confirm Project Approval</h3>
                <p class="text-sm text-gray-400 mb-4">By approving this draft, you indicate that the {{ isPhotoProject ? 'photo set' : 'video' }} is complete and ready for final delivery.</p>

                <form @submit.prevent="submitApproval" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Your Name</label>
                        <input type="text" v-model="approvalForm.approver_name" class="w-full bg-slate-900/50 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-accent" required />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Remarks (Optional)</label>
                        <textarea v-model="approvalForm.remarks" rows="3" class="w-full bg-slate-900/50 border border-white/10 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-accent" placeholder="e.g. Approved!"></textarea>
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
        
        <!-- Lightbox -->
        <div v-if="selectedLightboxImage" @click="selectedLightboxImage = null" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md cursor-pointer">
            <img :src="selectedLightboxImage" class="max-w-full max-h-[90vh] object-contain rounded-xl border border-white/5 cursor-default" @click.stop />
        </div>
    </div>
</template>
