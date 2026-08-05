<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    auth_user: Object,
});

const activeFeature = ref('sync');
</script>

<template>
    <Head title="RevisionRoom — Next-Gen Video & Photo Review Platform" />

    <div class="min-h-screen bg-[#131313] text-gray-100 font-sans selection:bg-accent selection:text-[#131313] relative overflow-x-hidden">
        
        <!-- Subtle Background Grid Pattern -->
        <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.03)_1px,transparent_1px)] [background-size:32px_32px] pointer-events-none"></div>

        <!-- Sticky Header Navigation -->
        <header class="border-b border-white/5 bg-[#131313]/90 backdrop-blur-xl px-6 py-4 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <img src="/RevisionRoomLogo.png" alt="RevisionRoom" class="h-8 w-auto" />
                    <span class="text-[9px] font-mono-technical uppercase tracking-widest text-accent bg-accent/10 border border-accent/20 px-1.5 py-0.5 rounded-sm hidden sm:inline-block">VIDEO + PHOTO V2.0</span>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-xs font-mono-technical uppercase tracking-wider text-gray-400">
                    <a href="#features" class="hover:text-accent transition-colors">Features</a>
                    <a href="#photo-reviews" class="hover:text-accent transition-colors">Photo Pinning</a>
                    <a href="#compare" class="hover:text-accent transition-colors">Sync Player</a>
                    <a href="#workflow" class="hover:text-accent transition-colors">Workflow</a>
                </nav>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <template v-if="auth_user">
                        <Link :href="route('dashboard')" class="btn-primary py-2 px-5 text-xs font-bold font-mono-technical uppercase tracking-wider flex items-center gap-2">
                            <span>Go to Dashboard</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="btn-secondary py-2 px-4 text-xs font-mono-technical uppercase tracking-wider">
                            Log In
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="btn-primary py-2 px-5 text-xs font-bold font-mono-technical uppercase tracking-wider">
                            Start Free Trial
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- 1. HERO SECTION -->
        <section class="relative pt-20 pb-16 px-6 max-w-7xl mx-auto text-center space-y-8 z-10">
            
            <!-- Top Announcement Pill -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-mono-technical text-gray-300 hover:border-accent/40 transition-colors shadow-sm cursor-pointer">
                <span class="w-2 h-2 rounded-full bg-accent"></span>
                <span>Now Supporting High-Res Photo Collections & Pinned Annotations!</span>
                <span class="text-accent font-bold">Explore →</span>
            </div>

            <!-- Hero Headline -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-editorial font-bold text-gray-100 tracking-tight leading-[1.1] max-w-5xl mx-auto">
                Work on all your video & photo revisions <br class="hidden sm:block" />
                <span class="text-accent">in one single place.</span>
            </h1>

            <!-- Subtitle -->
            <p class="text-base sm:text-lg text-gray-400 max-w-3xl mx-auto font-sans leading-relaxed">
                Streamline video and photography client reviews with synchronized video playback, high-resolution zoomable photo pinning, screenshot feedback, and certified PDF sign-offs.
            </p>

            <!-- CTA Buttons -->
            <div class="flex items-center justify-center gap-4 flex-wrap pt-2">
                <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-3.5 px-8 text-sm font-bold font-mono-technical uppercase tracking-wider flex items-center gap-2 text-[#131313] hover:scale-105 transition-all">
                    <span>Start Free Trial</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
                <Link v-else :href="route('dashboard')" class="btn-primary py-3.5 px-8 text-sm font-bold font-mono-technical uppercase tracking-wider flex items-center gap-2 text-[#131313] hover:scale-105 transition-all">
                    <span>Open Dashboard</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
            </div>


        </section>

        <!-- 2. PHOTO REVIEW & PINNING FEATURE SECTION -->
        <section id="photo-reviews" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-16 border-t border-white/5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                
                <!-- Left Text Column -->
                <div class="space-y-6 text-left">
                    <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold bg-accent/10 border border-accent/20 px-3 py-1 rounded-full">
                        HIGH-RES PHOTO REVIEW & PINNING
                    </span>
                    <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100 leading-tight">
                        Pinpoint feedback directly on photography and visual designs.
                    </h2>
                    <p class="text-gray-400 text-sm sm:text-base font-sans leading-relaxed">
                        Say goodbye to vague emails like "fix the top right corner". RevisionRoom lets clients drop numbered pin markers at exact pixel percentages on high-res photos with pan and zoom.
                    </p>
                    <ul class="space-y-3 text-xs font-mono-technical text-gray-300">
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Click-to-place numbered pin markers with percentage-based normalized coordinates
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Multi-photo collection sets per draft version with interactive thumbnail strips
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> 4 Photo Comparison Modes: Side-by-Side, Reveal Slider, Opacity Blend, and Diff View
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Auto EXIF orientation correction and GD thumbnail generation
                        </li>
                    </ul>
                </div>

                <!-- Right Graphic Box: Mock Interactive Canvas -->
                <div class="glass-card p-6 bg-[#1a1a1a] border border-white/10 rounded-2xl relative overflow-hidden shadow-xl space-y-4">
                    <div class="flex justify-between items-center bg-[#131313] p-2.5 rounded-xl border border-white/5 text-xs font-mono-technical">
                        <span class="text-accent font-bold flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            Add Pin Mode Active
                        </span>
                        <span class="text-gray-400">Zoom: 150%</span>
                    </div>

                    <!-- Mock Canvas Box -->
                    <div class="bg-black aspect-video rounded-xl border border-white/10 relative flex items-center justify-center overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-indigo-950/60 via-slate-900 to-black flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 opacity-20 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        
                        <!-- Mock Pins -->
                        <div class="absolute top-[35%] left-[45%] w-7 h-7 rounded-full bg-rose-500 text-white font-mono-technical font-bold text-xs flex items-center justify-center shadow-lg ring-4 ring-rose-500/30">
                            1
                        </div>
                        <div class="absolute top-[60%] left-[70%] w-7 h-7 rounded-full bg-accent text-[#131313] font-mono-technical font-bold text-xs flex items-center justify-center shadow-lg ring-4 ring-accent/40 animate-pulse">
                            2
                        </div>
                    </div>

                    <!-- Mock Comment Card for Pin #2 -->
                    <div class="p-3.5 bg-accent/10 border border-accent/30 rounded-xl text-xs space-y-1">
                        <div class="flex justify-between items-center font-mono-technical">
                            <span class="text-accent font-bold">Pin #2 at (70.0%, 60.0%)</span>
                            <span class="text-gray-400">Client</span>
                        </div>
                        <p class="text-gray-200 text-xs">"Please adjust exposure on this highlight and retouch shadows."</p>
                    </div>
                </div>

            </div>
        </section>

        <!-- 3. FEATURE SECTION: SIDE-BY-SIDE SYNC VIDEO PLAYER -->
        <section id="compare" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-16 border-t border-white/5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                
                <!-- Left Graphic Box -->
                <div class="glass-card p-6 bg-[#1a1a1a] border border-white/10 rounded-2xl relative overflow-hidden shadow-xl order-2 lg:order-1">
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-950 rounded-xl border border-white/5 flex items-center justify-between text-xs font-mono-technical">
                            <span class="text-gray-400">Master Clock: <span class="text-accent font-bold">01:24.08</span></span>
                            <span class="text-emerald-400 font-bold">● Synchronized</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-xl border border-white/5 text-xs space-y-2">
                            <div class="flex justify-between items-center text-[10px] font-mono-technical">
                                <span class="text-accent font-bold">v1 Cut (01:24)</span>
                                <span class="text-gray-400">Client: Alex Rivera</span>
                            </div>
                            <p class="text-gray-200 text-xs">"The color tone here feels too dark. Can we lift the shadows?"</p>
                        </div>
                        <div class="p-4 bg-sky-500/10 rounded-xl border border-sky-500/20 text-xs space-y-2">
                            <div class="flex justify-between items-center text-[10px] font-mono-technical">
                                <span class="text-sky-300 font-bold">v2 Cut Applied (01:24)</span>
                                <span class="text-sky-400 font-bold">✓ Revision Batch Active</span>
                            </div>
                            <p class="text-sky-100 text-xs">"Shadows lifted by +15%. Color grade matched in v2."</p>
                        </div>
                    </div>
                </div>

                <!-- Right Text Column -->
                <div class="space-y-6 text-left order-1 lg:order-2">
                    <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold bg-accent/10 border border-accent/20 px-3 py-1 rounded-full">
                        SYNCHRONIZED DUAL PLAYER
                    </span>
                    <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100 leading-tight">
                        Compare video versions side-by-side without missing a frame.
                    </h2>
                    <p class="text-gray-400 text-sm sm:text-base font-sans leading-relaxed">
                        Never guess what changed between cuts. RevisionRoom links two draft versions with automatic drift correction, pre-buffering barrier sync, and sub-frame playback rate matching.
                    </p>
                    <ul class="space-y-3 text-xs font-mono-technical text-gray-300">
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Real-time drift correction (<0.15s threshold)
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Automatic buffering pause and barrier re-sync
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Master timeline slider controlling both videos
                        </li>
                    </ul>
                </div>

            </div>
        </section>

        <!-- 4. FEATURE BENTO GRID SECTION -->
        <section id="features" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-12 border-t border-white/5">
            <div class="text-center space-y-4">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">Built For High-Speed Workflows</span>
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100">Designed to delete friction from creative feedback.</h2>
                <p class="text-gray-400 text-sm sm:text-base max-w-xl mx-auto">Every feature is crafted specifically for video editors, photographers, motion designers, and post-production studios.</p>
            </div>

            <!-- Bento Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Bento Card 1: Video & Photo Support -->
                <div class="glass-card p-6 bg-[#1a1a1a] border border-white/5 hover:border-accent/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </div>
                        <h3 class="text-xl font-editorial font-bold text-gray-200">Video & Photo Projects</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">Choose between Video and Photo projects. Review motion with timestamped comments or photography sets with interactive pinned annotations.</p>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-lg border border-white/10 text-[10px] font-mono-technical text-gray-400 flex items-center justify-between">
                        <span>Formats: <code class="text-accent">MP4, MOV, JPEG, PNG, WebP</code></span>
                    </div>
                </div>

                <!-- Bento Card 2: Passwordless Magic Links -->
                <div class="glass-card p-6 bg-[#1a1a1a] border border-white/5 hover:border-emerald-500/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        </div>
                        <h3 class="text-xl font-editorial font-bold text-gray-200">Passwordless Magic Links</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">Share one secure tokenized link with clients. They review, comment, and sign off instantly on any desktop or mobile browser without signing up.</p>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-lg border border-white/10 text-[10px] font-mono-technical text-gray-400 truncate">
                        <code>https://revisionroom.com/review/x9f8k2m1...</code>
                    </div>
                </div>

                <!-- Bento Card 3: Verifiable PDF Certificates -->
                <div class="glass-card p-6 bg-[#1a1a1a] border border-white/5 hover:border-sky-500/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h3 class="text-xl font-editorial font-bold text-gray-200">PDF Sign-Off Audit Trails</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">Once client approves a project, lock revisions and generate a watermarked PDF audit certificate containing approver details, timestamp, and signature log.</p>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-lg border border-white/10 text-[10px] font-mono-technical text-sky-400 flex items-center justify-between">
                        <span>Approval PDF Generated</span>
                        <span class="font-bold">Download ↓</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- 5. STEP-BY-STEP WORKFLOW TIMELINE -->
        <section id="workflow" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-16">
            <div class="text-center space-y-4">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">End-to-End Workflow</span>
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100">From raw draft to final sign-off in 4 steps.</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="p-6 bg-[#1a1a1a] border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-accent">01</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Upload Draft</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Upload video drafts with chunked background processing or photo collections with EXIF auto-rotation.</p>
                </div>

                <!-- Step 2 -->
                <div class="p-6 bg-[#1a1a1a] border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-emerald-400">02</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Send Magic Link</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Copy and send the passwordless share link to clients. Instant access on mobile or desktop.</p>
                </div>

                <!-- Step 3 -->
                <div class="p-6 bg-[#1a1a1a] border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-indigo-400">03</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Collect Revisions</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Clients leave timestamped comments or pinned photo markers. Editor views live sync automatically.</p>
                </div>

                <!-- Step 4 -->
                <div class="p-6 bg-[#1a1a1a] border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-sky-400">04</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Get PDF Sign-off</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Client signs off on final cut or photo set. Project locks and generates a verified approval PDF certificate.</p>
                </div>
            </div>
        </section>

        <!-- 6. CLEAN CTA BANNER -->
        <section class="py-20 px-6 max-w-7xl mx-auto relative z-10">
            <div class="glass-card p-10 sm:p-16 bg-[#1c1b1b] border border-white/10 rounded-3xl text-center space-y-8 shadow-xl">
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100 leading-tight">
                    Start reviewing video & photo drafts <br class="hidden sm:block" />
                    with your clients today.
                </h2>
                <p class="text-sm sm:text-base text-gray-400 max-w-xl mx-auto font-sans">
                    Join video editors, photographers, post houses, and creative agencies using RevisionRoom.
                </p>

                <div class="pt-4 flex justify-center items-center gap-4 flex-wrap">
                    <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-4 px-9 text-sm font-bold font-mono-technical uppercase tracking-wider text-[#131313] hover:scale-105 transition-all">
                        Create Free Workspace →
                    </Link>
                    <Link v-else :href="route('dashboard')" class="btn-primary py-4 px-9 text-sm font-bold font-mono-technical uppercase tracking-wider text-[#131313] hover:scale-105 transition-all">
                        Go to Dashboard →
                    </Link>
                </div>
            </div>
        </section>

        <!-- 7. FOOTER -->
        <footer class="border-t border-white/5 py-16 px-6 bg-[#0f0e0e] text-xs text-gray-500 font-mono-technical">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex items-center gap-3">
                    <img src="/RevisionRoomLogo.png" alt="RevisionRoom" class="h-7 w-auto" />
                </div>
                <div class="text-gray-500 text-xs">
                    © 2026 RevisionRoom Inc. Video & Photo Client Review Platform.
                </div>
            </div>
        </footer>

    </div>
</template>
