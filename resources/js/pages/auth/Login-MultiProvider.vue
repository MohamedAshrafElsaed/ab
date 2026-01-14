<script setup lang="ts">
/**
 * Auth/Login Page - Multi-Provider Version
 *
 * Alternative version with both Google and GitHub OAuth buttons.
 * Use this if you want to offer users multiple authentication options.
 *
 * To use this version instead of the single-provider version:
 * 1. Rename this file to Login.vue
 * 2. Or update FortifyServiceProvider to point to this component
 */

import GitHubButton from '@/components/GitHubButton.vue';
import GoogleButton from '@/components/GoogleButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Props {
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;
}

const props = withDefaults(defineProps<Props>(), {
    canResetPassword: false,
    canRegister: true,
    status: '',
});

const page = usePage();

const errorMessage = computed(() => {
    const flash = page.props.flash as { error?: string } | undefined;
    return flash?.error || null;
});

const successMessage = computed(() => {
    return props.status || null;
});

const isLoaded = ref(false);
const mouseX = ref(0);
const mouseY = ref(0);
const particles = ref<Array<{ id: number; x: number; y: number; size: number; duration: number; delay: number }>>([]);

const generateParticles = () => {
    particles.value = Array.from({ length: 30 }, (_, i) => ({
        id: i,
        x: Math.random() * 100,
        y: Math.random() * 100,
        size: Math.random() * 3 + 1,
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
    setTimeout(() => {
        isLoaded.value = true;
    }, 100);
    window.addEventListener('mousemove', handleMouseMove);
});

onUnmounted(() => {
    window.removeEventListener('mousemove', handleMouseMove);
});
</script>

<template>
    <Head title="Sign In">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    </Head>

    <div class="flex min-h-screen items-center justify-center overflow-hidden bg-[#1C1917] text-[#FAFAF9]">
        <!-- Animated Background -->
        <div class="pointer-events-none fixed inset-0">
            <div
                class="absolute h-[600px] w-[600px] rounded-full opacity-20 blur-3xl transition-all duration-1000 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(224, 120, 80, 0.4) 0%, transparent 70%)',
                    left: `${mouseX * 0.3 - 15}%`,
                    top: `${mouseY * 0.3 - 15}%`,
                }"
            ></div>
            <div
                class="absolute h-[500px] w-[500px] rounded-full opacity-15 blur-3xl transition-all duration-1500 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(198, 93, 61, 0.3) 0%, transparent 70%)',
                    right: `${(100 - mouseX) * 0.2}%`,
                    bottom: `${(100 - mouseY) * 0.2}%`,
                }"
            ></div>

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

            <div
                class="absolute inset-0 bg-[linear-gradient(rgba(68,64,60,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(68,64,60,0.05)_1px,transparent_1px)] bg-[size:64px_64px]"
            ></div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-md px-4">
            <!-- Logo & Branding -->
            <div :class="['mb-8 text-center transition-all duration-700', isLoaded ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0']">
                <Link href="/" class="group mb-6 inline-flex items-center gap-3">
                    <div class="relative">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#E07850] to-[#C65D3D] shadow-lg shadow-[#E07850]/30 transition-shadow group-hover:shadow-[#E07850]/50"
                        >
                            <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                            </svg>
                        </div>
                        <div class="absolute -top-1 -right-1 h-3 w-3 animate-pulse rounded-full border-2 border-[#1C1917] bg-[#22C55E]"></div>
                    </div>
                    <div>
                        <span class="bg-gradient-to-r from-[#FAFAF9] to-[#A8A29E] bg-clip-text text-2xl font-bold text-transparent">Maestro</span>
                        <span class="ml-1 text-2xl font-light text-[#E07850]">AI</span>
                    </div>
                </Link>
                <p class="text-sm text-[#78716C]">Build enterprise apps with AI precision</p>
            </div>

            <!-- Auth Card -->
            <div :class="['relative transition-all delay-100 duration-700', isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0']">
                <div class="absolute -inset-1 rounded-3xl bg-gradient-to-r from-[#E07850]/20 to-[#C65D3D]/20 opacity-50 blur-xl"></div>

                <div class="relative rounded-2xl border border-[#44403C] bg-[#292524]/90 p-8 shadow-2xl backdrop-blur-xl">
                    <!-- Header -->
                    <div class="mb-8 text-center">
                        <h1 class="mb-2 text-2xl font-bold text-[#FAFAF9]">Welcome</h1>
                        <p class="text-sm text-[#A8A29E]">Choose how you'd like to continue</p>
                    </div>

                    <!-- Success Message -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div v-if="successMessage" class="mb-6 flex items-start gap-3 rounded-xl border border-[#22C55E]/30 bg-[#22C55E]/10 p-4">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#22C55E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p class="text-sm text-[#22C55E]">{{ successMessage }}</p>
                        </div>
                    </Transition>

                    <!-- Error Message -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div v-if="errorMessage" class="mb-6 flex items-start gap-3 rounded-xl border border-[#EF4444]/30 bg-[#EF4444]/10 p-4">
                            <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-[#EF4444]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <p class="text-sm text-[#EF4444]">{{ errorMessage }}</p>
                        </div>
                    </Transition>

                    <!-- OAuth Buttons -->
                    <div class="space-y-3">
                        <!-- Google (primary - more prominent) -->
                        <GoogleButton />

                        <!-- GitHub (secondary option) -->
                        <GitHubButton />
                    </div>

                    <!-- Divider -->
                    <div class="relative my-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-[#44403C]"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="bg-[#292524] px-4 text-xs text-[#78716C]"> Secure authentication </span>
                        </div>
                    </div>

                    <!-- Benefits -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-2 text-xs text-[#A8A29E]">
                            <svg class="h-4 w-4 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                            <span>SOC2 Compliant</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-[#A8A29E]">
                            <svg class="h-4 w-4 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                            <span>Encrypted</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-[#A8A29E]">
                            <svg class="h-4 w-4 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Instant Setup</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-[#A8A29E]">
                            <svg class="h-4 w-4 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>No Password</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                :class="[
                    'mt-8 text-center transition-all delay-200 duration-700',
                    isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-xs text-[#78716C]">
                    By continuing, you agree to our
                    <a href="#" class="text-[#A8A29E] transition-colors hover:text-[#E07850]">Terms</a>
                    and
                    <a href="#" class="text-[#A8A29E] transition-colors hover:text-[#E07850]">Privacy Policy</a>
                </p>
            </div>

            <!-- Back to Home -->
            <div
                :class="[
                    'mt-6 text-center transition-all delay-300 duration-700',
                    isLoaded ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <Link href="/" class="group inline-flex items-center gap-2 text-sm text-[#78716C] transition-colors hover:text-[#FAFAF9]">
                    <svg class="h-4 w-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to home
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float {
    0%,
    100% {
        transform: translateY(0) translateX(0);
    }
    25% {
        transform: translateY(-10px) translateX(5px);
    }
    50% {
        transform: translateY(-20px) translateX(0);
    }
    75% {
        transform: translateY(-10px) translateX(-5px);
    }
}
</style>
