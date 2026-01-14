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
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
    </Head>

    <div class="min-h-screen bg-[#1C1917] text-[#FAFAF9] overflow-hidden">
        <!-- Animated Background -->
        <div class="fixed inset-0 pointer-events-none">
            <!-- Gradient orbs -->
            <div
                class="absolute w-[800px] h-[800px] rounded-full opacity-20 blur-3xl transition-all duration-1000 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(224, 120, 80, 0.4) 0%, transparent 70%)',
                    left: `${mouseX * 0.3 - 20}%`,
                    top: `${mouseY * 0.3 - 20}%`,
                }"
            ></div>
            <div
                class="absolute w-[600px] h-[600px] rounded-full opacity-15 blur-3xl transition-all duration-1500 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(198, 93, 61, 0.3) 0%, transparent 70%)',
                    right: `${(100 - mouseX) * 0.2 - 10}%`,
                    bottom: `${(100 - mouseY) * 0.2 - 10}%`,
                }"
            ></div>

            <!-- Floating particles -->
            <div
                v-for="particle in particles"
                :key="particle.id"
                class="absolute rounded-full bg-[#E07850]/20"
                :style="{
                    left: `${particle.x}%`,
                    top: `${particle.y}%`,
                    width: `${particle.size}px`,
                    height: `${particle.size}px`,
                    animation: `float ${particle.duration}s ease-in-out infinite`,
                    animationDelay: `${particle.delay}s`,
                }"
            ></div>

            <!-- Grid pattern -->
            <div class="absolute inset-0 bg-[linear-gradient(rgba(68,64,60,0.1)_1px,transparent_1px),linear-gradient(90deg,rgba(68,64,60,0.1)_1px,transparent_1px)] bg-[size:64px_64px]"></div>
        </div>

        <!-- Header -->
        <header class="fixed top-0 left-0 right-0 z-50">
            <div class="mx-4 mt-4">
                <div class="max-w-7xl mx-auto px-6 py-4 bg-[#292524]/80 backdrop-blur-xl rounded-2xl border border-[#44403C]/50 shadow-lg shadow-black/20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <div class="w-10 h-10 bg-gradient-to-br from-[#E07850] to-[#C65D3D] rounded-xl flex items-center justify-center shadow-lg shadow-[#E07850]/30">
                                    <svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                                    </svg>
                                </div>
                                <div class="absolute -top-1 -right-1 w-3 h-3 bg-[#22C55E] rounded-full border-2 border-[#292524] animate-pulse"></div>
                            </div>
                            <div>
                                <span class="text-xl font-bold bg-gradient-to-r from-[#FAFAF9] to-[#A8A29E] bg-clip-text text-transparent">Maestro</span>
                                <span class="text-xl font-light text-[#E07850] ml-1">AI</span>
                            </div>
                        </div>

                        <nav class="hidden md:flex items-center gap-1">
                            <a href="#features" class="px-4 py-2 text-sm font-medium text-[#A8A29E] hover:text-[#FAFAF9] hover:bg-[#44403C] rounded-lg transition-all">Features</a>
                            <a href="#frameworks" class="px-4 py-2 text-sm font-medium text-[#A8A29E] hover:text-[#FAFAF9] hover:bg-[#44403C] rounded-lg transition-all">Frameworks</a>
                            <a href="#enterprise" class="px-4 py-2 text-sm font-medium text-[#A8A29E] hover:text-[#FAFAF9] hover:bg-[#44403C] rounded-lg transition-all">Enterprise</a>
                            <a href="https://laravel.com/docs" target="_blank" class="px-4 py-2 text-sm font-medium text-[#A8A29E] hover:text-[#FAFAF9] hover:bg-[#44403C] rounded-lg transition-all">Docs</a>
                        </nav>

                        <div class="flex items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="dashboard()"
                                class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#E07850] to-[#C65D3D] rounded-xl hover:shadow-lg hover:shadow-[#E07850]/30 hover:-translate-y-0.5 transition-all duration-300"
                            >
                                Dashboard
                            </Link>
                            <template v-else>
                                <Link :href="login()" class="px-4 py-2 text-sm font-medium text-[#A8A29E] hover:text-[#FAFAF9] transition-colors">
                                    Sign in
                                </Link>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="group relative px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#E07850] to-[#C65D3D] rounded-xl overflow-hidden hover:shadow-lg hover:shadow-[#E07850]/30 hover:-translate-y-0.5 transition-all duration-300"
                                >
                                    <span class="relative z-10">Start Building</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-[#C65D3D] to-[#A64D36] opacity-0 group-hover:opacity-100 transition-opacity"></div>
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
                    <div class="inline-flex items-center gap-3 px-5 py-2.5 bg-[#292524] rounded-full border border-[#44403C] shadow-lg shadow-black/20">
                        <div class="flex items-center gap-1">
                            <span class="w-2 h-2 bg-[#22C55E] rounded-full animate-pulse"></span>
                            <span class="w-2 h-2 bg-[#22C55E] rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
                            <span class="w-2 h-2 bg-[#22C55E] rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
                        </div>
                        <span class="text-sm font-medium text-[#A8A29E]">Built for Developers & Enterprises</span>
                        <span class="px-2.5 py-1 text-xs font-bold text-[#E07850] bg-[#E07850]/10 rounded-full">v2.0</span>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h1 :class="['text-5xl sm:text-6xl lg:text-7xl font-black tracking-tight mb-6 transition-all duration-700 delay-100', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                        <span class="block text-[#FAFAF9]">Build Enterprise Apps</span>
                        <span class="block mt-2">
                            <span class="relative">
                                <span class="bg-gradient-to-r from-[#E07850] via-[#F59E7B] to-[#E07850] bg-clip-text text-transparent">with AI Precision</span>
                                <svg class="absolute -bottom-2 left-0 w-full h-3 text-[#E07850]/30" viewBox="0 0 200 12" preserveAspectRatio="none">
                                    <path d="M0,8 Q50,0 100,8 T200,8" fill="none" stroke="currentColor" stroke-width="3"/>
                                </svg>
                            </span>
                        </span>
                    </h1>

                    <p :class="['text-lg sm:text-xl text-[#A8A29E] max-w-3xl mx-auto leading-relaxed transition-all duration-700 delay-200', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                        Not just vibe coding — <span class="font-semibold text-[#FAFAF9]">full production-ready applications</span> with
                        complete run paths, CI/CD pipelines, and modern frameworks.
                        <span class="text-[#E07850] font-semibold">From idea to deployment in minutes.</span>
                    </p>
                </div>

                <div :class="['flex flex-col sm:flex-row items-center justify-center gap-4 mb-16 transition-all duration-700 delay-300', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4']">
                    <Link
                        :href="register()"
                        class="group relative px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-[#E07850] to-[#C65D3D] rounded-2xl overflow-hidden shadow-xl shadow-[#E07850]/30 hover:shadow-2xl hover:shadow-[#E07850]/40 hover:-translate-y-1 transition-all duration-300"
                    >
                        <span class="relative z-10 flex items-center gap-2">
                            Start Building Free
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-[#C65D3D] to-[#A64D36] opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </Link>
                    <a href="https://laracasts.com" target="_blank" class="px-8 py-4 text-base font-semibold text-[#FAFAF9] bg-[#292524] border-2 border-[#44403C] rounded-2xl hover:border-[#57534E] hover:shadow-lg hover:shadow-black/20 hover:-translate-y-1 transition-all duration-300 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#E07850]" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Watch Demo
                    </a>
                </div>

                <!-- Code Preview -->
                <div :class="['relative max-w-4xl mx-auto transition-all duration-1000 delay-400', isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-8']">
                    <div class="absolute -inset-4 bg-gradient-to-r from-[#E07850]/20 to-[#C65D3D]/20 rounded-3xl opacity-50 blur-2xl"></div>
                    <div class="relative bg-[#1C1917] rounded-2xl shadow-2xl border border-[#44403C] overflow-hidden">
                        <!-- Terminal Header -->
                        <div class="flex items-center gap-2 px-4 py-3 bg-[#292524] border-b border-[#44403C]">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-[#EF4444]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#F59E0B]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#22C55E]"></div>
                            </div>
                            <span class="ml-4 text-sm text-[#78716C] font-mono">maestro-ai</span>
                        </div>
                        <!-- Terminal Content -->
                        <div class="p-6 font-mono text-sm">
                            <div class="flex items-center gap-2 text-[#78716C] mb-4">
                                <span class="text-[#22C55E]">$</span>
                                <span class="text-[#FAFAF9]">maestro create enterprise-app</span>
                            </div>
                            <div class="space-y-2 text-[#A8A29E]">
                                <p><span class="text-[#22C55E]">✓</span> Analyzing requirements...</p>
                                <p><span class="text-[#22C55E]">✓</span> Generating Laravel backend with API routes</p>
                                <p><span class="text-[#22C55E]">✓</span> Creating Vue.js frontend components</p>
                                <p><span class="text-[#22C55E]">✓</span> Setting up authentication & authorization</p>
                                <p><span class="text-[#22C55E]">✓</span> Configuring CI/CD pipeline</p>
                                <p class="text-[#E07850] font-semibold mt-4">→ Ready to deploy in 3 minutes!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Frameworks Section -->
        <section id="frameworks" class="py-24 relative">
            <div class="absolute inset-0 bg-gradient-to-b from-transparent via-[#292524]/30 to-transparent"></div>
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <p class="text-[#78716C] text-sm uppercase tracking-widest mb-4">Works With Your Stack</p>
                </div>

                <div class="flex flex-wrap justify-center gap-4">
                    <!-- Laravel -->
                    <div class="group px-6 py-4 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#EF4444]/50 hover:shadow-xl hover:shadow-[#EF4444]/10 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 50 52" fill="none">
                                <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.21.006-.023.02-.044.028-.067.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.008.023.022.044.028.068a.809.809 0 0 1 .028.209v20.559l8.008-4.611v-10.51c0-.07.01-.141.028-.208.007-.024.02-.045.028-.068.016-.042.03-.085.052-.124.015-.026.037-.047.054-.071.024-.032.044-.065.072-.093.023-.023.052-.04.078-.06.03-.024.056-.05.088-.069h.001l9.611-5.533a.801.801 0 0 1 .8 0l9.61 5.533c.034.02.06.045.09.068.025.02.054.038.077.06.028.029.048.062.072.094.018.024.04.045.054.071.023.039.036.082.052.124.009.023.022.044.028.068z" fill="#FF2D20"/>
                            </svg>
                            <span class="font-semibold text-[#FAFAF9] group-hover:text-[#EF4444] transition-colors">Laravel</span>
                        </div>
                    </div>

                    <!-- Vue.js -->
                    <div class="group px-6 py-4 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#41B883]/50 hover:shadow-xl hover:shadow-[#41B883]/10 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 221">
                                <path d="M204.8 0H256L128 220.8 0 0h97.92L128 51.2 157.44 0h47.36z" fill="#41B883"/>
                                <path d="M0 0l128 220.8L256 0h-51.2L128 132.48 50.56 0H0z" fill="#41B883"/>
                                <path d="M50.56 0L128 133.12 204.8 0h-47.36L128 51.2 97.92 0H50.56z" fill="#35495E"/>
                            </svg>
                            <span class="font-semibold text-[#FAFAF9] group-hover:text-[#41B883] transition-colors">Vue.js</span>
                        </div>
                    </div>

                    <!-- React -->
                    <div class="group px-6 py-4 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#61DAFB]/50 hover:shadow-xl hover:shadow-[#61DAFB]/10 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8 text-[#61DAFB]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 13.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>
                                <path d="M12 21.35c-1.4 0-2.65-.25-3.65-.7-.95-.4-1.65-1-2.05-1.7-.65-1.15-.55-2.65.3-4.25.85 1 1.9 1.9 3.15 2.65 1.55.95 3.25 1.55 4.9 1.8-.25.8-.6 1.45-1 2-.65.85-1.35 1.2-1.65 1.2Zm0-18.7c.3 0 1 .35 1.65 1.2.4.5.75 1.15 1 2-1.65.25-3.35.85-4.9 1.8-1.25.75-2.3 1.65-3.15 2.65-.85-1.6-.95-3.1-.3-4.25.4-.7 1.1-1.3 2.05-1.7 1-.45 2.25-.7 3.65-.7Z"/>
                                <path d="M5.7 16.65c-.7-1.2-.7-2.85.05-4.65.75 1.15 1.75 2.25 2.95 3.25 1.5 1.2 3.2 2.15 4.95 2.75-.3.75-.65 1.4-1.05 1.95-.8 1.05-1.75 1.65-2.65 1.65-.65 0-1.4-.3-2.2-.85-.9-.6-1.65-1.45-2.05-2.4-.15-.35-.15-.5 0-.7Zm12.6 0c.15.2.15.35 0 .7-.4.95-1.15 1.8-2.05 2.4-.8.55-1.55.85-2.2.85-.9 0-1.85-.6-2.65-1.65-.4-.55-.75-1.2-1.05-1.95 1.75-.6 3.45-1.55 4.95-2.75 1.2-1 2.2-2.1 2.95-3.25.75 1.8.75 3.45.05 4.65Z"/>
                            </svg>
                            <span class="font-semibold text-[#FAFAF9] group-hover:text-[#61DAFB] transition-colors">React</span>
                        </div>
                    </div>

                    <!-- TypeScript -->
                    <div class="group px-6 py-4 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#3178C6]/50 hover:shadow-xl hover:shadow-[#3178C6]/10 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 256 256">
                                <rect width="256" height="256" rx="20" fill="#3178C6"/>
                                <path d="M150.518 200.475v27.62c4.492 2.302 9.805 4.028 15.938 5.179 6.133 1.151 12.597 1.726 19.393 1.726 6.622 0 12.914-.633 18.874-1.899 5.96-1.266 11.187-3.352 15.678-6.257 4.492-2.906 8.048-6.704 10.669-11.394 2.62-4.689 3.93-10.486 3.93-17.391 0-5.006-.749-9.394-2.246-13.163a30.748 30.748 0 0 0-6.479-10.055c-2.821-2.935-6.205-5.567-10.149-7.898-3.945-2.33-8.394-4.531-13.347-6.602-3.628-1.497-6.881-2.949-9.761-4.359-2.879-1.41-5.327-2.848-7.342-4.316-2.016-1.467-3.571-3.021-4.665-4.661-1.094-1.64-1.641-3.495-1.641-5.567 0-1.899.489-3.61 1.468-5.135s2.362-2.834 4.147-3.927c1.785-1.094 3.973-1.942 6.565-2.547 2.591-.604 5.471-.907 8.638-.907 2.246 0 4.665.173 7.256.518 2.591.345 5.182.864 7.773 1.554a53.91 53.91 0 0 1 7.601 2.764 41.59 41.59 0 0 1 6.953 3.927v-25.723c-4.147-1.668-8.727-2.906-13.739-3.711-5.013-.806-10.669-1.209-16.97-1.209-6.535 0-12.739.69-18.612 2.071-5.874 1.381-11.041 3.538-15.503 6.47-4.463 2.935-7.99 6.675-10.583 11.221-2.592 4.545-3.889 9.952-3.889 16.218 0 8.27 2.361 15.33 7.083 21.176 4.723 5.847 11.834 10.702 21.334 14.566 3.8 1.553 7.371 3.078 10.711 4.575 3.341 1.496 6.247 3.078 8.723 4.747 2.476 1.669 4.434 3.538 5.876 5.609 1.441 2.072 2.161 4.446 2.161 7.126 0 1.727-.432 3.339-1.295 4.834a10.852 10.852 0 0 1-3.716 3.711c-1.613 1.036-3.6 1.841-5.961 2.418-2.361.576-5.039.864-8.034.864-5.357 0-10.785-.907-16.282-2.72-5.498-1.813-10.583-4.576-15.256-8.29zm-57.073-89.334h29.494V88.072H50.193v23.07h29.321v89.333h13.931v-89.334z" fill="#FFF"/>
                            </svg>
                            <span class="font-semibold text-[#FAFAF9] group-hover:text-[#3178C6] transition-colors">TypeScript</span>
                        </div>
                    </div>

                    <!-- Tailwind -->
                    <div class="group px-6 py-4 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#06B6D4]/50 hover:shadow-xl hover:shadow-[#06B6D4]/10 hover:-translate-y-1 transition-all duration-300">
                        <div class="flex items-center gap-3">
                            <svg class="w-8 h-8" viewBox="0 0 54 33" fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M27 0c-7.2 0-11.7 3.6-13.5 10.8 2.7-3.6 5.85-4.95 9.45-4.05 2.054.514 3.522 2.004 5.147 3.653C30.744 13.09 33.808 16.2 40.5 16.2c7.2 0 11.7-3.6 13.5-10.8-2.7 3.6-5.85 4.95-9.45 4.05-2.054-.514-3.522-2.004-5.147-3.653C36.756 3.11 33.692 0 27 0zM13.5 16.2C6.3 16.2 1.8 19.8 0 27c2.7-3.6 5.85-4.95 9.45-4.05 2.054.514 3.522 2.004 5.147 3.653C17.244 29.29 20.308 32.4 27 32.4c7.2 0 11.7-3.6 13.5-10.8-2.7 3.6-5.85 4.95-9.45 4.05-2.054-.514-3.522-2.004-5.147-3.653C23.256 19.31 20.192 16.2 13.5 16.2z" fill="#06B6D4"/>
                            </svg>
                            <span class="font-semibold text-[#FAFAF9] group-hover:text-[#06B6D4] transition-colors">Tailwind</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-24 relative">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-[#E07850] mb-4">Enterprise Ready</h2>
                    <p class="text-3xl sm:text-4xl lg:text-5xl font-bold text-[#FAFAF9] mb-4">
                        Beyond Prototypes. <span class="text-[#E07850]">Production Ready.</span>
                    </p>
                    <p class="text-lg text-[#A8A29E] max-w-2xl mx-auto">
                        Full-stack applications with enterprise-grade architecture, security, and scalability built-in from the start.
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="feature in features" :key="feature.title" class="group relative p-8 bg-[#292524] rounded-2xl border border-[#44403C] hover:border-[#57534E] hover:shadow-2xl hover:shadow-[#E07850]/5 transition-all duration-500">
                        <div class="absolute inset-0 bg-gradient-to-br from-[#E07850]/5 via-transparent to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative">
                            <!-- Feature Icons -->
                            <div class="w-14 h-14 bg-gradient-to-br from-[#E07850]/20 to-[#C65D3D]/20 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 group-hover:shadow-lg group-hover:shadow-[#E07850]/20 transition-all duration-300">
                                <svg v-if="feature.icon === 'enterprise'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <svg v-if="feature.icon === 'pipeline'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <svg v-if="feature.icon === 'security'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <svg v-if="feature.icon === 'scale'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                                <svg v-if="feature.icon === 'api'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <svg v-if="feature.icon === 'framework'" class="w-7 h-7 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-[#FAFAF9] mb-3 group-hover:text-[#E07850] transition-colors">{{ feature.title }}</h3>
                            <p class="text-[#A8A29E] leading-relaxed">{{ feature.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Enterprise Section -->
        <section id="enterprise" class="py-24 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-b from-[#292524]/50 to-transparent"></div>
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-widest text-[#E07850] mb-4">For Companies</h2>
                        <p class="text-3xl sm:text-4xl font-bold text-[#FAFAF9] mb-6">
                            Enterprise-Grade <span class="text-[#E07850]">Development Platform</span>
                        </p>
                        <p class="text-lg text-[#A8A29E] mb-8 leading-relaxed">
                            Accelerate your team's productivity with AI-powered development. Build complex applications in hours, not months.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-[#22C55E]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#22C55E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-[#FAFAF9] font-medium">SOC2 Type II Compliant</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-[#22C55E]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#22C55E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-[#FAFAF9] font-medium">99.99% Uptime SLA</span>
                            </li>
                            <li class="flex items-center gap-3">
                                <div class="w-6 h-6 bg-[#22C55E]/10 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#22C55E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <span class="text-[#FAFAF9] font-medium">Dedicated Support & Training</span>
                            </li>
                        </ul>
                    </div>
                    <div class="relative">
                        <div class="absolute -inset-4 bg-gradient-to-r from-[#E07850]/20 to-[#C65D3D]/20 rounded-3xl opacity-30 blur-2xl"></div>
                        <div class="relative bg-[#292524] rounded-2xl shadow-2xl border border-[#44403C] p-8">
                            <div class="grid grid-cols-2 gap-6">
                                <div class="text-center">
                                    <p class="text-4xl font-bold text-[#E07850]">10x</p>
                                    <p class="text-[#78716C] text-sm mt-1">Faster Development</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-4xl font-bold text-[#E07850]">99.9%</p>
                                    <p class="text-[#78716C] text-sm mt-1">Uptime Guarantee</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-4xl font-bold text-[#E07850]">24/7</p>
                                    <p class="text-[#78716C] text-sm mt-1">Enterprise Support</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-4xl font-bold text-[#E07850]">∞</p>
                                    <p class="text-[#78716C] text-sm mt-1">Scalability</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-24 relative">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl sm:text-4xl font-bold text-[#FAFAF9] mb-6">
                    Ready to Build Something Amazing?
                </h2>
                <p class="text-lg text-[#A8A29E] mb-8 max-w-2xl mx-auto">
                    Join thousands of developers building enterprise applications with AI-powered precision.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        :href="register()"
                        class="group relative px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-[#E07850] to-[#C65D3D] rounded-2xl overflow-hidden shadow-xl shadow-[#E07850]/30 hover:shadow-2xl hover:shadow-[#E07850]/40 hover:-translate-y-1 transition-all duration-300"
                    >
                        <span class="relative z-10 flex items-center gap-2">
                            Get Started Free
                            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </span>
                        <div class="absolute inset-0 bg-gradient-to-r from-[#C65D3D] to-[#A64D36] opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </Link>
                    <a href="#" class="px-8 py-4 text-base font-semibold text-[#FAFAF9] bg-[#292524] border-2 border-[#44403C] rounded-2xl hover:border-[#57534E] hover:shadow-lg hover:shadow-black/20 hover:-translate-y-1 transition-all duration-300">
                        Talk to Sales
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-12 border-t border-[#292524]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-[#E07850] to-[#C65D3D] rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <span class="text-lg font-bold text-[#FAFAF9]">Maestro AI</span>
                    </div>
                    <div class="flex items-center gap-6">
                        <a href="#" class="text-sm text-[#78716C] hover:text-[#FAFAF9] transition-colors">Privacy</a>
                        <a href="#" class="text-sm text-[#78716C] hover:text-[#FAFAF9] transition-colors">Terms</a>
                        <a href="#" class="text-sm text-[#78716C] hover:text-[#FAFAF9] transition-colors">Contact</a>
                    </div>
                    <p class="text-sm text-[#78716C]">© 2025 Maestro AI. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-20px);
    }
}
</style>
