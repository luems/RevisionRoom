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
    <Head title="RevisionRoom — Next-Gen Video Review & Client Approval Platform" />

    <div class="min-h-screen bg-[#131313] text-gray-100 font-sans selection:bg-accent selection:text-[#131313] relative overflow-x-hidden">
        
        <!-- Subtle Background Grid Pattern -->
        <div class="absolute inset-0 bg-[radial-gradient(rgba(255,255,255,0.05)_1px,transparent_1px)] [background-size:32px_32px] pointer-events-none"></div>

        <!-- Ambient Glowing Background Orbs -->
        <div class="absolute top-[-100px] left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-accent/10 rounded-full blur-[160px] pointer-events-none"></div>
        <div class="absolute top-[1200px] right-[-100px] w-[500px] h-[500px] bg-indigo-600/10 rounded-full blur-[160px] pointer-events-none"></div>

        <!-- Sticky Header Navigation -->
        <header class="border-b border-white/5 bg-[#131313]/90 backdrop-blur-xl px-6 py-4 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-md bg-accent flex items-center justify-center text-[#131313] font-black text-base shadow-[0_0_15px_rgba(203,251,69,0.3)]">
                        R
                    </div>
                    <span class="text-xl font-editorial font-bold text-gray-100 tracking-tight">RevisionRoom</span>
                    <span class="text-[9px] font-mono-technical uppercase tracking-widest text-accent bg-accent/10 border border-accent/20 px-1.5 py-0.5 rounded-sm hidden sm:inline-block">V2.0 PRO</span>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center gap-8 text-xs font-mono-technical uppercase tracking-wider text-gray-400">
                    <a href="#features" class="hover:text-accent transition-colors">Features</a>
                    <a href="#compare" class="hover:text-accent transition-colors">Sync Player</a>
                    <a href="#workflow" class="hover:text-accent transition-colors">Workflow</a>
                    <a href="#tech" class="hover:text-accent transition-colors">Specs</a>
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
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-mono-technical text-gray-300 hover:border-accent/40 transition-colors shadow-inner cursor-pointer">
                <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                <span>Introducing Synchronized Dual-Player Comparison</span>
                <span class="text-accent font-bold">Explore →</span>
            </div>

            <!-- Hero Headline -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-editorial font-bold text-gray-100 tracking-tight leading-[1.1] max-w-5xl mx-auto">
                Work on all your video revisions <br class="hidden sm:block" />
                <span class="text-accent drop-shadow-[0_0_35px_rgba(203,251,69,0.3)]">in one single place.</span>
            </h1>

            <!-- Subtitle -->
            <p class="text-base sm:text-lg text-gray-400 max-w-2xl mx-auto font-sans leading-relaxed">
                Streamline video reviews, run side-by-side version comparison with frame sync, collect screenshot feedback, and get verified PDF sign-offs.
            </p>

            <!-- CTA Buttons -->
            <div class="flex items-center justify-center gap-4 flex-wrap pt-2">
                <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-3.5 px-8 text-sm font-bold font-mono-technical uppercase tracking-wider flex items-center gap-2 text-[#131313] shadow-[0_0_25px_rgba(203,251,69,0.3)] hover:scale-105 transition-all">
                    <span>Start Free Trial</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
                <Link v-else :href="route('dashboard')" class="btn-primary py-3.5 px-8 text-sm font-bold font-mono-technical uppercase tracking-wider flex items-center gap-2 text-[#131313] shadow-[0_0_25px_rgba(203,251,69,0.3)] hover:scale-105 transition-all">
                    <span>Open Dashboard</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
                <a href="#compare" class="btn-secondary py-3.5 px-7 text-sm font-mono-technical uppercase tracking-wider flex items-center gap-2 border-white/10 hover:border-white/20">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Watch Interactive Demo</span>
                </a>
            </div>

            <!-- Hero Product Showcase Mockup with Floating Tool Widgets -->
            <div class="pt-8 relative max-w-6xl mx-auto">
                
                <!-- Floating Widget Left: Comment Bubble -->
                <div class="hidden lg:flex absolute -left-12 top-24 z-20 p-3 rounded-xl bg-[#1c1b1b]/90 border border-white/10 backdrop-blur-xl shadow-2xl items-center gap-3 animate-bounce [animation-duration:4s]">
                    <div class="w-8 h-8 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-accent font-bold text-xs font-mono">
                        0:14
                    </div>
                    <div class="text-left text-xs">
                        <div class="font-bold text-gray-200">Color Grade Shift</div>
                        <div class="text-[10px] text-gray-400 font-mono-technical">"Make neon pop in intro frame"</div>
                    </div>
                </div>

                <!-- Floating Widget Right: Sync Status Badge -->
                <div class="hidden lg:flex absolute -right-10 top-36 z-20 p-3 rounded-xl bg-[#1c1b1b]/90 border border-white/10 backdrop-blur-xl shadow-2xl items-center gap-3">
                    <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></div>
                    <div class="text-left text-xs font-mono-technical">
                        <div class="font-bold text-emerald-400">Sync Corrected (0.00s)</div>
                        <div class="text-[10px] text-gray-400">Dual-Player Active</div>
                    </div>
                </div>

                <!-- Main Glassmorphic Showcase Container -->
                <div class="glass-card p-4 sm:p-6 bg-[#1a1a1a]/70 border border-white/10 rounded-2xl shadow-2xl overflow-hidden relative group">
                    <div class="flex justify-between items-center px-4 py-3 bg-[#131313] rounded-xl border border-white/5 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                            <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                            <span class="ml-3 text-xs font-mono-technical text-gray-400 truncate">RevisionRoom // Project: Cyberpunk_Ad_Cut_v2.mp4</span>
                        </div>
                        <div class="flex items-center gap-3 font-mono-technical text-xs">
                            <span class="bg-accent/10 border border-accent/30 text-accent px-2 py-0.5 rounded text-[10px] uppercase font-bold">Compare v1 vs v2</span>
                        </div>
                    </div>

                    <!-- Dual Player Preview Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- v1 Player Box -->
                        <div class="bg-black aspect-video rounded-xl border border-white/10 relative overflow-hidden flex flex-col justify-between p-3 group/p1">
                            <div class="flex justify-between items-center z-10">
                                <span class="bg-accent text-[#131313] font-bold font-mono-technical text-[10px] px-2 py-0.5 rounded uppercase">v1 (Original)</span>
                                <span class="text-[10px] font-mono-technical text-gray-400">1080p // 24fps</span>
                            </div>
                            <div class="self-center w-12 h-12 rounded-full bg-white/20 border border-white/30 backdrop-blur-md flex items-center justify-center text-white cursor-pointer group-hover/p1:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                            </div>
                            <div class="w-full bg-white/10 h-1 rounded-full relative z-10"><div class="w-1/3 h-full bg-accent rounded-full"></div></div>
                        </div>

                        <!-- v2 Player Box -->
                        <div class="bg-black aspect-video rounded-xl border border-white/10 relative overflow-hidden flex flex-col justify-between p-3 group/p2">
                            <div class="flex justify-between items-center z-10">
                                <span class="bg-accent text-[#131313] font-bold font-mono-technical text-[10px] px-2 py-0.5 rounded uppercase">v2 (Latest Cut)</span>
                                <span class="text-[10px] font-mono-technical text-emerald-400 font-bold">● Active Revision</span>
                            </div>
                            <div class="self-center w-12 h-12 rounded-full bg-accent flex items-center justify-center text-[#131313] shadow-lg cursor-pointer group-hover/p2:scale-110 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                            </div>
                            <div class="w-full bg-white/10 h-1 rounded-full relative z-10"><div class="w-1/3 h-full bg-accent rounded-full"></div></div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- 2. LOGO TICKER / METRIC PROOF BAR -->
        <section class="py-12 border-y border-white/5 bg-[#161515]">
            <div class="max-w-7xl mx-auto px-6 text-center space-y-4">
                <span class="text-[11px] font-mono-technical uppercase tracking-widest text-gray-500 font-bold">Trusted by Editors, Post Houses & Agencies Worldwide</span>
                <div class="flex items-center justify-center gap-8 sm:gap-16 flex-wrap text-sm font-mono-technical text-gray-400 font-bold">
                    <span class="hover:text-accent transition-colors">🎞️ FRAME.ACCURATE</span>
                    <span class="hover:text-accent transition-colors">⚡ FFmpeg.FAST</span>
                    <span class="hover:text-accent transition-colors">🔄 SYNC.PLAYBACK</span>
                    <span class="hover:text-accent transition-colors">📄 PDF.CERTIFIED</span>
                    <span class="hover:text-accent transition-colors">🔒 MAGIC.LINKS</span>
                </div>
            </div>
        </section>

        <!-- 3. FEATURE SECTION 1: SIDE-BY-SIDE SYNC PLAYER -->
        <section id="compare" class="py-24 px-6 max-w-7xl mx-auto relative z-10 space-y-16">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                
                <!-- Left Text Column -->
                <div class="space-y-6 text-left">
                    <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold bg-accent/10 border border-accent/20 px-3 py-1 rounded-full">
                        SYNCHRONIZED DUAL PLAYER
                    </span>
                    <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100 leading-tight">
                        Compare versions side-by-side without missing a frame.
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
                        <li class="flex items-center gap-2">
                            <span class="text-accent font-bold">✓</span> Instant comment jump to exact timestamp on both cuts
                        </li>
                    </ul>
                </div>

                <!-- Right Graphic Box -->
                <div class="glass-card p-6 bg-[#1a1a1a]/60 border border-white/10 rounded-2xl relative overflow-hidden shadow-2xl">
                    <div class="space-y-4">
                        <div class="p-4 bg-slate-950 rounded-xl border border-white/5 flex items-center justify-between text-xs font-mono-technical">
                            <span class="text-gray-400">Master Clock: <span class="text-accent font-bold">01:24.08</span></span>
                            <span class="text-emerald-400 font-bold">● Both Ready (canplaythrough)</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-xl border border-white/5 text-xs space-y-2">
                            <div class="flex justify-between items-center text-[10px] font-mono-technical">
                                <span class="text-accent font-bold">v1 Feedback (01:24)</span>
                                <span class="text-gray-400">Client: Alex Rivera</span>
                            </div>
                            <p class="text-gray-200 text-xs">"The color tone here feels too dark. Can we lift the shadows?"</p>
                        </div>
                        <div class="p-4 bg-sky-500/10 rounded-xl border border-sky-500/20 text-xs space-y-2">
                            <div class="flex justify-between items-center text-[10px] font-mono-technical">
                                <span class="text-sky-300 font-bold">v2 Fix Applied (01:24)</span>
                                <span class="text-sky-400 font-bold">✓ Revision Batch Active</span>
                            </div>
                            <p class="text-sky-100 text-xs">"Shadows lifted by +15%. Color grade matched in v2."</p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- 4. FEATURE BENTO GRID SECTION -->
        <section id="features" class="py-20 px-6 max-w-7xl mx-auto relative z-10 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">Built For High-Speed Workflows</span>
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100">Designed to delete friction from video feedback.</h2>
                <p class="text-gray-400 text-sm sm:text-base max-w-xl mx-auto">Every feature is crafted specifically for video editors, motion designers, and post-production studios.</p>
            </div>

            <!-- Bento 3-Card Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Bento Card 1: Clipboard Screenshot Paste -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-accent/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent font-bold">
                            📋
                        </div>
                        <h3 class="text-xl font-editorial font-bold text-gray-200">Clipboard Image Paste</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">Clients can copy any screenshot and paste it directly into the feedback box with <kbd class="px-1.5 py-0.5 bg-white/10 rounded text-[10px] font-mono text-accent">Ctrl+V</kbd>. No file picker required.</p>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-lg border border-white/10 text-[10px] font-mono-technical text-gray-400 flex items-center justify-between">
                        <span>Image Attached: <code class="text-accent">screenshot_01.png</code></span>
                        <span class="text-emerald-400 font-bold">✓ Ready</span>
                    </div>
                </div>

                <!-- Bento Card 2: Passwordless Magic Links -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-emerald-500/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold">
                            🔗
                        </div>
                        <h3 class="text-xl font-editorial font-bold text-gray-200">Passwordless Magic Links</h3>
                        <p class="text-xs text-gray-400 leading-relaxed">Share one secure tokenized link with clients. They review, comment, and sign off instantly on any desktop or mobile browser without signing up.</p>
                    </div>
                    <div class="p-3 bg-slate-950 rounded-lg border border-white/10 text-[10px] font-mono-technical text-gray-400 truncate">
                        <code>https://revisionroom.com/review/x9f8k2m1...</code>
                    </div>
                </div>

                <!-- Bento Card 3: Verifiable PDF Certificates -->
                <div class="glass-card p-6 bg-[#1a1a1a]/50 border border-white/5 hover:border-sky-500/40 transition-all duration-300 space-y-4 flex flex-col justify-between">
                    <div class="space-y-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 font-bold">
                            📄
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

        <!-- 5. DRAMATIC DARK SECTION: HORIZON GLOW & TECH SPECS -->
        <section id="tech" class="py-28 px-6 bg-gradient-to-b from-[#131313] via-[#0d0d0d] to-[#131313] relative overflow-hidden border-t border-white/5">
            
            <!-- Curved Horizon Light Beam Glow -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[300px] bg-gradient-to-b from-accent/20 via-indigo-500/10 to-transparent rounded-b-[100%] blur-3xl pointer-events-none"></div>

            <div class="max-w-7xl mx-auto text-center space-y-16 relative z-10">
                <div class="space-y-4">
                    <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">Universal Post-Production Architecture</span>
                    <h2 class="text-4xl sm:text-6xl font-editorial font-bold text-gray-100">Engineered for Video Teams</h2>
                    <p class="text-gray-400 text-sm sm:text-base max-w-xl mx-auto">High-performance video streaming powered by chunked uploads, FFmpeg background queue workers, and Inertia live polling.</p>
                </div>

                <!-- 4 Spec Pillar Counters -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                    <div class="p-6 bg-[#171616] border border-white/5 rounded-xl space-y-2">
                        <div class="text-3xl sm:text-4xl font-mono-technical font-bold text-accent">0.00s</div>
                        <div class="text-xs font-mono-technical text-gray-400 uppercase tracking-wider">Sync Drift Threshold</div>
                    </div>
                    <div class="p-6 bg-[#171616] border border-white/5 rounded-xl space-y-2">
                        <div class="text-3xl sm:text-4xl font-mono-technical font-bold text-emerald-400">4.0s</div>
                        <div class="text-xs font-mono-technical text-gray-400 uppercase tracking-wider">Background Poll Sync</div>
                    </div>
                    <div class="p-6 bg-[#171616] border border-white/5 rounded-xl space-y-2">
                        <div class="text-3xl sm:text-4xl font-mono-technical font-bold text-indigo-400">10MB</div>
                        <div class="text-xs font-mono-technical text-gray-400 uppercase tracking-wider">Chunked Stream Uploads</div>
                    </div>
                    <div class="p-6 bg-[#171616] border border-white/5 rounded-xl space-y-2">
                        <div class="text-3xl sm:text-4xl font-mono-technical font-bold text-sky-400">100%</div>
                        <div class="text-xs font-mono-technical text-gray-400 uppercase tracking-wider">Browser Native Player</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. STEP-BY-STEP WORKFLOW TIMELINE -->
        <section id="workflow" class="py-24 px-6 max-w-7xl mx-auto relative z-10 space-y-16">
            <div class="text-center space-y-4">
                <span class="text-xs font-mono-technical uppercase tracking-widest text-accent font-bold">End-to-End Workflow</span>
                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100">From raw edit to final sign-off in 4 steps.</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Step 1 -->
                <div class="p-6 bg-[#1a1a1a]/40 border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-accent">01</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Upload Draft</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Chunked background upload processes large video files into streamable H.264 formats.</p>
                </div>

                <!-- Step 2 -->
                <div class="p-6 bg-[#1a1a1a]/40 border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-emerald-400">02</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Send Magic Link</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Copy and send the passwordless share link to clients. Instant access on mobile or desktop.</p>
                </div>

                <!-- Step 3 -->
                <div class="p-6 bg-[#1a1a1a]/40 border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-indigo-400">03</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Collect Revisions</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Clients leave frame-locked comments and paste screenshots. Editor views live sync automatically.</p>
                </div>

                <!-- Step 4 -->
                <div class="p-6 bg-[#1a1a1a]/40 border border-white/5 rounded-xl space-y-3 relative">
                    <span class="text-3xl font-mono-technical font-bold text-sky-400">04</span>
                    <h4 class="font-editorial font-bold text-gray-200 text-lg">Get PDF Sign-off</h4>
                    <p class="text-xs text-gray-400 leading-relaxed">Client signs off on final cut. Project locks and generates a verified approval PDF certificate.</p>
                </div>
            </div>
        </section>

        <!-- 7. TESTIMONIAL / CUSTOMER QUOTE HIGHLIGHT -->
        <section class="py-16 px-6 max-w-5xl mx-auto relative z-10">
            <div class="glass-card p-8 sm:p-12 bg-[#1a1a1a]/50 border border-white/10 rounded-2xl text-center space-y-6 shadow-2xl">
                <div class="text-accent text-3xl font-serif">“</div>
                <blockquote class="text-xl sm:text-2xl font-editorial font-bold text-gray-100 max-w-3xl mx-auto leading-relaxed">
                    RevisionRoom cut our commercial video review cycles from 5 days down to 4 hours. The dual sync comparison player completely stopped client confusion on version changes.
                </blockquote>
                <div class="flex flex-col items-center gap-1 font-mono-technical text-xs">
                    <span class="text-gray-200 font-bold">Marcus Vance</span>
                    <span class="text-gray-400">Lead Post Producer // Apex Media Studio</span>
                </div>
            </div>
        </section>

        <!-- 8. BIG CTA BANNER WITH CURVED ARC GLOW -->
        <section class="py-20 px-6 max-w-7xl mx-auto relative z-10">
            <div class="glass-card p-10 sm:p-16 bg-gradient-to-b from-[#1c1b1b] to-[#131313] border border-white/10 rounded-3xl text-center space-y-8 relative overflow-hidden shadow-2xl">
                <!-- Arc Glow background -->
                <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[600px] h-[200px] bg-accent/20 rounded-b-full blur-3xl pointer-events-none"></div>

                <h2 class="text-3xl sm:text-5xl font-editorial font-bold text-gray-100 leading-tight">
                    Start reviewing video drafts <br class="hidden sm:block" />
                    with your clients today.
                </h2>
                <p class="text-sm sm:text-base text-gray-400 max-w-xl mx-auto font-sans">
                    Join video editors, post houses, and agencies already using RevisionRoom.
                </p>

                <div class="pt-4 flex justify-center items-center gap-4 flex-wrap">
                    <Link v-if="!auth_user" :href="route('register')" class="btn-primary py-4 px-9 text-sm font-bold font-mono-technical uppercase tracking-wider text-[#131313] shadow-[0_0_30px_rgba(203,251,69,0.4)] hover:scale-105 transition-all">
                        Create Free Workspace →
                    </Link>
                    <Link v-else :href="route('dashboard')" class="btn-primary py-4 px-9 text-sm font-bold font-mono-technical uppercase tracking-wider text-[#131313] shadow-[0_0_30px_rgba(203,251,69,0.4)] hover:scale-105 transition-all">
                        Go to Dashboard →
                    </Link>
                </div>
            </div>
        </section>

        <!-- 9. MULTI-COLUMN FOOTER -->
        <footer class="border-t border-white/5 py-16 px-6 bg-[#0f0e0e] text-xs text-gray-500 font-mono-technical">
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-5 gap-10">
                
                <!-- Col 1: Brand Info -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded bg-accent flex items-center justify-center text-[#131313] font-black text-sm">
                            R
                        </div>
                        <span class="text-lg font-editorial font-bold text-gray-100">RevisionRoom</span>
                    </div>
                    <p class="text-gray-400 text-xs max-w-sm font-sans leading-relaxed">
                        The next-generation video review and client approval platform for post-production creators.
                    </p>
                    <div class="text-gray-600 text-[11px]">
                        © 2026 RevisionRoom Inc. All rights reserved.
                    </div>
                </div>

                <!-- Col 2: Product -->
                <div class="space-y-3">
                    <span class="font-bold uppercase tracking-wider text-gray-300 text-[10px]">Product</span>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#compare" class="hover:text-accent transition-colors">Sync Player</a></li>
                        <li><a href="#features" class="hover:text-accent transition-colors">Clipboard Paste</a></li>
                        <li><a href="#features" class="hover:text-accent transition-colors">Magic Share Links</a></li>
                        <li><a href="#features" class="hover:text-accent transition-colors">PDF Sign-offs</a></li>
                    </ul>
                </div>

                <!-- Col 3: Workflows -->
                <div class="space-y-3">
                    <span class="font-bold uppercase tracking-wider text-gray-300 text-[10px]">Workflows</span>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#workflow" class="hover:text-accent transition-colors">Video Editors</a></li>
                        <li><a href="#workflow" class="hover:text-accent transition-colors">Motion Design</a></li>
                        <li><a href="#workflow" class="hover:text-accent transition-colors">Commercial Agencies</a></li>
                        <li><a href="#workflow" class="hover:text-accent transition-colors">Post Houses</a></li>
                    </ul>
                </div>

                <!-- Col 4: Account / Portal -->
                <div class="space-y-3">
                    <span class="font-bold uppercase tracking-wider text-gray-300 text-[10px]">Access</span>
                    <ul class="space-y-2 text-gray-400">
                        <li><Link :href="route('login')" class="hover:text-accent transition-colors">Log In</Link></li>
                        <li><Link :href="route('register')" class="hover:text-accent transition-colors">Register</Link></li>
                        <li><Link v-if="auth_user" :href="route('dashboard')" class="hover:text-accent transition-colors">Dashboard</Link></li>
                    </ul>
                </div>

            </div>
        </footer>

    </div>
</template>
