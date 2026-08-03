<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PhotoCanvas from '@/Components/PhotoCanvas.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    project: Object,
});

const currentDraftIndex = ref(0);
const selectedItemIndex = ref(0);
const selectedDraft1 = ref('');
const selectedDraft2 = ref('');
const showUploadModal = ref(false);
const videoPlayer = ref(null);
const photoCanvasRef = ref(null);
const selectedCommentId = ref(null);
const selectedLightboxImage = ref(null);

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
    // If photo project, filter comments by current active photo item if multiple photos exist
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

const isVertical = ref(false);

const onVideoLoaded = (e) => {
    const video = e.target;
    isVertical.value = video.videoHeight > video.videoWidth;
};

watch(currentDraftIndex, () => {
    isVertical.value = false;
    selectedItemIndex.value = 0;
    selectedCommentId.value = null;
});

onUnmounted(() => {
    stopPolling();
});

// Format timestamp: seconds to MM:SS
const formatTime = (seconds) => {
    if (seconds === null || seconds === undefined) return '';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

// Jump to time in video player
const jumpToTime = (seconds) => {
    if (videoPlayer.value && seconds !== null) {
        videoPlayer.value.currentTime = seconds;
        videoPlayer.value.play();
    }
};

// Select comment in photo canvas
const selectComment = (comment) => {
    selectedCommentId.value = comment.id;
};

// Upload State for Video & Photo Drafts
const uploadFiles = ref([]);
const uploadStatus = ref('idle');
const chunkProgress = ref(0);
const uploadError = ref(null);

const handleFileSelect = (e) => {
    uploadFiles.value = Array.from(e.target.files);
};

const closeSuccessModal = () => {
    showUploadModal.value = false;
    uploadFiles.value = [];
    uploadStatus.value = 'idle';
    chunkProgress.value = 0;
    router.reload();
};

const uploadDraft = async () => {
    if (uploadFiles.value.length === 0 || uploadStatus.value === 'uploading') return;

    if (isPhotoProject.value) {
        // Upload photo draft (standard multipart upload)
        uploadStatus.value = 'uploading';
        uploadError.value = null;

        const formData = new FormData();
        uploadFiles.value.forEach((file) => {
            formData.append('photos[]', file);
        });

        try {
            await axios.post(route('drafts.store', props.project.id), formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            uploadStatus.value = 'success';
        } catch (err) {
            uploadStatus.value = 'error';
            uploadError.value = err.response?.data?.message || 'Failed to upload photo draft.';
        }
        return;
    }

    // Video Upload (Chunked)
    uploadStatus.value = 'uploading';
    uploadError.value = null;

    const file = uploadFiles.value[0];
    const chunkSize = 10 * 1024 * 1024; // 10MB
    const totalChunks = Math.ceil(file.size / chunkSize);
    const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

    for (let index = 0; index < totalChunks; index++) {
        const start = index * chunkSize;
        const end = Math.min(start + chunkSize, file.size);
        const chunk = file.slice(start, end);

        const formData = new FormData();
        formData.append('file', chunk);
        formData.append('chunk_index', index);
        formData.append('total_chunks', totalChunks);
        formData.append('filename', file.name);
        formData.append('upload_id', uploadId);

        try {
            const response = await axios.post(route('drafts.upload-chunk', props.project.id), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (progressEvent) => {
                    const chunkUploadedPercent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    const overallPercent = Math.round(((index * 100) + chunkUploadedPercent) / totalChunks);
                    chunkProgress.value = Math.min(overallPercent, 99);
                }
            });

            if (response.data.status === 'completed') {
                chunkProgress.value = 100;
                uploadStatus.value = 'success';
                return;
            }
        } catch (error) {
            uploadStatus.value = 'error';
            uploadError.value = error.response?.data?.message || 'Upload failed.';
            return;
        }
    }
};

// Comment Resolution & Rejection
const resolveComment = (commentId, isResolved) => {
    const form = useForm({ is_resolved: isResolved });
    form.post(route('comments.resolve', commentId), { preserveScroll: true });
};

const showRejectModal = ref(false);
const rejectingCommentId = ref(null);
const rejectionForm = useForm({ rejection_reason: '', is_rejected: true });

const promptRejection = (comment) => {
    if (comment.is_rejected) {
        rejectionForm.is_rejected = false;
        rejectionForm.rejection_reason = '';
        rejectionForm.post(route('comments.reject', comment.id), { preserveScroll: true });
    } else {
        rejectingCommentId.value = comment.id;
        rejectionForm.is_rejected = true;
        rejectionForm.rejection_reason = '';
        showRejectModal.value = true;
    }
};

const submitRejection = () => {
    rejectionForm.post(route('comments.reject', rejectingCommentId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            rejectionForm.reset();
        }
    });
};

const copied = ref(false);
const shareUrl = computed(() => `${window.location.origin}/review/${props.project.share_token}`);

const copyShareLink = () => {
    // navigator.clipboard requires HTTPS — use execCommand fallback for HTTP (Laragon)
    const tryClipboard = () => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(shareUrl.value);
        }
        // HTTP fallback: temporary textarea trick
        const el = document.createElement('textarea');
        el.value = shareUrl.value;
        el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0';
        document.body.appendChild(el);
        el.focus();
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        return Promise.resolve();
    };
    tryClipboard()
        .then(() => {
            copied.value = true;
            setTimeout(() => copied.value = false, 2000);
        })
        .catch(() => {
            // Last resort: prompt the user to copy manually
            prompt('Copy this link:', shareUrl.value);
        });
};

const archiveProject = () => {
    if (confirm('Acknowledge and archive project?')) {
        router.post(route('projects.archive', props.project.id));
    }
};

const deleteProject = () => {
    if (confirm('Trash project permanently?')) {
        router.delete(route('projects.destroy', props.project.id));
    }
};

const deleteComment = (commentId) => {
    if (confirm('Delete this comment?')) {
        router.delete(route('comments.destroy', commentId), { preserveScroll: true });
    }
};

onMounted(() => {
    startPolling();
    if (props.project.drafts && props.project.drafts.length >= 2) {
        selectedDraft1.value = props.project.drafts[props.project.drafts.length - 1].id;
        selectedDraft2.value = props.project.drafts[0].id;
    }
});
</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center flex-wrap gap-4 border-b border-white/5 pb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ isPhotoProject ? '🖼️' : '🎬' }}</span>
                        <h2 class="text-3xl font-editorial tracking-tight text-gray-100">Project: {{ project.name }}</h2>
                        <span class="bg-[#1a1a1a] border border-white/5 px-2.5 py-1 rounded-sm text-[10px] font-mono-technical font-semibold uppercase tracking-wider flex items-center gap-1.5 text-gray-200">
                            <span :class="`w-1.5 h-1.5 rounded-full ${
                                project.status === 'approved' ? 'bg-[#10b981]' : project.status === 'archived' ? 'bg-[#6366f1]' : 'bg-[#f59e0b]'
                            }`"></span>
                            {{ project.status }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5 font-mono-technical uppercase tracking-wider">{{ project.description || 'No description provided' }}</p>
                </div>

                <div class="flex gap-3">
                    <a v-if="project.status === 'approved' || project.status === 'archived'" :href="route('projects.download-record', project.id)" target="_blank" class="btn-secondary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        Download PDF Audit Report
                    </a>

                    <button v-if="project.status === 'approved'" @click="archiveProject" class="btn-primary bg-gradient-to-r from-emerald-500 to-teal-600 shadow-emerald-500/30 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Acknowledge & Archive
                    </button>

                    <button v-if="project.status === 'archived'" @click="deleteProject" class="btn-secondary border-rose-500/20 text-rose-400 hover:bg-rose-500/10 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Trash Project
                    </button>
                    
                    <button v-if="project.status !== 'archived'" @click="showUploadModal = true" class="btn-primary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L6.707 8.121a1 1 0 01-1.414-1.414z" clip-rule="evenodd" /></svg>
                        Upload {{ isPhotoProject ? 'Photo' : 'Video' }} Draft
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Client Access Link Box -->
                <div class="glass-card p-6 flex justify-between items-center flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 bg-accent/10 rounded-sm text-accent">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-200">Secure Client Access Link</h4>
                            <p class="text-xs text-gray-400">Share magic URL with client <span class="text-accent font-mono-technical font-bold">{{ project.client?.name }}</span> to review and approve drafts.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-950 p-1.5 rounded-sm border border-white/5 w-full md:w-auto">
                        <span class="text-xs text-gray-400 px-3 truncate max-w-md font-mono-technical">{{ shareUrl }}</span>
                        <button @click="copyShareLink" class="btn-primary py-1.5 px-4 text-xs">
                            {{ copied ? 'Copied!' : 'Copy Link' }}
                        </button>
                    </div>
                </div>

                <!-- Main Grid Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left 2 Cols: Media Player or Photo Canvas -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="glass-card overflow-hidden p-4 space-y-4">
                            <!-- Photo Canvas (If Photo Project) -->
                            <template v-if="isPhotoProject">
                                <div v-if="activePhotoItem" class="space-y-4">
                                    <PhotoCanvas 
                                        ref="photoCanvasRef"
                                        :photo-url="activePhotoItem.file_url"
                                        :comments="activeComments"
                                        :selected-comment-id="selectedCommentId"
                                        @select-comment="selectComment"
                                        :readonly="true"
                                    />

                                    <!-- Multi-Photo Thumbnail Strip -->
                                    <div v-if="activeDraft && activeDraft.items && activeDraft.items.length > 1" class="flex items-center gap-2 overflow-x-auto pb-2 pt-2 border-t border-white/5">
                                        <span class="text-xs text-gray-400 font-mono-technical font-bold uppercase shrink-0">Photos ({{ activeDraft.items.length }}):</span>
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
                                    <span class="text-4xl mb-3 opacity-30">🖼️</span>
                                    <h4 class="font-editorial text-lg font-bold text-gray-300">No photo drafts uploaded</h4>
                                    <p class="text-xs text-gray-400 font-mono-technical uppercase tracking-wider mt-2">Upload your first photo draft to get started.</p>
                                </div>
                            </template>

                            <!-- Video Player (If Video Project) -->
                            <template v-else>
                                <div v-if="activeDraft" class="bg-black max-h-[65vh] flex items-center justify-center relative aspect-auto">
                                    <video v-if="activeDraft.status === 'ready'" ref="videoPlayer" :src="activeDraft.video_url" @loadedmetadata="onVideoLoaded" controls class="max-w-full max-h-[65vh] object-contain"></video>
                                    <div v-else-if="activeDraft.status === 'processing'" class="text-center p-8 text-gray-400">
                                        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-indigo-500 mx-auto mb-4"></div>
                                        <h4 class="font-semibold text-lg text-gray-200">Processing Draft v{{ activeDraft.version_number }}...</h4>
                                        <p class="text-sm">Transcoding video formats. Please wait.</p>
                                    </div>
                                </div>
                                <div v-else class="bg-[#1c1b1b] aspect-video flex flex-col items-center justify-center p-8 text-gray-500 rounded-none">
                                    <span class="text-4xl mb-3 opacity-30">🎬</span>
                                    <h4 class="font-editorial text-lg font-bold text-gray-300">No video drafts uploaded</h4>
                                </div>
                            </template>

                            <!-- Version Switcher Bar -->
                            <div v-if="activeDraft" class="pt-3 border-t border-white/5 flex justify-between items-center flex-wrap gap-4">
                                <div>
                                    <span class="text-xs uppercase font-semibold text-accent font-mono-technical">Active Draft View</span>
                                    <h4 class="text-base font-editorial font-bold text-gray-200">Draft Version {{ activeDraft.version_number }}</h4>
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

                        <!-- Version Comparison Link Card -->
                        <div v-if="project.drafts && project.drafts.length > 1" class="glass-card p-6">
                            <h3 class="font-bold text-lg mb-4 font-editorial">Version Comparison</h3>
                            <div class="flex items-center gap-4 flex-wrap">
                                <div class="flex items-center gap-2 text-xs">
                                    <span>Compare version:</span>
                                    <select v-model="selectedDraft1" class="bg-slate-950 border border-white/10 rounded-lg text-xs py-1.5 px-3 text-white">
                                        <option v-for="d in project.drafts" :key="d.id" :value="d.id">v{{ d.version_number }}</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 text-xs">
                                    <span>with version:</span>
                                    <select v-model="selectedDraft2" class="bg-slate-950 border border-white/10 rounded-lg text-xs py-1.5 px-3 text-white">
                                        <option v-for="d in project.drafts" :key="d.id" :value="d.id">v{{ d.version_number }}</option>
                                    </select>
                                </div>
                                <Link :href="route('projects.compare', [project.id])" :data="{ draft1: selectedDraft1, draft2: selectedDraft2 }" class="btn-primary py-1.5 px-4 text-xs">
                                    Compare Versions
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Revision Checklist -->
                    <div class="space-y-6">
                        <div class="glass-card p-6 flex flex-col h-[580px]">
                            <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
                                <h3 class="font-bold text-sm uppercase tracking-wider font-mono-technical text-gray-400">Feedback Checklist</h3>
                                <span v-if="activeDraft" class="bg-accent/10 text-accent text-xs px-2 py-0.5 rounded-sm border border-accent/20 font-bold font-mono-technical">
                                    v{{ activeDraft.version_number }}
                                </span>
                            </div>

                            <div v-if="!activeDraft" class="text-center text-gray-500 my-auto">
                                No drafts uploaded.
                            </div>
                            
                            <div v-else-if="activeComments.length === 0" class="text-center text-gray-500 my-auto">
                                <p class="text-sm">Checklist is clean!</p>
                                <p class="text-xs text-gray-400 mt-1">Client hasn't left feedback on this {{ isPhotoProject ? 'photo' : 'draft' }} yet.</p>
                            </div>

                            <div v-else class="flex-1 overflow-y-auto space-y-4 pr-2">
                                <div v-for="comment in activeComments" 
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
                                                <!-- Video Timestamp vs Photo Pin Tag -->
                                                <span v-if="comment.timestamp_seconds !== null" @click.stop="jumpToTime(comment.timestamp_seconds)" class="font-mono-technical bg-white/5 border border-white/5 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-sm hover:border-accent hover:text-accent transition-all">
                                                    {{ formatTime(comment.timestamp_seconds) }}
                                                </span>
                                                <span v-else-if="comment.position_x !== null && comment.position_y !== null" class="font-mono-technical bg-accent/10 border border-accent/30 text-accent font-bold text-[10px] px-1.5 py-0.5 rounded-sm">
                                                    Pin ({{ Math.round(comment.position_x) }}%, {{ Math.round(comment.position_y) }}%)
                                                </span>
                                                <span v-else class="font-mono-technical bg-white/5 border border-white/5 text-gray-400 text-[10px] px-1.5 py-0.5 rounded-sm">
                                                    General
                                                </span>
                                                <span class="text-xs text-gray-400 font-bold">{{ comment.author_name }}</span>
                                            </div>
                                            <p class="text-sm text-gray-200 leading-relaxed">{{ comment.content }}</p>

                                            <!-- Image Attachment Thumbnail -->
                                            <div v-if="comment.image_url" class="mt-2.5">
                                                <img :src="comment.image_url" @click.stop="selectedLightboxImage = comment.image_url" class="h-16 w-24 object-cover rounded-lg border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all hover:scale-105" alt="Attachment" />
                                            </div>
                                            
                                            <!-- Decline reason banner -->
                                            <div v-if="comment.is_rejected" class="mt-2 p-2 bg-rose-500/5 border border-rose-500/10 rounded-lg text-xs text-rose-300">
                                                <span class="font-bold text-rose-200">Declined:</span> {{ comment.rejection_reason || 'No reason provided.' }}
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <!-- Resolve Button -->
                                            <button @click.stop="resolveComment(comment.id, !comment.is_resolved)" :disabled="comment.is_rejected" :class="`p-1.5 rounded-sm border transition-all ${
                                                comment.is_resolved ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-slate-950 border-white/5 text-gray-500 hover:text-emerald-400'
                                            }`" title="Resolve">
                                                ✓
                                            </button>
                                            
                                            <!-- Reject Button -->
                                            <button @click.stop="promptRejection(comment)" :disabled="comment.is_resolved" :class="`p-1.5 rounded-sm border transition-all ${
                                                comment.is_rejected ? 'bg-rose-500/15 border-rose-500/30 text-rose-400' : 'bg-slate-950 border-white/5 text-gray-500 hover:text-rose-400'
                                            }`" title="Decline">
                                                ✕
                                            </button>

                                            <!-- Delete Button -->
                                            <button @click.stop="deleteComment(comment.id)" class="p-1.5 rounded-sm border bg-slate-950 border-white/5 text-gray-500 hover:text-rose-400 transition-all" title="Delete">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Draft Modal (Supports Video & Photo Uploads) -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-8 relative">
                <button @click="showUploadModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <h3 class="text-2xl font-bold mb-6 text-gray-100 font-editorial">
                    Upload New {{ isPhotoProject ? 'Photo Set' : 'Video' }} Draft
                </h3>

                <form @submit.prevent="uploadDraft" class="space-y-4">
                    <div class="border-2 border-dashed border-white/10 hover:border-accent/40 rounded-sm p-8 text-center cursor-pointer transition-colors relative">
                        <input type="file" 
                               @change="handleFileSelect" 
                               class="absolute inset-0 opacity-0 cursor-pointer" 
                               :accept="isPhotoProject ? 'image/*' : 'video/*'" 
                               :multiple="isPhotoProject"
                               :required="uploadFiles.length === 0" 
                               :disabled="uploadStatus === 'uploading'" />
                        <div class="space-y-2">
                            <span class="text-4xl block">{{ isPhotoProject ? '🖼️' : '🎬' }}</span>
                            <div class="text-sm text-gray-300 font-semibold font-mono-technical">
                                {{ uploadFiles.length > 0 ? `${uploadFiles.length} file(s) selected` : isPhotoProject ? 'Click or drag photo(s)' : 'Click or drag video file' }}
                            </div>
                            <p class="text-xs text-gray-400">
                                {{ isPhotoProject ? 'JPEG, PNG, WebP or AVIF (Select 1 or multiple photos)' : 'MP4, MOV, WEBM or MKV' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="uploadError" class="text-red-500 text-xs text-center mt-2 font-mono-technical">
                        {{ uploadError }}
                    </div>

                    <div v-if="uploadStatus === 'uploading'" class="space-y-2 text-center text-xs text-accent font-mono-technical animate-pulse">
                        <span>Uploading draft version...</span>
                    </div>
                    
                    <div v-else-if="uploadStatus === 'success'" class="text-center py-6 space-y-4">
                        <div class="w-12 h-12 rounded-full bg-accent/15 border border-accent/40 flex items-center justify-center mx-auto text-accent text-xl">
                            ✓
                        </div>
                        <div>
                            <h4 class="font-editorial text-lg font-bold text-gray-200">Draft Uploaded Successfully</h4>
                        </div>
                        <button type="button" @click="closeSuccessModal" class="btn-primary w-full py-2">
                            Done
                        </button>
                    </div>

                    <div v-if="uploadStatus === 'idle' || uploadStatus === 'error'" class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showUploadModal = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="uploadFiles.length === 0">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-md w-full p-8 relative">
                <button @click="showRejectModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>

                <h3 class="text-xl font-bold mb-2 text-gray-100">Mark Revision as Not Doable</h3>
                <p class="text-xs text-gray-400 mb-6">Type a short message explaining to the client why this feedback cannot be implemented.</p>

                <form @submit.prevent="submitRejection" class="space-y-4">
                    <textarea v-model="rejectionForm.rejection_reason" placeholder="Explain reason..." rows="3" class="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500" required></textarea>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showRejectModal = false" class="btn-secondary text-xs">Cancel</button>
                        <button type="submit" class="btn-primary text-xs bg-rose-600 hover:bg-rose-500">Decline Request</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
