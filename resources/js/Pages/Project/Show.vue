<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    project: Object,
});

const currentDraftIndex = ref(0);
const selectedDraft1 = ref('');
const selectedDraft2 = ref('');
const showUploadModal = ref(false);
const videoPlayer = ref(null);

const activeDraft = computed(() => {
    if (!props.project.drafts || props.project.drafts.length === 0) return null;
    return props.project.drafts[currentDraftIndex.value];
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
    console.log(`[VideoLoaded] Dimensions: ${video.videoWidth}x${video.videoHeight}, isVertical: ${isVertical.value}`);
};

watch(currentDraftIndex, () => {
    isVertical.value = false;
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

// Jump to time in player
const jumpToTime = (seconds) => {
    if (videoPlayer.value && seconds !== null) {
        videoPlayer.value.currentTime = seconds;
        videoPlayer.value.play();
    }
};

const uploadFile = ref(null);
const uploadStatus = ref('idle'); // idle, uploading, merging, error, success
const chunkProgress = ref(0); // 0 to 100
const chunkIndexInfo = ref({ current: 0, total: 0 });
const uploadError = ref(null);

const fileSizeMB = computed(() => {
    if (!uploadFile.value) return 0;
    return Math.round(uploadFile.value.size / (1024 * 1024));
});

const uploadedMB = computed(() => {
    if (!uploadFile.value) return 0;
    const totalSize = uploadFile.value.size;
    const currentBytes = Math.round((chunkProgress.value / 100) * totalSize);
    return Math.round(currentBytes / (1024 * 1024));
});

const handleFileSelect = (e) => {
    uploadFile.value = e.target.files[0];
    console.log('[ProjectView] Selected file for upload:', uploadFile.value ? { name: uploadFile.value.name, size: uploadFile.value.size } : null);
};

const closeSuccessModal = () => {
    showUploadModal.value = false;
    uploadFile.value = null;
    uploadStatus.value = 'idle';
    chunkProgress.value = 0;
    router.reload();
};

const uploadDraft = async () => {
    if (!uploadFile.value || uploadStatus.value === 'uploading') return;

    uploadStatus.value = 'uploading';
    uploadError.value = null;

    const file = uploadFile.value;
    const chunkSize = 10 * 1024 * 1024; // 10MB chunk size
    const totalChunks = Math.ceil(file.size / chunkSize);
    const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    
    chunkIndexInfo.value = { current: 0, total: totalChunks };
    chunkProgress.value = 0;

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
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
                onUploadProgress: (progressEvent) => {
                    const chunkUploadedPercent = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    const overallPercent = Math.round(((index * 100) + chunkUploadedPercent) / totalChunks);
                    chunkProgress.value = Math.min(overallPercent, 99); // Let the completed status set 100

                    if (index === totalChunks - 1 && chunkUploadedPercent === 100) {
                        uploadStatus.value = 'merging';
                    }
                }
            });

            chunkIndexInfo.value.current = index + 1;

            if (response.data.status === 'completed') {
                chunkProgress.value = 100;
                uploadStatus.value = 'success';
                
                // Fire native system desktop notification if permitted
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('RevisionRoom', {
                        body: `Draft Version v${response.data.version || ''} uploaded and processed!`,
                        silent: false
                    });
                }
                return;
            }
        } catch (error) {
            console.error('[ProjectView] Chunk upload error:', error);
            uploadStatus.value = 'error';
            uploadError.value = error.response?.data?.message || 'Upload failed. Please try again.';
            return;
        }
    }
};

// Comment Resolution Form
const resolveComment = (commentId, isResolved) => {
    const form = useForm({ is_resolved: isResolved });
    form.post(route('comments.resolve', commentId), {
        preserveScroll: true,
        onStart: () => {
            console.log(`[ProjectView] Sending resolution change for comment ID ${commentId} (resolved: ${isResolved})...`);
        },
        onSuccess: () => {
            console.log(`[ProjectView] Successfully updated resolution for comment ID ${commentId}!`);
        },
        onError: (errors) => {
            console.error(`[ProjectView] Failed to update resolution for comment ID ${commentId}:`, errors);
        },
    });
};

// Comment Rejection Form
const showRejectModal = ref(false);
const rejectingCommentId = ref(null);
const rejectionForm = useForm({
    rejection_reason: '',
    is_rejected: true,
});

const promptRejection = (comment) => {
    if (comment.is_rejected) {
        rejectionForm.is_rejected = false;
        rejectionForm.rejection_reason = '';
        rejectionForm.post(route('comments.reject', comment.id), {
            preserveScroll: true,
            onSuccess: () => {
                console.log('[ProjectView] Rejection cleared.');
            }
        });
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
            console.log('[ProjectView] Comment rejected successfully.');
        }
    });
};

// Copy Share Link
const copied = ref(false);
const shareUrl = computed(() => {
    return `${window.location.origin}/review/${props.project.share_token}`;
});

const copyShareLink = () => {
    navigator.clipboard.writeText(shareUrl.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};

const archiveProject = () => {
    if (confirm('Are you sure you want to acknowledge and archive this project? This will lock client approvals and move it to archives.')) {
        router.post(route('projects.archive', props.project.id), {
            onSuccess: () => {
                console.log('[ProjectView] Project archived and locked successfully!');
            }
        });
    }
};

const deleteProject = () => {
    if (confirm('Are you sure you want to permanently delete/trash this project? This cannot be undone.')) {
        router.delete(route('projects.destroy', props.project.id), {
            onSuccess: () => {
                console.log('[ProjectView] Project deleted successfully.');
            }
        });
    }
};

const deleteComment = (commentId) => {
    if (confirm('Are you sure you want to delete this comment?')) {
        router.delete(route('comments.destroy', commentId), {
            preserveScroll: true,
            onSuccess: () => {
                console.log('[ProjectView] Comment deleted successfully.');
            }
        });
    }
};

onMounted(() => {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
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
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                        Download Approval PDF
                    </a>

                    <!-- Handshake Archive Button -->
                    <button v-if="project.status === 'approved'" @click="archiveProject" class="btn-primary bg-gradient-to-r from-emerald-500 to-teal-600 shadow-emerald-500/30 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Acknowledge & Archive
                    </button>

                    <!-- Trash Button for archived project -->
                    <button v-if="project.status === 'archived'" @click="deleteProject" class="btn-secondary border-rose-500/20 text-rose-400 hover:bg-rose-500/10 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Trash Project
                    </button>
                    
                    <button v-if="project.status !== 'archived'" @click="showUploadModal = true" class="btn-primary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L6.707 8.121a1 1 0 01-1.414-1.414z" clip-rule="evenodd" />
                        </svg>
                        Upload Draft
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
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-200">Secure Client Access Link</h4>
                            <p class="text-xs text-gray-400">Share this magic URL with client <span class="text-accent font-mono-technical font-bold">{{ project.client?.name }}</span> to review and approve drafts.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-950 p-1.5 rounded-sm border border-white/5 w-full md:w-auto">
                        <span class="text-xs text-gray-400 px-3 truncate max-w-md font-mono-technical">{{ shareUrl }}</span>
                        <button @click="copyShareLink" class="btn-primary py-1.5 px-4 text-xs">
                            {{ copied ? 'Copied!' : 'Copy Link' }}
                        </button>
                    </div>
                </div>

                <!-- Split Layout: Video & Revisions -->
                <div :class="isVertical ? 'grid grid-cols-1 lg:grid-cols-2 gap-6' : 'grid grid-cols-1 lg:grid-cols-3 gap-6'">
                    <!-- Left: Video & Versions -->
                    <div :class="isVertical ? 'lg:col-span-1 space-y-6' : 'lg:col-span-2 space-y-6'">
                        <!-- Custom Video Display -->
                        <div class="glass-card overflow-hidden">
                            <div v-if="activeDraft" class="bg-black max-h-[65vh] flex items-center justify-center relative aspect-auto">
                                <video v-if="activeDraft.status === 'ready'" ref="videoPlayer" :src="activeDraft.video_url" @loadedmetadata="onVideoLoaded" controls class="max-w-full max-h-[65vh] object-contain"></video>
                                <div v-else-if="activeDraft.status === 'processing'" class="text-center p-8 text-gray-400">
                                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-indigo-500 mx-auto mb-4"></div>
                                    <h4 class="font-semibold text-lg text-gray-200">Processing Draft v{{ activeDraft.version_number }}...</h4>
                                    <p class="text-sm">We are running FFmpeg to transcode and extract thumbnails. Please refresh in a moment.</p>
                                </div>
                                <div v-else class="text-center text-red-400 p-8">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    <h4 class="font-semibold">Transcoding Failed</h4>
                                    <p class="text-xs text-gray-400 mt-1">Please try uploading a different format.</p>
                                </div>
                            </div>
                            <div v-else class="bg-[#1c1b1b] aspect-video flex flex-col items-center justify-center p-8 text-gray-500 rounded-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-4 opacity-35 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <h4 class="font-editorial text-lg font-bold text-gray-300">No drafts uploaded</h4>
                                <p class="text-xs text-gray-400 font-mono-technical uppercase tracking-wider mt-2">Upload your first draft version to get started.</p>
                            </div>

                            <div v-if="activeDraft" class="p-6 bg-[#1a1a1a]/50 border-t border-white/5 flex justify-between items-center flex-wrap gap-4">
                                <div>
                                    <span class="text-xs uppercase font-semibold text-accent font-mono-technical">Active Draft View</span>
                                    <h4 class="text-base font-editorial font-bold text-gray-200">Draft Version {{ activeDraft.version_number }}</h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-200 font-mono-technical uppercase tracking-wider font-semibold">Versions:</span>
                                    <div class="flex gap-1 bg-slate-950 p-1 rounded-sm border border-white/10">
                                        <button 
                                            v-for="(draft, idx) in project.drafts" 
                                            :key="draft.id" 
                                            @click="currentDraftIndex = idx" 
                                            :class="`px-2.5 py-1 text-[11px] uppercase tracking-wider font-bold rounded-sm transition-all ${
                                                currentDraftIndex === idx ? 'bg-accent text-[#131313] font-extrabold shadow' : 'bg-white/10 text-gray-100 hover:text-white hover:bg-white/20 border border-white/10'
                                            }`"
                                        >
                                            v{{ draft.version_number }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Before and After Comparison Control -->
                        <div v-if="project.drafts && project.drafts.length > 1" class="glass-card p-6">
                            <h3 class="font-bold text-lg mb-4">Version Comparison</h3>
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
                                    Compare Side-by-Side
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Revision Checklist -->
                    <div class="space-y-6">
                        <div class="glass-card p-6 flex flex-col h-[550px]">
                            <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
                                <h3 class="font-bold text-sm uppercase tracking-wider font-mono-technical text-gray-400">Feedback & Annotations</h3>
                                <span v-if="activeDraft" class="bg-accent/10 text-accent text-xs px-2 py-0.5 rounded-sm border border-accent/20 font-bold font-mono-technical">
                                    v{{ activeDraft.version_number }}
                                </span>
                            </div>

                            <div v-if="!activeDraft" class="text-center text-gray-500 my-auto">
                                No drafts uploaded.
                            </div>
                            
                            <div v-else-if="activeDraft.comments.length === 0" class="text-center text-gray-500 my-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-2 opacity-35" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                <p class="text-sm">Checklist is clean!</p>
                                <p class="text-xs text-gray-400 mt-1">Client hasn't left feedback yet.</p>
                            </div>

                            <div v-else class="flex-1 overflow-y-auto space-y-4 pr-2">
                                <div v-for="comment in activeDraft.comments" :key="comment.id" :class="`p-4 rounded-sm border transition-all duration-300 ${
                                    comment.is_resolved 
                                        ? 'bg-[#1a1a1a]/40 border-emerald-500/20 opacity-60' 
                                        : comment.is_rejected 
                                        ? 'bg-[#1a1a1a]/40 border-rose-500/20' 
                                        : isSubmitted 
                                        ? 'bg-[#1c1d26] border-sky-400 shadow-[0_0_15px_rgba(56,189,248,0.2)] ring-1 ring-sky-400/40' 
                                        : 'bg-[#1a1a1a] border-white/5'
                                }`">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1.5">
                                                <span v-if="comment.timestamp_seconds !== null" @click="jumpToTime(comment.timestamp_seconds)" class="font-mono-technical bg-white/5 border border-white/5 text-gray-300 text-[10px] px-1.5 py-0.5 rounded-sm hover:border-accent hover:text-accent transition-all cursor-pointer">
                                                    {{ formatTime(comment.timestamp_seconds) }}
                                                </span>
                                                <span class="text-xs text-gray-400 font-bold">{{ comment.author_name }}</span>
                                            </div>
                                            <p class="text-sm text-gray-200 leading-relaxed">{{ comment.content }}</p>

                                            <!-- Image Attachment Thumbnail -->
                                            <div v-if="comment.image_url" class="mt-2.5">
                                                <img 
                                                    :src="comment.image_url" 
                                                    @click="selectedLightboxImage = comment.image_url" 
                                                    class="h-16 w-24 object-cover rounded-lg border border-white/10 hover:border-indigo-500/50 cursor-pointer transition-all hover:scale-105" 
                                                    alt="Attachment" 
                                                />
                                            </div>
                                            
                                            <!-- Decline reason banner -->
                                            <div v-if="comment.is_rejected" class="mt-2 p-2 bg-rose-500/5 border border-rose-500/10 rounded-lg text-xs text-rose-300 flex items-start gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-rose-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                                <span><span class="font-bold text-rose-200">Declined:</span> {{ comment.rejection_reason || 'No reason provided.' }}</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-1.5">
                                            <!-- Resolve Button -->
                                            <button @click="resolveComment(comment.id, !comment.is_resolved)" :disabled="comment.is_rejected" :class="`p-1.5 rounded-sm border transition-all ${
                                                comment.is_resolved ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-red-500/10 hover:border-red-500/20 hover:text-red-400' : 'bg-slate-950 border-white/5 text-gray-500 hover:bg-emerald-500/10 hover:border-emerald-500/20 hover:text-emerald-400'
                                            } ${comment.is_rejected ? 'opacity-35 cursor-not-allowed' : ''}`" :title="comment.is_resolved ? 'Mark Unresolved' : 'Mark Resolved'">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                            
                                            <!-- Reject Button -->
                                            <button @click="promptRejection(comment)" :disabled="comment.is_resolved" :class="`p-1.5 rounded-sm border transition-all ${
                                                comment.is_rejected ? 'bg-rose-500/15 border-rose-500/30 text-rose-400 hover:bg-slate-950 hover:border-white/5 hover:text-gray-500' : 'bg-slate-950 border-white/5 text-gray-500 hover:bg-rose-500/10 hover:border-rose-500/20 hover:text-rose-400'
                                            } ${comment.is_resolved ? 'opacity-35 cursor-not-allowed' : ''}`" :title="comment.is_rejected ? 'Clear Decline' : 'Mark Not Doable'">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>

                                            <!-- Delete Button -->
                                            <button @click="deleteComment(comment.id)" class="p-1.5 rounded-sm border bg-slate-950 border-white/5 text-gray-500 hover:bg-rose-500/10 hover:border-rose-500/20 hover:text-rose-400 transition-all" title="Delete Comment">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
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

        <!-- Upload Modal -->
        <div v-if="showUploadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm">
            <div class="glass-card max-w-md w-full p-8 relative">
                <button @click="showUploadModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-2xl font-bold mb-6 text-gray-100">Upload New Video Draft</h3>

                <form @submit.prevent="uploadDraft" class="space-y-4">
                    <div class="border-2 border-dashed border-white/10 hover:border-accent/40 rounded-sm p-8 text-center cursor-pointer transition-colors relative">
                        <input type="file" @change="handleFileSelect" class="absolute inset-0 opacity-0 cursor-pointer" accept="video/*" :required="!uploadFile" :disabled="uploadStatus === 'uploading'" />
                        <div class="space-y-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mx-auto text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <div class="text-sm text-gray-300 font-semibold font-mono-technical">
                                {{ uploadFile ? uploadFile.name : 'Click or drag video file' }}
                            </div>
                            <p class="text-xs text-gray-400">MP4, MOV, WEBM or MKV (Unlimited size, chunked)</p>
                        </div>
                    </div>

                    <div v-if="fileSizeMB > 100 && uploadStatus === 'idle'" class="p-3 bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs rounded-sm flex items-center gap-2">
                        <span>💡 Large file detected ({{ fileSizeMB }} MB). Using high-performance slice upload.</span>
                    </div>

                    <div v-if="uploadError" class="text-red-500 text-xs text-center mt-2 font-mono-technical">
                        {{ uploadError }}
                    </div>

                    <div v-if="uploadStatus === 'uploading'" class="space-y-2">
                        <div class="flex justify-between text-xs text-gray-400 font-mono-technical">
                            <span>Uploading Draft ({{ uploadedMB }} MB / {{ fileSizeMB }} MB)</span>
                            <span class="font-bold text-accent">{{ chunkProgress }}%</span>
                        </div>
                        <div class="w-full bg-slate-950 rounded-none h-2 border border-white/5 overflow-hidden">
                            <div class="bg-accent h-2 transition-all duration-200" :style="`width: ${chunkProgress}%`"></div>
                        </div>
                    </div>
                    
                    <div v-else-if="uploadStatus === 'merging'" class="space-y-2">
                        <div class="flex justify-between text-xs text-gray-400 font-mono-technical animate-pulse">
                            <span>Assembling video chunks on server...</span>
                            <span class="font-bold text-accent">99%</span>
                        </div>
                        <div class="w-full bg-slate-950 rounded-none h-2 border border-white/5 overflow-hidden">
                            <div class="bg-accent h-2 transition-all duration-200" style="width: 99%"></div>
                        </div>
                        <div class="text-[9px] text-gray-500 font-mono-technical text-right">
                            Writing to disk, please do not close this window
                        </div>
                    </div>
                    
                    <div v-else-if="uploadStatus === 'success'" class="text-center py-6 space-y-4">
                        <div class="w-12 h-12 rounded-full bg-accent/15 border border-accent/40 flex items-center justify-center mx-auto text-accent text-xl animate-bounce">
                            ✓
                        </div>
                        <div>
                            <h4 class="font-editorial text-lg font-bold text-gray-200 font-editorial">Draft Uploaded Successfully</h4>
                            <p class="text-xs text-gray-400 mt-1 font-mono-technical uppercase tracking-wider">Merged and queued for transcoding</p>
                        </div>
                        <button type="button" @click="closeSuccessModal" class="btn-primary w-full py-2">
                            Done
                        </button>
                    </div>

                    <div v-if="uploadStatus === 'idle' || uploadStatus === 'error'" class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="showUploadModal = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="!uploadFile">Upload</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rejection Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-fade-in">
            <div class="glass-card max-w-md w-full p-8 relative">
                <button @click="showRejectModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-xl font-bold mb-2 text-gray-100">Mark Revision as Not Doable</h3>
                <p class="text-xs text-gray-400 mb-6">Type a short message explaining to the client why this feedback cannot be implemented.</p>

                <form @submit.prevent="submitRejection" class="space-y-4">
                    <textarea 
                        v-model="rejectionForm.rejection_reason" 
                        placeholder="Explain reason (e.g. constraints, timeframe)..." 
                        rows="3" 
                        class="w-full bg-slate-950/50 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500" 
                        required
                    ></textarea>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" @click="showRejectModal = false" class="btn-secondary text-xs">Cancel</button>
                        <button type="submit" class="btn-primary text-xs bg-rose-600 hover:bg-rose-500 shadow-rose-600/20">Decline Request</button>
                    </div>
                </form>
            </div>
        </div>

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
