<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const isLoaded = ref(false);
const mouseX = ref(0);
const mouseY = ref(0);
const particles = ref<Array<{ id: number; x: number; y: number; size: number; duration: number; delay: number }>>([]);

const generateParticles = () => {
    particles.value = Array.from({ length: 50 }, (_, i) => ({
        id: i,
        x: Math.random() * 100,
        y: Math.random() * 100,
        size: Math.random() * 4 + 1,
        duration: Math.random() * 20 + 10,
        delay: Math.random() * 5,
    }));
};

const handleMouseMove = (e: MouseEvent) => {
    mouseX.value = (e.clientX / window.innerWidth) * 100;
    mouseY.value = (e.clientY / window.innerHeight) * 100;
};

onMounted(() => {
    generateParticles();
    setTimeout(() => { isLoaded.value = true; }, 100);
    window.addEventListener('mousemove', handleMouseMove);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
});

const features = [
    { icon: 'enterprise', title: 'Full Enterprise Apps', desc: 'Complete business applications with complex workflows, not just prototypes' },
    { icon: 'pipeline', title: 'Run Paths & Pipelines', desc: 'Automated CI/CD, testing pipelines, and deployment workflows built-in' },
    { icon: 'security', title: 'Enterprise Security', desc: 'SOC2 compliant, role-based access, audit logs, and encryption' },
    { icon: 'scale', title: 'Scalable Architecture', desc: 'Microservices, load balancing, and auto-scaling from day one' },
    { icon: 'api', title: 'API-First Design', desc: 'RESTful & GraphQL APIs with documentation auto-generated' },
    { icon: 'framework', title: 'Modern Frameworks', desc: 'Laravel, Vue, React, Next.js, and more - your choice' },
];
</script>

<template>
    <Head title="Maestro AI - Enterprise App Builder">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-[#fafbfc] font-['Inter',sans-serif] text-slate-900 overflow-x-hidden">
        <!-- Animated Background -->
        <div class="fixed inset-0 pointer-events-none overflow-hidden">
            <div
                class="absolute w-[800px] h-[800px] rounded-full opacity-30 blur-[120px] transition-all duration-1000 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(99,102,241,0.4) 0%, rgba(139,92,246,0.2) 50%, transparent 70%)',
                    left: `${mouseX * 0.3}%`,
                    top: `${mouseY * 0.3 - 20}%`,
                }"
            ></div>
            <div
                class="absolute w-[600px] h-[600px] rounded-full opacity-25 blur-[100px] transition-all duration-[1500ms] ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(14,165,233,0.4) 0%, rgba(6,182,212,0.2) 50%, transparent 70%)',
                    right: `${(100 - mouseX) * 0.2}%`,
                    top: `${mouseY * 0.4 + 10}%`,
                }"
            ></div>

            <div
                v-for="particle in particles"
                :key="particle.id"
                class="absolute rounded-full bg-gradient-to-r from-indigo-500/20 to-cyan-500/20"
                :style="{
                    width: `${particle.size}px`,
                    height: `${particle.size}px`,
                    left: `${particle.x}%`,
                    top: `${particle.y}%`,
                    animation: `float ${particle.duration}s ease-in-out infinite`,
                    animationDelay: `${particle.delay}s`,
                }"
            ></div>

            <div class="absolute inset-0 bg-[linear-gradient(rgba(99,102,241,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(99,102,241,0.03)_1px,transparent_1px)] bg-[size:60px_60px]"></div>
        </div>

        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50">
            <div class="mx-4 mt-4">
                <div class="max-w-7xl mx-auto px-6 py-4 bg-white/70 backdrop-blur-xl rounded-2xl border border-slate-200/50 shadow-lg shadow-slate-200/20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/30">
                                    <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                                    </svg>
                                </div>
                                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white animate-pulse"></div>
                            </div>
                            <div>
                                <span class="text-xl font-bold bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 bg-clip-text text-transparent">Maestro</span>
                                <span class="text-xl font-light text-indigo-600 ml-1">AI</span>
                            </div>
                        </div>

                        <nav class="hidden md:flex items-center gap-1">
                            <a href="#features" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-all">Features</a>
                            <a href="#frameworks" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-all">Frameworks</a>
                            <a href="#enterprise" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-all">Enterprise</a>
                            <a href="https://laravel.com/docs" target="_blank" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-all">Docs</a>
                        </nav>

                        <div class="flex items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboard()"
                                class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 rounded-xl hover:shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-0.5 transition-all duration-300"
                            >
                                Dashboard
                            </Link>
                            <template v-else>
                                <Link :href="login()" class="px-4 py-2 text-sm font-medium text-slate-700 hover:text-slate-900 transition-colors">
                                    Sign in
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="group relative px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 rounded-xl overflow-hidden hover:shadow-lg hover:shadow-indigo-500/30 hover:-translate-y-0.5 transition-all duration-300"
                                >
                                    <span class="relative z-10">Start Building</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-violet-600 to-indigo-600 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative pt-40 pb-20">
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div :class="['flex justify-center mb-8 transition-all duration-700', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                    <div class="inline-flex items-center gap-3 px-5 py-2.5 bg-white rounded-full border border-slate-200 shadow-lg shadow-slate-200/50">
                        <div class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
                            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
                        </div>
                        <span class="text-sm font-medium text-slate-700">Built for Developers & Enterprises</span>
                        <span class="px-2.5 py-1 text-xs font-bold text-indigo-600 bg-indigo-50 rounded-full">v2.0</span>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h1 :class="['text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight mb-6 transition-all duration-700 delay-100', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                        <span class="block text-slate-900">Build Enterprise Apps</span>
                        <span class="block mt-2">
                            <span class="relative">
                                <span class="bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-600 bg-clip-text text-transparent">with AI Precision</span>
                                <svg class="absolute -bottom-2 left-0 w-full h-3 text-indigo-600/30" viewBox="0 0 200 12" preserveAspectRatio="none">
                                    <path d="M0,8 Q50,0 100,8 T200,8" fill="none" stroke="currentColor" stroke-width="3"/>
                                </svg>
                            </span>
                        </span>
                    </h1>

                    <p :class="['text-lg sm:text-xl text-slate-600 max-w-3xl mx-auto leading-relaxed transition-all duration-700 delay-200', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                        Not just vibe coding — <span class="font-semibold text-slate-900">full production-ready applications</span> with
                        complete run paths, CI/CD pipelines, and modern frameworks.
                        <span class="text-indigo-600 font-semibold">From idea to deployment in minutes.</span>
                    </p>
                </div>

                <div :class="['flex flex-col sm:flex-row items-center justify-center gap-4 mb-16 transition-all duration-700 delay-300', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                    <Link
                        :href="register()"
                        class="group relative px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl overflow-hidden shadow-xl shadow-indigo-500/30 hover:shadow-2xl hover:shadow-indigo-500/40 hover:-translate-y-1 transition-all duration-300"
                    >
                        <span class="relative z-10 flex items-center gap-2">
                            Start Building Free
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-violet-600 to-purple-600 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </Link>
                    <a href="https://laracasts.com" target="_blank" class="px-8 py-4 text-base font-semibold text-slate-700 bg-white border-2 border-slate-200 rounded-2xl hover:border-slate-300 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Demo
                    </a>
                </div>

                <!-- Code Preview -->
                <div :class="['relative max-w-4xl mx-auto transition-all duration-1000 delay-400', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8']">
                    <div class="absolute -inset-4 bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500 rounded-3xl opacity-20 blur-2xl animate-pulse"></div>
                    <div class="relative bg-slate-900 rounded-2xl shadow-2xl overflow-hidden border border-slate-700">
                        <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            </div>
                            <div class="flex items-center gap-2 px-4 py-1.5 bg-slate-700 rounded-lg">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                </svg>
                                <span class="text-sm text-slate-400 font-mono">maestro.ai/build</span>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium text-green-400 bg-green-500/20 rounded">Live</span>
                        </div>

                        <div class="p-6 font-['JetBrains_Mono',monospace] text-sm">
                            <div class="flex items-center gap-2 text-slate-400 mb-3">
                                <span class="text-green-400">➜</span>
                                <span class="text-cyan-400">~/enterprise-app</span>
                                <span class="text-slate-500">git:(main)</span>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-violet-400">$</span>
                                    <span class="text-white typing-animation">maestro build "CRM with full auth, dashboard, API endpoints"</span>
                                </div>
                                <div class="text-slate-500 ml-4"><span class="text-green-400">✓</span> Analyzing requirements...</div>
                                <div class="text-slate-500 ml-4"><span class="text-green-400">✓</span> Generating Laravel backend with Sanctum auth</div>
                                <div class="text-slate-500 ml-4"><span class="text-green-400">✓</span> Creating Vue.js dashboard components</div>
                                <div class="text-slate-500 ml-4"><span class="text-green-400">✓</span> Setting up database migrations & seeders</div>
                                <div class="text-slate-500 ml-4"><span class="text-green-400">✓</span> Configuring CI/CD pipeline</div>
                                <div class="text-slate-500 ml-4"><span class="text-cyan-400 animate-pulse">●</span> Deploying to production...</div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-slate-700 flex items-center justify-between">
                                <span class="text-green-400">🚀 App deployed successfully!</span>
                                <span class="text-slate-500">2m 34s</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Frameworks Section -->
        <section id="frameworks" class="py-20 relative">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">Modern Stack</h2>
                    <p class="text-3xl sm:text-4xl font-bold text-slate-900">Your Favorite Frameworks, <span class="text-indigo-600">Supercharged</span></p>
                </div>

                <div class="flex flex-wrap justify-center gap-4">
                    <!-- Laravel -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-red-300 hover:shadow-xl hover:shadow-red-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 50 52" fill="none">
                                <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068zm-1.574 10.718v-9.124l-3.363 1.936-4.646 2.675v9.124l8.01-4.611zm-9.61 16.505v-9.13l-4.57 2.61-13.05 7.448v9.216l17.62-10.144zM1.602 7.719v31.068L19.22 48.93v-9.214l-9.204-5.209-.003-.002-.004-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-21.483L4.965 9.654 1.602 7.72zm8.81-5.994L2.405 6.334l8.005 4.609 8.006-4.61-8.006-4.608zm4.164 28.764l4.645-2.674V7.719l-3.363 1.936-4.646 2.675v20.096l3.364-1.937zM39.243 7.164l-8.006 4.609 8.006 4.609 8.005-4.61-8.005-4.608zm-.801 10.605l-4.646-2.675-3.363-1.936v9.124l4.645 2.674 3.364 1.937v-9.124zM20.02 38.33l11.743-6.704 5.87-3.35-8-4.606-9.211 5.303-8.395 4.833 7.993 4.524z" fill="#FF2D20"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-red-600 transition-colors">Laravel</span>
                        </div>
                    </div>

                    <!-- Vue.js -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-emerald-300 hover:shadow-xl hover:shadow-emerald-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 221">
                                <path d="M204.8 0H256L128 220.8 0 0h97.92L128 51.2 157.44 0h47.36z" fill="#41B883"/>
                                <path d="M0 0l128 220.8L256 0h-51.2L128 132.48 50.56 0H0z" fill="#41B883"/>
                                <path d="M50.56 0L128 133.12 204.8 0h-47.36L128 51.2 97.92 0H50.56z" fill="#35495E"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-emerald-600 transition-colors">Vue.js</span>
                        </div>
                    </div>

                    <!-- React -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-cyan-300 hover:shadow-xl hover:shadow-cyan-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 228">
                                <path d="M210.483 73.824a171.49 171.49 0 0 0-8.24-2.597c.465-1.9.893-3.777 1.273-5.621 6.238-30.281 2.16-54.676-11.769-62.708-13.355-7.7-35.196.329-57.254 19.526a171.23 171.23 0 0 0-6.375 5.848 155.866 155.866 0 0 0-4.241-3.917C100.759 3.829 77.587-4.822 63.673 3.233 50.33 10.957 46.379 33.89 51.995 62.588a170.974 170.974 0 0 0 1.892 8.48c-3.28.932-6.445 1.924-9.474 2.98C17.309 83.498 0 98.307 0 113.668c0 15.865 18.582 31.778 46.812 41.427a145.52 145.52 0 0 0 6.921 2.165 167.467 167.467 0 0 0-2.01 9.138c-5.354 28.2-1.173 50.591 12.134 58.266 13.744 7.926 36.812-.22 59.273-19.855a145.567 145.567 0 0 0 5.342-4.923 168.064 168.064 0 0 0 6.92 6.314c21.758 18.722 43.246 26.282 56.54 18.586 13.731-7.949 18.194-32.003 12.4-61.268a145.016 145.016 0 0 0-1.535-6.842c1.62-.48 3.21-.985 4.76-1.52 29.151-10.09 48.443-25.958 48.443-42.056 0-15.385-18.059-30.644-45.557-40.478zm-6.315 69.658c-1.344.448-2.72.877-4.124 1.289-3.907-12.155-9.353-25.142-16.126-38.453 6.521-13.004 11.747-25.745 15.514-37.727 2.082.574 4.113 1.172 6.085 1.801 23.503 7.49 38.27 18.899 38.27 28.866 0 10.557-16.046 22.888-39.619 30.224zm-18.29 56.088c-6.028 16.402-14.294 28.586-23.056 34.106-7.938 4.995-15.025 4.427-21.327.789-13.47-7.778-18.746-28.95-13.786-54.076a150.908 150.908 0 0 1 1.835-8.333 223.52 223.52 0 0 0 26.857 2.864 229.855 229.855 0 0 0 21.123 22.238 129.762 129.762 0 0 1-4.764 4.47c-2.387 2.097-4.832 4.04-7.284 5.823l.402.119zm-57.673 4.456c-9.303-4.064-18.07-9.188-26.135-15.275a213.15 213.15 0 0 0 13.492-.867 229.22 229.22 0 0 0 12.643 16.142zm-35.161-73.163c-12.318-21.14-19.767-41.86-20.348-57.103-.265-6.97.932-12.67 3.434-16.79 4.87-8.019 14.584-10.413 27.786-6.814a133.71 133.71 0 0 1 8.208 2.698 235.31 235.31 0 0 0-6.81 22.17c-7.287 6.992-13.933 14.51-19.788 22.458-8.497 11.545-8.99 23.57-1.308 33.381h8.826zm21.545 12.58a207.152 207.152 0 0 1-11.167-16.662c8.036-1.47 16.316-2.457 24.748-2.943v.001a206.23 206.23 0 0 1-13.581 19.604zm13.581 36.656a207.158 207.158 0 0 1-24.748-2.943 207.152 207.152 0 0 1 11.167-16.662 206.23 206.23 0 0 1 13.581 19.605zm19.008-14.478a206.258 206.258 0 0 1-6.01 8.313 203.93 203.93 0 0 1-6.01-8.313 240.886 240.886 0 0 0 6.01-9.044 240.842 240.842 0 0 0 6.01 9.044zm6.998 14.478v-.001a206.23 206.23 0 0 1 13.581-19.604 207.152 207.152 0 0 1 11.167 16.662 207.158 207.158 0 0 1-24.748 2.943zm38.33-33.234a207.152 207.152 0 0 1 11.167 16.662 207.158 207.158 0 0 1-24.748 2.943v.001a206.23 206.23 0 0 1 13.58-19.606zm-19.008-33.89a240.886 240.886 0 0 0-6.01 9.043 240.842 240.842 0 0 0-6.01-9.044 206.258 206.258 0 0 1 6.01-8.312 203.93 203.93 0 0 1 6.01 8.312zm-38.33-14.478a206.23 206.23 0 0 1-13.58 19.605 207.152 207.152 0 0 1-11.168-16.662 207.158 207.158 0 0 1 24.748-2.943zm-38.33 33.234a207.158 207.158 0 0 1-24.748 2.943 207.152 207.152 0 0 1 11.167-16.662 206.23 206.23 0 0 1 13.581 19.72zm-13.58-54.855a170.974 170.974 0 0 1 1.891-8.48 206.258 206.258 0 0 1 8.01-21.908c3.907 12.154 9.354 25.141 16.127 38.452-6.521 13.005-11.748 25.746-15.515 37.728a206.23 206.23 0 0 1-6.01-8.313c-6.52-9.89-6.52-24.203-4.503-37.48zM128 90.808c12.946 0 23.448 10.502 23.448 23.448 0 12.946-10.502 23.448-23.448 23.448-12.946 0-23.448-10.502-23.448-23.448 0-12.946 10.502-23.448 23.448-23.448z" fill="#61DAFB"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-cyan-600 transition-colors">React</span>
                        </div>
                    </div>

                    <!-- Next.js -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-slate-400 hover:shadow-xl hover:shadow-slate-200 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 180 180" fill="none">
                                <mask id="a" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="180" height="180">
                                    <circle cx="90" cy="90" r="90" fill="#000"/>
                                </mask>
                                <g mask="url(#a)">
                                    <circle cx="90" cy="90" r="90" fill="#000"/>
                                    <path d="M149.508 157.52L69.142 54H54v71.97h12.114V69.384l73.885 95.461a90.304 90.304 0 0 0 9.509-7.325z" fill="url(#b)"/>
                                    <rect x="115" y="54" width="12" height="72" fill="url(#c)"/>
                                </g>
                                <defs>
                                    <linearGradient id="b" x1="109" y1="116.5" x2="144.5" y2="160.5" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
                                    </linearGradient>
                                    <linearGradient id="c" x1="121" y1="54" x2="120.799" y2="106.875" gradientUnits="userSpaceOnUse">
                                        <stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-slate-700 transition-colors">Next.js</span>
                        </div>
                    </div>

                    <!-- Node.js -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-green-300 hover:shadow-xl hover:shadow-green-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 289">
                                <path d="M128 288.464c-3.975 0-7.685-1.06-11.13-2.915l-35.247-20.936c-5.3-2.915-2.65-3.975-1.06-4.505 7.155-2.385 8.48-2.915 15.9-7.156.796-.53 1.856-.265 2.65.265l27.032 16.166c1.06.53 2.385.53 3.18 0l105.74-61.217c1.06-.53 1.59-1.59 1.59-2.915V83.08c0-1.325-.53-2.385-1.59-2.915l-105.74-60.953c-1.06-.53-2.385-.53-3.18 0L20.705 80.166c-1.06.53-1.59 1.855-1.59 2.915v122.17c0 1.06.53 2.385 1.59 2.915l28.887 16.695c15.636 7.95 25.44-1.325 25.44-10.6V93.68c0-1.59 1.326-3.18 3.181-3.18h13.516c1.59 0 3.18 1.325 3.18 3.18v120.58c0 20.936-11.396 33.126-31.272 33.126-6.095 0-10.865 0-24.38-6.625l-27.827-15.9C4.24 220.615 0 213.195 0 205.245V83.08c0-7.95 4.24-15.37 11.13-19.345L116.87 2.518c6.625-3.71 15.635-3.71 22.26 0l105.74 61.217c6.89 3.975 11.13 11.396 11.13 19.346v122.17c0 7.95-4.24 15.37-11.13 19.345l-105.74 61.216c-3.445 1.855-7.42 2.65-11.13 2.65z" fill="#539E43"/>
                                <path d="M160.863 204.318c-45.27 0-54.81-20.936-54.81-38.427 0-1.59 1.325-3.18 3.18-3.18h13.78c1.59 0 2.916 1.06 2.916 2.65 2.12 14.045 8.215 20.936 36.307 20.936 22.26 0 31.802-5.035 31.802-16.96 0-6.891-2.65-11.926-37.367-15.372-28.887-2.915-46.847-9.275-46.847-32.33 0-21.467 18.02-34.187 48.172-34.187 33.92 0 50.617 11.66 52.737 37.102 0 .795-.265 1.59-.795 2.385-.53.53-1.325 1.06-2.12 1.06h-13.78c-1.326 0-2.65-1.06-2.916-2.385-3.18-14.575-11.395-19.345-33.126-19.345-24.38 0-27.296 8.48-27.296 14.84 0 7.686 3.445 10.07 36.307 14.31 32.597 4.24 47.907 10.336 47.907 33.127-.265 23.321-19.345 36.571-53.05 36.571z" fill="#539E43"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-green-600 transition-colors">Node.js</span>
                        </div>
                    </div>

                    <!-- Python -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-yellow-300 hover:shadow-xl hover:shadow-yellow-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 255">
                                <defs>
                                    <linearGradient id="py-a" x1="12.959%" x2="79.639%" y1="12.039%" y2="78.201%">
                                        <stop offset="0%" stop-color="#387EB8"/><stop offset="100%" stop-color="#366994"/>
                                    </linearGradient>
                                    <linearGradient id="py-b" x1="19.128%" x2="90.742%" y1="20.579%" y2="88.429%">
                                        <stop offset="0%" stop-color="#FFE052"/><stop offset="100%" stop-color="#FFC331"/>
                                    </linearGradient>
                                </defs>
                                <path d="M126.916.072c-64.832 0-60.784 28.115-60.784 28.115l.072 29.128h61.868v8.745H41.631S.145 61.355.145 126.77c0 65.417 36.21 63.097 36.21 63.097h21.61v-30.356s-1.165-36.21 35.632-36.21h61.362s34.475.557 34.475-33.319V33.97S194.67.072 126.916.072zM92.802 19.66a11.12 11.12 0 0 1 11.13 11.13 11.12 11.12 0 0 1-11.13 11.13 11.12 11.12 0 0 1-11.13-11.13 11.12 11.12 0 0 1 11.13-11.13z" fill="url(#py-a)"/>
                                <path d="M128.757 254.126c64.832 0 60.784-28.115 60.784-28.115l-.072-29.127H127.6v-8.745h86.441s41.486 4.705 41.486-60.712c0-65.416-36.21-63.096-36.21-63.096h-21.61v30.355s1.165 36.21-35.632 36.21h-61.362s-34.475-.557-34.475 33.32v56.013s-5.235 33.897 62.518 33.897zm34.114-19.586a11.12 11.12 0 0 1-11.13-11.13 11.12 11.12 0 0 1 11.13-11.131 11.12 11.12 0 0 1 11.13 11.13 11.12 11.12 0 0 1-11.13 11.13z" fill="url(#py-b)"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-yellow-600 transition-colors">Python</span>
                        </div>
                    </div>

                    <!-- TypeScript -->
                    <div class="group px-6 py-4 bg-white rounded-2xl border border-slate-200 hover:border-blue-300 hover:shadow-xl hover:shadow-blue-100 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 256">
                                <rect width="256" height="256" rx="20" fill="#3178C6"/>
                                <path d="M150.518 200.475v27.62c4.492 2.302 9.805 4.028 15.938 5.179 6.133 1.151 12.597 1.726 19.393 1.726 6.622 0 12.914-.633 18.874-1.899 5.96-1.266 11.187-3.352 15.678-6.257 4.492-2.906 8.048-6.704 10.669-11.394 2.62-4.689 3.93-10.486 3.93-17.391 0-5.006-.749-9.394-2.246-13.163a30.748 30.748 0 0 0-6.479-10.055c-2.821-2.935-6.205-5.567-10.149-7.898-3.945-2.33-8.394-4.531-13.347-6.602-3.628-1.497-6.881-2.949-9.761-4.359-2.879-1.41-5.327-2.848-7.342-4.316-2.016-1.467-3.571-3.021-4.665-4.661-1.094-1.64-1.641-3.495-1.641-5.567 0-1.899.489-3.61 1.468-5.135s2.362-2.834 4.147-3.927c1.785-1.094 3.973-1.942 6.565-2.547 2.591-.604 5.471-.907 8.638-.907 2.246 0 4.665.173 7.256.518 2.591.345 5.182.864 7.773 1.554a53.91 53.91 0 0 1 7.601 2.764 41.59 41.59 0 0 1 6.953 3.927v-25.723c-4.147-1.668-8.727-2.906-13.739-3.711-5.013-.806-10.669-1.209-16.97-1.209-6.535 0-12.739.69-18.612 2.071-5.874 1.381-11.041 3.538-15.503 6.47-4.463 2.935-7.99 6.675-10.583 11.221-2.592 4.545-3.889 9.952-3.889 16.218 0 8.27 2.361 15.33 7.083 21.176 4.723 5.847 11.834 10.702 21.334 14.566 3.8 1.553 7.371 3.078 10.711 4.575 3.341 1.496 6.247 3.078 8.723 4.747 2.476 1.669 4.434 3.538 5.876 5.609 1.441 2.072 2.161 4.446 2.161 7.126 0 1.727-.432 3.339-1.295 4.834a10.852 10.852 0 0 1-3.716 3.711c-1.613 1.036-3.6 1.841-5.961 2.418-2.361.576-5.039.864-8.034.864-5.357 0-10.785-.907-16.282-2.72-5.498-1.813-10.583-4.576-15.256-8.29zm-57.073-89.334h29.494V88.072H50.193v23.07h29.321v89.333h13.931v-89.334z" fill="#FFF"/>
                            </svg>
                            <span class="font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">TypeScript</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 relative">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">Enterprise Ready</h2>
                    <p class="text-3xl sm:text-4xl lg:text-5xl font-bold text-slate-900 mb-4">
                        Beyond Prototypes. <span class="text-indigo-600">Production Ready.</span>
                    </p>
                    <p class="text-lg text-slate-600 max-w-2xl mx-auto">
                        Full-stack applications with enterprise-grade architecture, security, and scalability built-in from the start.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="feature in features" :key="feature.title" class="group relative p-8 bg-white rounded-2xl border border-slate-200 hover:border-transparent hover:shadow-2xl hover:shadow-indigo-100 transition-all duration-500">
                        <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-violet-50 to-purple-50 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative">
                            <!-- Feature Icons -->
                            <div class="w-14 h-14 bg-gradient-to-br from-indigo-100 to-violet-100 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 group-hover:shadow-lg transition-all duration-300">
                                <svg v-if="feature.icon === 'enterprise'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <svg v-if="feature.icon === 'pipeline'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <svg v-if="feature.icon === 'security'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <svg v-if="feature.icon === 'scale'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                                <svg v-if="feature.icon === 'api'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <svg v-if="feature.icon === 'framework'" class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-3 group-hover:text-indigo-600 transition-colors">{{ feature.title }}</h3>
                            <p class="text-slate-600 leading-relaxed">{{ feature.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Enterprise Section -->
        <section id="enterprise" class="py-24 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-slate-50 to-white"></div>
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-widest text-indigo-600 mb-4">For Companies</h2>
                        <p class="text-3xl sm:text-4xl font-bold text-slate-900 mb-6">
                            Enterprise-Grade <span class="text-indigo-600">Development Platform</span>
                        </p>
                        <p class="text-lg text-slate-600 mb-8 leading-relaxed">
                            Accelerate your team's productivity with AI-powered development. Build complex applications in hours, not months.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-slate-700 font-medium">SOC2 Type II Compliant</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-slate-700 font-medium">99.99% Uptime SLA</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-slate-700 font-medium">Dedicated Support & Training</span>
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <div class="absolute -inset-4 bg-gradient-to-r from-indigo-500 to-violet-500 rounded-3xl opacity-10 blur-2xl"></div>
                        <div class="relative bg-white rounded-2xl shadow-2xl border border-slate-200 p-8">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="text-center p-6 bg-slate-50 rounded-xl">
                                    <div class="text-4xl font-black text-indigo-600 mb-2">10x</div>
                                    <div class="text-sm text-slate-600 font-medium">Faster Development</div>
                                </div>
                                <div class="text-center p-6 bg-slate-50 rounded-xl">
                                    <div class="text-4xl font-black text-violet-600 mb-2">50%</div>
                                    <div class="text-sm text-slate-600 font-medium">Cost Reduction</div>
                                </div>
                                <div class="text-center p-6 bg-slate-50 rounded-xl">
                                    <div class="text-4xl font-black text-purple-600 mb-2">24/7</div>
                                    <div class="text-sm text-slate-600 font-medium">AI Assistance</div>
                                </div>
                                <div class="text-center p-6 bg-slate-50 rounded-xl">
                                    <div class="text-4xl font-black text-pink-600 mb-2">∞</div>
                                    <div class="text-sm text-slate-600 font-medium">Scalability</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trusted By Section -->
        <section class="py-20 border-t border-slate-200 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs font-bold uppercase tracking-[0.2em] text-slate-500 mb-12">
                    The #1 professional vibe coding tool trusted by
                </p>
                <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
                    <!-- Accenture -->
                    <svg class="h-8 text-slate-400 hover:text-[#A100FF] transition-colors duration-300" viewBox="0 0 2500 544" fill="currentColor">
                        <path d="M0 516h130.2V243.2L0 516zm193.5 0L405.8 27.8H273.4L61.2 516h132.3zM1487 212.6c-29.6-14.8-61.8-22.2-96.3-22.2-91.4 0-152 60.7-152 152.5s59.6 152.5 152.5 152.5c34.5 0 66.7-7.4 95.8-22.2v-108c-24.8 24.8-53.9 37.1-87.4 37.1-44.5 0-71.6-24.8-71.6-59.3s27.1-59.3 71.6-59.3c33.5 0 62.7 12.3 87.4 37.1V212.6zM1600.8 212.6c-29.6-14.8-61.8-22.2-96.3-22.2-91.4 0-152 60.7-152 152.5s59.6 152.5 152.5 152.5c34.5 0 66.7-7.4 95.8-22.2v-108c-24.8 24.8-53.9 37.1-87.4 37.1-44.5 0-71.6-24.8-71.6-59.3s27.1-59.3 71.6-59.3c33.5 0 62.7 12.3 87.4 37.1V212.6zM2173.8 342.8c0-91.4-59.3-152.5-152.5-152.5s-152.5 61.1-152.5 152.5c0 91.4 59.3 152.5 152.5 152.5s152.5-61.1 152.5-152.5zm-212.3 0c0-34.5 27.1-59.3 59.8-59.3 32.7 0 59.8 24.8 59.8 59.3s-27.1 59.3-59.8 59.3c-32.6.1-59.8-24.8-59.8-59.3zM706.2 342.8c0-91.4-59.3-152.5-152.5-152.5s-152.5 61.1-152.5 152.5c0 91.4 59.3 152.5 152.5 152.5s152.5-61.1 152.5-152.5zm-212.3 0c0-34.5 27.1-59.3 59.8-59.3 32.7 0 59.8 24.8 59.8 59.3s-27.1 59.3-59.8 59.3c-32.7.1-59.8-24.8-59.8-59.3zM2500 197.8h-93.7v-87.5h-93.7v87.5h-47.1v86.5h47.1v107.5c0 71.6 37.1 103.5 127.3 103.5 21.2 0 42.5-2.5 60.2-7.4v-79.1c-12.3 5-25.1 7.4-40 7.4-29.6 0-54.4-7.4-54.4-37.1V284.3H2500v-86.5zM1024 197.8h-93.7v-87.5H836.6v87.5h-47.1v86.5h47.1v107.5c0 71.6 37.1 103.5 127.3 103.5 21.2 0 42.5-2.5 60.2-7.4v-79.1c-12.3 5-25.1 7.4-40 7.4-29.6 0-54.4-7.4-54.4-37.1V284.3H1024v-86.5zM1195.9 200.3c-34.5 0-62.7 17.3-79.1 44.5V197.8h-93.7v298.1h93.7V339.8c0-34.5 19.8-52 54.4-52 12.3 0 24.8 2.5 37.1 7.4v-92.4c-4.4-1.5-8.4-2.5-12.4-2.5zM2326.1 197.8v89l-30.3-44.5c-17.3-25.1-42.2-52-96.5-52-79.1 0-137.5 64.2-137.5 152.5s58.4 152.5 137.5 152.5c54.4 0 79.1-27.1 96.5-52l30.3-44.5v89h93.7V197.8h-93.7zm-76.4 204.4c-32.7 0-59.8-24.8-59.8-59.3s27.1-59.3 59.8-59.3c32.7 0 59.8 24.8 59.8 59.3s-27.1 59.3-59.8 59.3z"/>
                    </svg>

                    <!-- Google -->
                    <svg class="h-8 text-slate-400 hover:text-[#4285F4] transition-colors duration-300" viewBox="0 0 272 92" fill="currentColor">
                        <path d="M115.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18C71.25 34.32 81.24 25 93.5 25s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44S80.99 39.2 80.99 47.18c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
                        <path d="M163.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18c0-12.85 9.99-22.18 22.25-22.18s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44s-12.51 5.46-12.51 13.44c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
                        <path d="M209.75 26.34v39.82c0 16.38-9.66 23.07-21.08 23.07-10.75 0-17.22-7.19-19.66-13.07l8.48-3.53c1.51 3.61 5.21 7.87 11.17 7.87 7.31 0 11.84-4.51 11.84-13v-3.19h-.34c-2.18 2.69-6.38 5.04-11.68 5.04-11.09 0-21.25-9.66-21.25-22.09 0-12.52 10.16-22.26 21.25-22.26 5.29 0 9.49 2.35 11.68 4.96h.34v-3.61h9.25zm-8.56 20.92c0-7.81-5.21-13.52-11.84-13.52-6.72 0-12.35 5.71-12.35 13.52 0 7.73 5.63 13.36 12.35 13.36 6.63 0 11.84-5.63 11.84-13.36z"/>
                        <path d="M225 3v65h-9.5V3h9.5z"/>
                        <path d="M262.02 54.48l7.56 5.04c-2.44 3.61-8.32 9.83-18.48 9.83-12.6 0-22.01-9.74-22.01-22.18 0-13.19 9.49-22.18 20.92-22.18 11.51 0 17.14 9.16 18.98 14.11l1.01 2.52-29.65 12.28c2.27 4.45 5.8 6.72 10.75 6.72 4.96 0 8.4-2.44 10.92-6.14zm-23.27-7.98l19.82-8.23c-1.09-2.77-4.37-4.7-8.23-4.7-4.95 0-11.84 4.37-11.59 12.93z"/>
                        <path d="M35.29 41.41V32H67c.31 1.64.47 3.58.47 5.68 0 7.06-1.93 15.79-8.15 22.01-6.05 6.3-13.78 9.66-24.02 9.66C16.32 69.35.36 53.89.36 34.91.36 15.93 16.32.47 35.3.47c10.5 0 17.98 4.12 23.6 9.49l-6.64 6.64c-4.03-3.78-9.49-6.72-16.97-6.72-13.86 0-24.7 11.17-24.7 25.03 0 13.86 10.84 25.03 24.7 25.03 8.99 0 14.11-3.61 17.39-6.89 2.66-2.66 4.41-6.46 5.1-11.65l-22.49.01z"/>
                    </svg>

                    <!-- Intel -->
                    <svg class="h-8 text-slate-400 hover:text-[#0071C5] transition-colors duration-300" viewBox="0 0 200 80" fill="currentColor">
                        <path d="M170.4 29.8h8v27.3h-8V29.8zm0-12h8v8h-8v-8zm-20.2 12h7.6v3.3c1.8-2.4 4.8-4.1 9-4.1 6.8 0 11.2 4.6 11.2 11.8v16.3h-8V42.4c0-3.4-1.8-5.8-5.2-5.8-3.6 0-6.6 2.4-6.6 7v13.5h-8V29.8zm-32.4 0h8v3.5c1.6-2.4 5-4.3 9-4.3 4.8 0 8 2.1 9.6 5.5 2.2-3.4 5.8-5.5 10.2-5.5 6.4 0 10.8 4.2 10.8 11.4v16.7h-8V42c0-3.6-1.6-5.8-5-5.8-3.4 0-6 2.6-6 7v14h-8V42c0-3.6-1.6-5.8-5-5.8-3.4 0-6 2.6-6 7v14h-8V29.8zm-27.4 0h8v27.3h-8V29.8zm0-12h8v8h-8v-8zM80 29h8v3.3c1.8-2.4 4.8-4.1 9-4.1 6.8 0 11.2 4.6 11.2 11.8v16.3h-8V42.4c0-3.4-1.8-5.8-5.2-5.8-3.6 0-6.6 2.4-6.6 7v13.5h-8V29zm-35.6.8h8v7h8v6.6h-8v10.4c0 1.8.6 2.8 2.4 2.8h5.6v7.2h-7c-6.4 0-9-3.4-9-9.4V43.4h-5.8v-6.6h5.8v-7zm-24.2 27.9c-8.6 0-15-6.2-15-14.3 0-8 6.4-14.2 15-14.2 8.8 0 15.2 6.2 15.2 14.2 0 8-6.4 14.3-15.2 14.3zm0-7c4 0 7-2.8 7-7.3 0-4.4-3-7.2-7-7.2-3.8 0-6.8 2.8-6.8 7.2 0 4.5 3 7.3 6.8 7.3z"/>
                    </svg>

                    <!-- Meta -->
                    <svg class="h-8 text-slate-400 hover:text-[#0081FB] transition-colors duration-300" viewBox="0 0 512 512" fill="currentColor">
                        <path d="M0 256c0 141.385 114.615 256 256 256s256-114.615 256-256S397.385 0 256 0 0 114.615 0 256zm477.867 0c0 60.587-23.592 117.547-66.427 160.427-42.88 42.835-99.84 66.427-160.427 66.427-60.587 0-117.547-23.592-160.427-66.427C47.75 373.547 24.16 316.587 24.16 256c0-60.587 23.59-117.547 66.426-160.427C133.466 52.738 190.426 29.147 251.013 29.147c60.587 0 117.547 23.591 160.427 66.426 42.835 42.88 66.427 99.84 66.427 160.427zM255.854 140.8c-26.538 0-54.955 20.923-78.507 61.867-6.26 10.88-19.2 23.04-38.4 36.693-14.507 10.24-21.547 25.387-21.547 43.52 0 27.307 18.134 53.12 56.747 53.12 31.147 0 56.96-16.64 70.827-16.64 14.08 0 40.96 16.64 71.893 16.64 38.614 0 56.747-25.813 56.747-53.12 0-18.133-7.04-33.28-21.547-43.52-19.2-13.653-32.14-25.813-38.4-36.693-23.552-40.944-51.968-61.867-78.506-61.867z"/>
                    </svg>

                    <!-- Salesforce -->
                    <svg class="h-8 text-slate-400 hover:text-[#00A1E0] transition-colors duration-300" viewBox="0 0 200 140" fill="currentColor">
                        <path d="M83.5 23.5c8.5-8.8 20.3-14.2 33.4-14.2 18.1 0 33.8 10.4 41.5 25.5 6.7-3 14.2-4.6 22.1-4.6 29.5 0 53.5 24 53.5 53.5s-24 53.5-53.5 53.5c-4.4 0-8.7-.5-12.8-1.5-7 11.2-19.5 18.7-33.7 18.7-8.3 0-16-2.5-22.4-6.9-7.4 10.8-19.8 17.9-33.9 17.9-15.3 0-28.7-8.4-35.8-20.8-3.6.9-7.3 1.3-11.2 1.3C14 145.9 0 131.9 0 114.6c0-12.7 7.6-23.6 18.4-28.5-.8-3.5-1.2-7.2-1.2-11C17.2 47 39.3 24.9 67.4 24.9c5.7 0 11.2.9 16.1 2.6z"/>
                    </svg>

                    <!-- Shopify -->
                    <svg class="h-8 text-slate-400 hover:text-[#96BF48] transition-colors duration-300" viewBox="0 0 448 512" fill="currentColor">
                        <path d="M388.32 104.1a4.66 4.66 0 0 0-4.4-4c-2 0-37.23-.8-37.23-.8s-21.61-20.82-29.62-28.83V503.2L442.76 472S388.72 106.5 388.32 104.1zM288.65 70.47a116.67 116.67 0 0 0-7.21-17.61C271 32.85 255.42 22 237 22a15 15 0 0 0-4 .4c-.4-.8-1.2-1.2-1.6-2C223.4 11.63 213 7.63 200.58 8c-24 .8-48 18-67.25 48.83-13.61 21.62-24 48.84-26.82 70.06-27.62 8.4-46.83 14.41-47.23 14.81-14 4.4-14.41 4.8-16 18-1.2 10-38 291.82-38 291.82L307.86 504V65.67a41.66 41.66 0 0 0-4.4.4S## 288.65 70.47zM233.41 87.69c-16 4.8-33.63 10.4-50.84 15.61 4.8-18.82 14.41-37.63 25.62-50 4.4-4.4 10.41-9.61 17.21-12.81 6.4 13.61 8.01 32.83 8.01 47.2zm-36.63-66.06a26.92 26.92 0 0 1 14.41 4c-6.4 3.2-12.81 8.41-18.81 14.41-15.21 16.42-26.82 42-31.62 66.45-14.42 4.41-28.83 8.81-42 12.81C131.33 83.28 163.75 22.43 196.78 21.63zm-78.86 85.28c27.62 76.86 130.85 164.5 175.28 186.52-5.21 54.44-28.42 144.48-122.85 144.48-72.86 0-137.71-54.43-137.71-137.71 0-65.66 36.43-193.29 85.28-193.29z"/>
                    </svg>

                    <!-- Stripe -->
                    <svg class="h-8 text-slate-400 hover:text-[#635BFF] transition-colors duration-300" viewBox="0 0 512 214" fill="currentColor">
                        <path d="M512 110.08c0-36.409-17.636-65.138-51.342-65.138-33.85 0-54.33 28.73-54.33 64.854 0 42.808 24.179 64.426 58.88 64.426 16.925 0 29.725-3.84 39.396-9.244v-28.445c-9.67 4.836-20.764 7.823-34.844 7.823-13.796 0-26.027-4.836-27.591-21.618h69.547c0-1.85.284-9.245.284-12.658zm-70.258-13.511c0-16.071 9.814-22.756 18.774-22.756 8.675 0 17.92 6.685 17.92 22.756h-36.694zm-90.31-51.627c-13.939 0-22.899 6.542-27.876 11.094l-1.85-8.818h-31.288v165.83l35.555-7.537.143-40.249c5.12 3.698 12.657 8.96 25.173 8.96 25.458 0 48.64-20.48 48.64-65.564-.142-41.245-23.609-63.716-48.497-63.716zm-8.534 97.991c-8.391 0-13.37-2.986-16.782-6.684l-.143-52.765c3.698-4.124 8.818-6.968 16.925-6.968 12.942 0 21.902 14.506 21.902 33.137 0 19.058-8.818 33.28-21.902 33.28zM241.493 36.551l35.698-7.68V0l-35.698 7.538zm0 10.809h35.698v124.444h-35.698zm-38.257 10.524L200.96 47.36h-30.72v124.444h35.556V87.467c8.39-10.951 22.613-8.96 27.022-7.396V47.36c-4.551-1.707-21.191-4.836-29.582 10.524zm-71.112-41.386l-34.702 7.395-.142 113.92c0 21.05 15.787 36.551 36.836 36.551 11.662 0 20.195-2.133 24.888-4.693V140.8c-4.55 1.849-27.022 8.391-27.022-12.658V77.653h27.022V47.36h-27.022l.142-30.862zM35.982 83.484c0-5.546 4.551-7.68 12.09-7.68 10.808 0 24.461 3.272 35.27 9.103V51.484c-11.804-4.693-23.466-6.542-35.27-6.542C19.2 44.942 0 60.018 0 85.192c0 39.252 54.044 32.995 54.044 49.92 0 6.541-5.688 8.675-13.653 8.675-11.804 0-26.88-4.836-38.827-11.378v33.849c13.227 5.689 26.596 8.106 38.827 8.106 29.582 0 49.92-14.648 49.92-40.106-.142-42.382-54.329-34.845-54.329-50.778z"/>
                    </svg>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
    25% { transform: translateY(-20px) translateX(10px); opacity: 0.6; }
    50% { transform: translateY(-10px) translateX(-10px); opacity: 0.4; }
    75% { transform: translateY(-30px) translateX(5px); opacity: 0.5; }
}

@keyframes typing {
    from { width: 0; }
    to { width: 100%; }
}

.typing-animation {
    display: inline-block;
    overflow: hidden;
    white-space: nowrap;
    animation: typing 2s steps(60, end) forwards;
    border-right: 2px solid #a78bfa;
}
</style>
