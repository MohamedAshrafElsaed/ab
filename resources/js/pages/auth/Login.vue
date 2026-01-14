<script setup lang="ts">
/**
 * Auth/Login Page
 *
 * A clean, modern authentication page with Google OAuth.
 * Handles both login and registration seamlessly - new users
 * are automatically registered, existing users are logged in.
 *
 * Backend route: /auth/google (redirects to Google OAuth)
 * Callback handled by: SocialAuthController::callback
 */

import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import GoogleButton from '@/components/GoogleButton.vue';

// Props passed from FortifyServiceProvider
interface Props {
    canResetPassword?: boolean;
    canRegister?: boolean;
    status?: string;  // Flash message for success states
}

const props = withDefaults(defineProps<Props>(), {
    canResetPassword: false,
    canRegister: true,
    status: '',
});

// Access flash messages and errors from Inertia
const page = usePage();

// Computed property for error messages (from OAuth callback)
const errorMessage = computed(() => {
    const flash = page.props.flash as { error?: string } | undefined;
    return flash?.error || null;
});

// Computed property for success messages
const successMessage = computed(() => {
    return props.status || null;
});

// Animation state
const isLoaded = ref(false);
const mouseX = ref(0);
const mouseY = ref(0);

// Floating particles for background animation
const particles = ref<Array<{
    id: number;
    x: number;
    y: number;
    size: number;
    duration: number;
    delay: number
}>>([]);

/**
 * Generate random particles for the animated background
 */
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

/**
 * Track mouse movement for parallax effect on gradient orbs
 */
const handleMouseMove = (e: MouseEvent) => {
    mouseX.value = (e.clientX / window.innerWidth) * 100;
    mouseY.value = (e.clientY / window.innerHeight) * 100;
};

onMounted(() => {
    generateParticles();
    // Slight delay for entrance animation
    setTimeout(() => { isLoaded.value = true; }, 100);
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

    <div class="min-h-screen bg-[#1C1917] text-[#FAFAF9] overflow-hidden flex items-center justify-center">
        <!-- Animated Background -->
        <div class="fixed inset-0 pointer-events-none">
            <!-- Gradient orbs that follow mouse -->
            <div
                class="absolute w-[600px] h-[600px] rounded-full opacity-20 blur-3xl transition-all duration-1000 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(224, 120, 80, 0.4) 0%, transparent 70%)',
                    left: `${mouseX * 0.3 - 15}%`,
                    top: `${mouseY * 0.3 - 15}%`,
                }"
            ></div>
            <div
                class="absolute w-[500px] h-[500px] rounded-full opacity-15 blur-3xl transition-all duration-1500 ease-out"
                :style="{
                    background: 'radial-gradient(circle, rgba(198, 93, 61, 0.3) 0%, transparent 70%)',
                    right: `${(100 - mouseX) * 0.2}%`,
                    bottom: `${(100 - mouseY) * 0.2}%`,
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

            <!-- Subtle grid pattern -->
            <div class="absolute inset-0 bg-[linear-gradient(rgba(68,64,60,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(68,64,60,0.05)_1px,transparent_1px)] bg-[size:64px_64px]"></div>
        </div>

        <!-- Main Content -->
        <div class="relative z-10 w-full max-w-md px-4">
            <!-- Logo & Branding -->
            <div
                :class="[
                    'text-center mb-8 transition-all duration-700',
                    isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'
                ]"
            >
                <!-- Logo -->
                <Link href="/" class="inline-flex items-center gap-3 mb-6 group">
                    <div class="relative">
                        <div class="w-12 h-12 bg-gradient-to-br from-[#E07850] to-[#C65D3D] rounded-xl flex items-center justify-center shadow-lg shadow-[#E07850]/30 group-hover:shadow-[#E07850]/50 transition-shadow">
                            <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                        </div>
                        <div class="absolute -top-1 -right-1 w-3 h-3 bg-[#22C55E] rounded-full border-2 border-[#1C1917] animate-pulse"></div>
                    </div>
                    <div>
                        <span class="text-2xl font-bold bg-gradient-to-r from-[#FAFAF9] to-[#A8A29E] bg-clip-text text-transparent">Maestro</span>
                        <span class="text-2xl font-light text-[#E07850] ml-1">AI</span>
                    </div>
                </Link>

                <!-- Tagline -->
                <p class="text-[#78716C] text-sm">
                    Build enterprise apps with AI precision
                </p>
            </div>

            <!-- Auth Card -->
            <div
                :class="[
                    'relative transition-all duration-700 delay-100',
                    isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
                ]"
            >
                <!-- Card glow effect -->
                <div class="absolute -inset-1 bg-gradient-to-r from-[#E07850]/20 to-[#C65D3D]/20 rounded-3xl opacity-50 blur-xl"></div>

                <!-- Card content -->
                <div class="relative bg-[#292524]/90 backdrop-blur-xl rounded-2xl border border-[#44403C] p-8 shadow-2xl">
                    <!-- Card Header -->
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-bold text-[#FAFAF9] mb-2">
                            Get Started
                        </h1>
                        <p class="text-[#A8A29E] text-sm">
                            Sign in or create an account instantly
                        </p>
                    </div>

                    <!-- Success Message (e.g., after password reset) -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="successMessage"
                            class="mb-6 p-4 bg-[#22C55E]/10 border border-[#22C55E]/30 rounded-xl flex items-start gap-3"
                        >
                            <svg class="w-5 h-5 text-[#22C55E] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-[#22C55E]">{{ successMessage }}</p>
                        </div>
                    </Transition>

                    <!-- Error Message (e.g., OAuth failure) -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 -translate-y-2"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-2"
                    >
                        <div
                            v-if="errorMessage"
                            class="mb-6 p-4 bg-[#EF4444]/10 border border-[#EF4444]/30 rounded-xl flex items-start gap-3"
                        >
                            <svg class="w-5 h-5 text-[#EF4444] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-[#EF4444]">{{ errorMessage }}</p>
                        </div>
                    </Transition>

                    <!-- Google OAuth Button -->
                    <GoogleButton />

                    <!-- Divider -->
                    <div class="relative my-8">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-[#44403C]"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="px-4 text-xs text-[#78716C] bg-[#292524]">
                                Secure authentication powered by Google
                            </span>
                        </div>
                    </div>

                    <!-- Benefits List -->
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 text-sm text-[#A8A29E]">
                            <div class="w-5 h-5 rounded-full bg-[#E07850]/10 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span>No password required</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-[#A8A29E]">
                            <div class="w-5 h-5 rounded-full bg-[#E07850]/10 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span>Instant account creation</span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-[#A8A29E]">
                            <div class="w-5 h-5 rounded-full bg-[#E07850]/10 flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 text-[#E07850]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span>Enterprise-grade security</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Links -->
            <div
                :class="[
                    'mt-8 text-center transition-all duration-700 delay-200',
                    isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
                ]"
            >
                <p class="text-[#78716C] text-xs">
                    By continuing, you agree to our
                    <a href="#" class="text-[#A8A29E] hover:text-[#E07850] transition-colors">Terms of Service</a>
                    and
                    <a href="#" class="text-[#A8A29E] hover:text-[#E07850] transition-colors">Privacy Policy</a>
                </p>
            </div>

            <!-- Back to Home -->
            <div
                :class="[
                    'mt-6 text-center transition-all duration-700 delay-300',
                    isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'
                ]"
            >
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 text-sm text-[#78716C] hover:text-[#FAFAF9] transition-colors group"
                >
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to home
                </Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Floating animation for particles */
@keyframes float {
    0%, 100% {
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
