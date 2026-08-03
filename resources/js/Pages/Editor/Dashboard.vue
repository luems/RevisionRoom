<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    projects: Array,
});

const showCreateModal = ref(false);
const activeFilter = ref('all'); // 'all' | 'video' | 'photo' | 'active' | 'approved' | 'archived'

const form = useForm({
    name: '',
    description: '',
    media_type: 'video',
    client_name: '',
    client_email: '',
});

const filteredProjects = computed(() => {
    if (!props.projects) return [];
    if (activeFilter.value === 'all') return props.projects;
    if (activeFilter.value === 'video') return props.projects.filter(p => (p.media_type || 'video') === 'video');
    if (activeFilter.value === 'photo') return props.projects.filter(p => p.media_type === 'photo');
    return props.projects.filter(p => p.status === activeFilter.value);
});

const submit = () => {
    form.post(route('projects.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Editor Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center border-b border-white/5 pb-6 flex-wrap gap-4">
                <div>
                    <h2 class="text-3xl font-editorial tracking-tight text-gray-100">
                        Active Studio Projects
                    </h2>
                    <p class="text-xs text-gray-400 font-mono-technical mt-1">Manage video & photo client reviews, drafts, and approvals.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Filter Tabs -->
                    <div class="flex items-center gap-1 bg-[#1c1b1b] p-1 rounded-lg border border-white/10 text-xs font-mono-technical">
                        <button @click="activeFilter = 'all'" :class="`px-2.5 py-1 rounded font-bold ${activeFilter === 'all' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'}`">All</button>
                        <button @click="activeFilter = 'video'" :class="`px-2.5 py-1 rounded font-bold ${activeFilter === 'video' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'}`">🎬 Video</button>
                        <button @click="activeFilter = 'photo'" :class="`px-2.5 py-1 rounded font-bold ${activeFilter === 'photo' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'}`">🖼️ Photo</button>
                        <button @click="activeFilter = 'active'" :class="`px-2.5 py-1 rounded font-bold ${activeFilter === 'active' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'}`">Active</button>
                        <button @click="activeFilter = 'approved'" :class="`px-2.5 py-1 rounded font-bold ${activeFilter === 'approved' ? 'bg-accent text-[#131313]' : 'text-gray-400 hover:text-gray-200'}`">Approved</button>
                    </div>

                    <button @click="showCreateModal = true" class="btn-primary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        New Project
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12 bg-[#131313]">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="filteredProjects.length === 0" class="glass-card p-12 text-center flex flex-col items-center justify-center min-h-[300px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-500 mb-4 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="text-lg font-editorial font-bold mb-2">No projects found</h3>
                    <p class="text-gray-400 mb-6 max-w-sm text-sm">Create your first video or photo project to start collecting client feedback.</p>
                    <button @click="showCreateModal = true" class="btn-primary">Create a Project</button>
                </div>

                <!-- Projects Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="project in filteredProjects" :key="project.id" class="glass-card hover:border-accent/40 transition-all duration-300 flex flex-col justify-between overflow-hidden group">
                        <!-- Thumbnail Header / Background -->
                        <div class="h-44 bg-slate-900/60 relative flex items-center justify-center border-b border-white/5 overflow-hidden">
                            <img v-if="project.latest_draft && project.latest_draft.thumbnail_url" 
                                 :src="project.latest_draft.thumbnail_url" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                 alt="Project cover" />
                            <div v-else class="text-gray-600 flex flex-col items-center gap-2">
                                <span class="text-2xl">{{ project.media_type === 'photo' ? '🖼️' : '🎬' }}</span>
                                <span class="text-[10px] uppercase tracking-wider font-semibold opacity-50 font-mono-technical">No Drafts Uploaded</span>
                            </div>

                            <!-- Media Type Pill -->
                            <div class="absolute top-3 left-3 z-10">
                                <span class="bg-[#131313]/90 border border-white/10 px-2 py-0.5 rounded text-[10px] font-mono-technical font-bold uppercase tracking-wider text-accent flex items-center gap-1">
                                    <span>{{ project.media_type === 'photo' ? '🖼️ PHOTO' : '🎬 VIDEO' }}</span>
                                </span>
                            </div>

                            <!-- Status Pill -->
                            <div class="absolute top-3 right-3 z-10">
                                <span class="bg-[#131313]/90 border border-white/5 px-2.5 py-1 rounded-sm text-[10px] font-mono-technical font-semibold uppercase tracking-wider flex items-center gap-1.5 text-gray-200">
                                    <span :class="`w-1.5 h-1.5 rounded-full ${
                                        project.status === 'approved' ? 'bg-[#10b981]' : project.status === 'archived' ? 'bg-[#6366f1]' : 'bg-[#f59e0b]'
                                    }`"></span>
                                    {{ project.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                            <div>
                                <h4 class="text-xl font-editorial font-bold text-gray-100 mb-2 truncate group-hover:text-accent transition-colors">
                                    {{ project.name }}
                                </h4>
                                <p class="text-gray-400 text-sm line-clamp-2 leading-relaxed">
                                    {{ project.description || 'No description provided.' }}
                                </p>
                            </div>

                            <div class="border-t border-white/5 pt-4 flex flex-col gap-2 text-xs text-gray-400 font-mono-technical">
                                <div class="flex justify-between">
                                    <span>Client:</span>
                                    <span class="font-medium text-gray-200">{{ project.client?.name || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Draft Versions:</span>
                                    <span class="font-medium text-accent font-bold">{{ project.drafts_count }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div class="bg-[#1a1a1a]/30 px-6 py-4 border-t border-white/5 flex justify-between items-center">
                            <Link :href="route('projects.show', project.id)" class="text-accent hover:text-accent-hover font-mono-technical font-semibold text-xs flex items-center gap-1 uppercase tracking-wider">
                                Manage Project
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Project Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="glass-card max-w-lg w-full p-8 relative animate-fade-in">
                <button @click="showCreateModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-2xl font-editorial font-bold mb-6 text-gray-100">Create New Studio Project</h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Project Media Type Selection -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Project Media Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label :class="`p-3.5 rounded-xl border cursor-pointer transition-all flex flex-col justify-between space-y-2 ${
                                form.media_type === 'video' ? 'bg-accent/10 border-accent text-white' : 'bg-slate-950 border-white/10 text-gray-400 hover:border-white/20'
                            }`">
                                <input type="radio" v-model="form.media_type" value="video" class="sr-only" />
                                <div class="flex items-center gap-2 font-bold text-sm font-editorial text-gray-100">
                                    <span>🎬 Video Review</span>
                                </div>
                                <p class="text-[11px] leading-tight text-gray-400 font-sans">
                                    Review motion projects using timestamped feedback and synchronized video comparisons.
                                </p>
                            </label>

                            <label :class="`p-3.5 rounded-xl border cursor-pointer transition-all flex flex-col justify-between space-y-2 ${
                                form.media_type === 'photo' ? 'bg-accent/10 border-accent text-white' : 'bg-slate-950 border-white/10 text-gray-400 hover:border-white/20'
                            }`">
                                <input type="radio" v-model="form.media_type" value="photo" class="sr-only" />
                                <div class="flex items-center gap-2 font-bold text-sm font-editorial text-gray-100">
                                    <span>🖼️ Photo Review</span>
                                </div>
                                <p class="text-[11px] leading-tight text-gray-400 font-sans">
                                    Review photography and visual designs using pinned comments, zoomable previews, and image comparisons.
                                </p>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Project Name</label>
                        <input type="text" v-model="form.name" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2.5 text-sm text-white focus:outline-none focus:border-accent" required />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Description (Optional)</label>
                        <textarea v-model="form.description" rows="2" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2.5 text-sm text-white focus:outline-none focus:border-accent"></textarea>
                    </div>

                    <div class="border-t border-white/5 pt-4">
                        <h4 class="text-xs uppercase font-bold text-accent mb-3 tracking-wider font-mono-technical">Client Access Details</h4>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Client Name</label>
                                <input type="text" v-model="form.client_name" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2 text-sm text-white focus:outline-none focus:border-accent" required />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1">Client Email</label>
                                <input type="email" v-model="form.client_email" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2 text-sm text-white focus:outline-none focus:border-accent" required />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-white/5 mt-6">
                        <button type="button" @click="showCreateModal = false" class="btn-secondary">Cancel</button>
                        <button type="submit" class="btn-primary" :disabled="form.processing">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
