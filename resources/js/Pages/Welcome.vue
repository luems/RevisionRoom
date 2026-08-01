<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    auth_user: Object,
});

const activeTab = ref('sync');
</script>

<template>
    <Head title="RevisionRoom — Next-Gen Video Review & Client Approval Platform" />

    <div class="min-h-screen bg-[#131313] text-gray-100 font-sans selection:bg-accent selection:text-[#131313] relative overflow-hidden">
        
        <!-- Ambient Glowing Background Blobs -->
        <div class="absolute top-[-10%] left-[20%] w-[500px] h-[500px] bg-accent/10 rounded-full blur-[140px] pointer-events-none"></div>
        <div class="absolute top-[40%] right-[-10%] w-[600px] h-[600px] bg-indigo-600/10 rounded-full blur-[160px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[30%] w-[450px] h-[450px] bg-emerald-500/10 rounded-full blur-[150px] pointer-events-none"></div>

        <!-- Sticky Header Navigation -->
        <header class="border-b border-white/5 bg-[#1c1b1b]/80 backdrop-blur-xl px-6 py-4 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-[#131313] font-black text-lg shadow-[0_0_20px_rgba(203,251,69,0.4)]">
                        R
                    </div>
                    <div>
                        <span class="text-xl font-editorial font-bold text-gray-100 tracking-tight">RevisionRoom</span>
                        <span class="ml-2 text-[9px] font-mono-technical uppercase tracking-widest text-accent bg-accent/10 border border-accent/20 px-1.5 py-0.5 rounded-sm">V2.0 PRO</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-xs font-mono-technical uppercase tracking-wider text-gray-400">
                    <a href="#features" class="hover:text-accent transition-colors">Features</a>
                    <a href="#compare" class="hover:text-accent transition-colors">Sync Player</a>
                    <a href="#workflow" class="hover:text-accent transition-colors">Workflow</a>
                    <a href="#security" class="hover:text-accent transition-colors">Security</a>
                </nav>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <template v-if="auth_user">
                        <Link :href="route('dashboard')" class="btn-primary py-2 px-5 text-xs flex items-center gap-2">
                            <span>Go to Dashboard</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="btn-secondary py-2 px-4 text-xs">
                            Sign In
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn-primary py-2 px-5 text-xs">
                            Start Free Trial
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-20 pb-16 px-6 max-w-7xl mx-auto text-center space-y-8 z-10">
            <!-- Pill Announcement Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-mono-technical text-gray-300 backdrop-blur-md shadow-inner">
                <span class="w-2 h-2 rounded-full bg-accent animate-ping"></span>
                <span>Introducing Synchronized Dual-Player Comparison</span>
                <span class="text-accent font-bold">New ✨</span>
            </div>

            <!-- Main Headline -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-editorial font-bold text-gray-100 tracking-tight leading-[1.1] max-w-5xl mx-auto">
                Hello World
            </h1>

            <!-- Subtitle -->
            <p class="text-base sm:text-lg text-gray-400 max-w-2xl mx-auto font-sans leading-relaxed">
                Eliminate endless email threads and vague feedback. Share magic review links, compare version drafts side-by-side with synchronized playback, and export legally verified PDF sign-offs.
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex items-center justify-center gap-4 flex-wrap pt-2">
                <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-3.5 px-8 text-sm flex items-center gap-2 text-[#131313] font-bold shadow-[0_0_30px_rgba(203,251,69,0.3)] hover:scale-105 transition-transform">
                    <span>Create Free Workspace</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
                <Link v-else :href="route('dashboard')" class="btn-primary py-3.5 px-8 text-sm flex items-center gap-2 text-[#131313] font-bold shadow-[0_0_30px_rgba(203,251,69,0.3)] hover:scale-105 transition-transform">
                    <span>Open Editor Dashboard</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
                <a href="#features" class="btn-secondary py-3.5 px-7 text-sm flex items-center gap-2 border-white/10 hover:border-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Explore Features</span>
                </a>
            </div>

            <!-- Feature Badges Bar -->
            <div class="pt-8 border-t border-white/5 max-w-4xl mx-auto flex items-center justify-center gap-6 sm:gap-12 flex-wrap text-xs font-mono-technical text-gray-400 uppercase tracking-wider">
                <div class="flex items-center gap-2">
                    <span class="text-accent font-bold">⚡</span> FFmpeg Transcoding
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-emerald-400 font-bold">🎞️</span> Frame-Exact Timestamps
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-indigo-400 font-bold">🔄</span> Dual Sync Comparison
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sky-400 font-bold">📄</span> PDF Audit Certificates
                </div>
            </div>
        </section>

        <!-- Interactive Mockup Showcase Section -->
        <section class="py-12 px-6 max-w-7xl mx-auto relative z-10">
            <div class="glass-card p-3 sm:p-6 bg-[#1a1a1a]/60 border border-white/10 rounded-2xl shadow-2xl overflow-hidden group">
                
                <!-- Mockup Header Bar -->
                <div class="flex items-center justify-between px-4 py-3 bg-[#131313] rounded-xl border border-white/5 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                        <span class="ml-3 text-xs font-mono-technical text-gray-400 truncate max-w-[200px]">RevisionRoom // Project: Commercial_Cut_v2.mp4</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] font-mono-technical bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-2 py-0.5 rounded uppercase font-bold">● Live Sync</span>
                        <span class="text-xs font-mono-technical text-gray-400 hidden sm:block">Share Token: <code class="text-accent font-mono">x9f8k2...</code></span>
                    </div>
                </div>

                <!-- Mockup Body (Dual Video Player Simulation) -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    
                    <!-- Left 2 Cols: Player Mock -->
                    <div class="lg:col-span-2 space-y-3">
                        <div class="bg-black aspect-video rounded-xl border border-white/10 relative overflow-hidden flex items-center justify-center group/video">
                            <!-- Background Dark Gradients to simulate video frame -->
                            <div class="absolute inset-0 bg-gradient-to-tr from-slate-950 via-slate-900 to-indigo-950/40"></div>
                            
                            <!-- Central Play Icon Simulation -->
                            <div class="w-16 h-16 rounded-full bg-accent/90 flex items-center justify-center shadow-2xl text-[#131313] group-hover/video:scale-110 transition-transform cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 ml-1" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </div>

                            <!-- Floating Comment Pins on Timeline Simulation -->
                            <div class="absolute bottom-12 left-1/4 px-2.5 py-1 bg-accent text-[#131313] text-[10px] font-bold font-mono-technical rounded shadow-lg animate-bounce">
                                00:14 — Color Grade Adjustment
                            </div>

                            <!-- Video Overlay Control Bar Mockup -->
                            <div class="absolute bottom-0 inset-x-0 bg-slate-950/80 backdrop-blur-md p-3 border-t border-white/10 flex items-center gap-3">
                                <span class="text-[11px] font-mono-technical text-accent">00:14 / 01:30</span>
                                <div class="flex-1 h-1.5 bg-white/10 rounded-full relative">
                                    <div class="w-1/4 h-full bg-accent rounded-full"></div>
                                    <div class="absolute left-1/4 top-1/2 -translate-y-1/2 w-3 h-3 bg-accent rounded-full border-2 border-[#131313]"></div>
                                </div>
                                <span class="text-[10px] font-mono-technical bg-white/10 px-2 py-0.5 rounded text-gray-300">1080p60</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Revision Checklist Mockup -->
                    <div class="bg-[#131313] rounded-xl border border-white/10 p-4 space-y-3 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center border-b border-white/5 pb-2 mb-3">
                                <span class="text-xs font-mono-technical uppercase tracking-wider text-gray-400 font-bold">Revision Checklist</span>
                                <span class="text-[10px] font-mono-technical text-accent">3 Comments</span>
                            </div>

                            <div class="space-y-2">
                                <div class="p-2.5 rounded bg-white/5 border border-white/5 text-xs space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="font-mono-technical bg-white/10 text-accent text-[10px] px-1.5 py-0.5 rounded">00:14</span>
                                        <span class="text-[10px] text-sky-400 font-mono-technical">● Requested</span>
                                    </div>
                                    <p class="text-gray-200 text-[11px]">Make the neon highlights pop more in the opening scene.</p>
                                </div>

                                <div class="p-2.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-xs space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="font-mono-technical bg-white/10 text-emerald-400 text-[10px] px-1.5 py-0.5 rounded">00:42</span>
                                        <span class="text-[10px] text-emerald-400 font-mono-technical">✓ Resolved</span>
                                    </div>
                                    <p class="text-gray-300 text-[11px]">Trim 2 frames off the transition to lower audio dip.</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-white/5 flex justify-between items-center">
                            <span class="text-[10px] text-gray-400 font-mono-technical">Status: Reviewing</span>
                            <button class="btn-primary py-1 px-3 text-[11px] font-mono-technical">
                                Submit Revisions
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Feature Grid Section -->
        <section id="features" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">Built for Video Creators</span>
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100">Everything You Need for Seamless Reviews</h2>
                <p class="text-gray-400 text-sm sm:text-base max-w-xl mx-auto">Eliminate friction, speed up turnarounds, and keep clients thrilled with your post-production workflow.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Feature 1 -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-accent/30 transition-all duration-300 space-y-4">
                    <div class="w-12 h-12 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-editorial font-bold text-gray-200">Synchronized Dual Player</h3>
                    <p class="text-xs text-gray-400 leading-relaxed font-sans">Compare v1 and v2 side-by-side with automatic drift correction, pre-buffering barriers, and unified master scrubbers.</p>
                </div>

                <!-- Feature 2 -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-emerald-500/30 transition-all duration-300 space-y-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-editorial font-bold text-gray-200">Paste Screenshot Feedback</h3>
                    <p class="text-xs text-gray-400 leading-relaxed font-sans">Clients can paste clipboard screenshots directly into comments with <kbd class="px-1 py-0.5 bg-white/10 rounded text-[10px] font-mono">Ctrl+V</kbd> alongside frame-exact timestamps.</p>
                </div>

                <!-- Feature 3 -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-indigo-500/30 transition-all duration-300 space-y-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-editorial font-bold text-gray-200">Passwordless Magic Links</h3>
                    <p class="text-xs text-gray-400 leading-relaxed font-sans">Zero login barrier for clients. Send one secure share link and let them review immediately on any desktop or mobile device.</p>
                </div>

                <!-- Feature 4 -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-sky-500/30 transition-all duration-300 space-y-4">
                    <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-editorial font-bold text-gray-200">Verifiable PDF Audit Trail</h3>
                    <p class="text-xs text-gray-400 leading-relaxed font-sans">Once approved, generate a legally traceable PDF audit record complete with approver name, timestamp, and signature log.</p>
                </div>

            </div>
        </section>

        <!-- Step-by-Step Workflow Section -->
        <section id="workflow" class="py-16 px-6 max-w-7xl mx-auto border-t border-white/5 relative z-10 space-y-12">
            <div class="text-center space-y-3">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">Simple 4-Step Process</span>
                <h2 class="text-3xl sm:text-4xl font-editorial font-bold text-gray-100">How RevisionRoom Works</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative">
                <!-- Step 1 -->
                <div class="space-y-3 bg-[#1a1a1a]/30 p-5 rounded-xl border border-white/5">
                    <span class="text-2xl font-mono-technical font-bold text-accent">01.</span>
                    <h4 class="font-editorial font-bold text-gray-200">Upload Video Draft</h4>
                    <p class="text-xs text-gray-400">Chunked background upload with automatic FFmpeg transcoding into streamable MP4 format.</p>
                </div>

                <!-- Step 2 -->
                <div class="space-y-3 bg-[#1a1a1a]/30 p-5 rounded-xl border border-white/5">
                    <span class="text-2xl font-mono-technical font-bold text-emerald-400">02.</span>
                    <h4 class="font-editorial font-bold text-gray-200">Share Client Link</h4>
                    <p class="text-xs text-gray-400">Copy the secure magic URL and send it to your client. No sign-up or passwords required.</p>
                </div>

                <!-- Step 3 -->
                <div class="space-y-3 bg-[#1a1a1a]/30 p-5 rounded-xl border border-white/5">
                    <span class="text-2xl font-mono-technical font-bold text-indigo-400">03.</span>
                    <h4 class="font-editorial font-bold text-gray-200">Receive Live Feedback</h4>
                    <p class="text-xs text-gray-400">Client comments lock to current video frame. Real-time 4s polling syncs editor and client views.</p>
                </div>

                <!-- Step 4 -->
                <div class="space-y-3 bg-[#1a1a1a]/30 p-5 rounded-xl border border-white/5">
                    <span class="text-2xl font-mono-technical font-bold text-sky-400">04.</span>
                    <h4 class="font-editorial font-bold text-gray-200">Client Sign-off & PDF</h4>
                    <p class="text-xs text-gray-400">Client signs off on the project. Project locks and generates an instant PDF verification record.</p>
                </div>
            </div>
        </section>

        <!-- Call to Action Banner -->
        <section class="py-16 px-6 max-w-7xl mx-auto relative z-10">
            <div class="glass-card p-8 sm:p-12 bg-gradient-to-r from-[#1c1b1b] via-[#222121] to-[#1c1b1b] border border-white/10 rounded-2xl text-center space-y-6 relative overflow-hidden shadow-2xl">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-accent/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <h2 class="text-3xl sm:text-4xl font-editorial font-bold text-gray-100">Ready to Upgrade Your Video Feedback?</h2>
                <p class="text-sm text-gray-400 max-w-xl mx-auto font-sans">Start reviewing drafts with your clients right now. No credit card required.</p>
                
                <div class="pt-2 flex justify-center items-center gap-4 flex-wrap">
                    <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-3 px-8 text-sm text-[#131313] font-bold shadow-lg">
                        Get Started Free →
                    </Link>
                    <Link v-else :href="route('dashboard')" class="btn-primary py-3 px-8 text-sm text-[#131313] font-bold shadow-lg">
                        Go to Dashboard →
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/5 py-8 px-6 text-center text-xs text-gray-500 font-mono-technical space-y-2">
            <p>RevisionRoom © 2026. Next-Gen Video Collaboration Platform.</p>
            <div class="flex justify-center gap-4 text-gray-400">
                <a href="#features" class="hover:text-accent">Features</a>
                <span>•</span>
                <a href="#workflow" class="hover:text-accent">Workflow</a>
                <span>•</span>
                <Link :href="route('login')" class="hover:text-accent">Sign In</Link>
            </div>
        </footer>

    </div>
</template>
