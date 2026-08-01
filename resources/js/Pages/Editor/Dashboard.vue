<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    projects: Array,
});

const showCreateModal = ref(false);

const form = useForm({
    name: '',
    description: '',
    client_name: '',
    client_email: '',
});

const submit = () => {
    form.post(route('projects.store'), {
        onStart: () => {
            console.log('[Dashboard] Creating new project...', form.data());
        },
        onSuccess: () => {
            console.log('[Dashboard] Project created successfully!');
            showCreateModal.value = false;
            form.reset();
        },
        onError: (errors) => {
            console.error('[Dashboard] Project creation failed with errors:', errors);
        },
    });
};
</script>

<template>
    <Head title="Editor Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center border-b border-white/5 pb-6">
                <h2 class="text-3xl font-editorial tracking-tight text-gray-100">
                    Active Studio Projects
                </h2>
                <button @click="showCreateModal = true" class="btn-primary flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    New Project
                </button>
            </div>
        </template>

        <div class="py-12 bg-[#131313]">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="projects.length === 0" class="glass-card p-12 text-center flex flex-col items-center justify-center min-h-[300px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-500 mb-4 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="text-lg font-editorial font-bold mb-2">No projects created yet</h3>
                    <p class="text-gray-400 mb-6 max-w-sm text-sm">Create your first creative project and invite your client to share draft versions and collect timestamped feedback.</p>
                    <button @click="showCreateModal = true" class="btn-primary">Create a Project</button>
                </div>

                <!-- Projects Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="project in projects" :key="project.id" class="glass-card hover:border-accent/40 transition-all duration-300 flex flex-col justify-between overflow-hidden group">
                        <!-- Thumbnail Header / Background -->
                        <div class="h-44 bg-slate-900/60 relative flex items-center justify-center border-b border-white/5 overflow-hidden">
                            <img v-if="project.latest_draft && project.latest_draft.thumbnail_url" 
                                 :src="project.latest_draft.thumbnail_url" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                 alt="Project cover" />
                            <div v-else class="text-gray-600 flex flex-col items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span class="text-[10px] uppercase tracking-wider font-semibold opacity-50 font-mono-technical">No Drafts Uploaded</span>
                            </div>
                            <div class="absolute top-3 right-3">
                                <span :class="`px-2 py-0.5 rounded-sm text-[10px] font-semibold uppercase tracking-wider flex items-center gap-1.5 border ${
                                    project.status === 'approved' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : project.status === 'archived' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                                }`">
                                    <span :class="`w-1.5 h-1.5 rounded-full ${
                                        project.status === 'approved' ? 'bg-emerald-400' : project.status === 'archived' ? 'bg-indigo-400' : 'bg-amber-400'
                                    }`"></span>
                                    {{ project.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xl font-editorial font-bold text-gray-100 mb-2 truncate group-hover:text-accent transition-colors">
                                    {{ project.name }}
                                </h4>
                                <p class="text-gray-400 text-sm line-clamp-2 mb-4 leading-relaxed">
                                    {{ project.description || 'No description provided.' }}
                                </p>
                            </div>

                            <div class="border-t border-white/5 pt-4 mt-2 flex flex-col gap-2 text-xs text-gray-400">
                                <div class="flex justify-between">
                                    <span>Client:</span>
                                    <span class="font-medium text-gray-200">{{ project.client?.name || 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Drafts:</span>
                                    <span class="font-medium text-gray-200">{{ project.drafts_count }}</span>
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
            <div class="glass-card max-w-md w-full p-8 relative animate-fade-in">
                <button @click="showCreateModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <h3 class="text-2xl font-editorial font-bold mb-6 text-gray-100">Create New Project</h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Project Name</label>
                        <input type="text" v-model="form.name" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2.5 text-sm text-white focus:outline-none focus:border-accent" required />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Description (Optional)</label>
                        <textarea v-model="form.description" rows="3" class="w-full bg-slate-950 border border-white/10 rounded-sm px-4 py-2.5 text-sm text-white focus:outline-none focus:border-accent"></textarea>
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
