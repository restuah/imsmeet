<template>
    <div
        class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8"
    >
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <svg
                    class="mx-auto w-16 h-16 text-primary-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                    />
                </svg>
                <h2 class="mt-6 text-3xl font-bold text-gray-900">
                    Welcome back
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Sign in to your account to continue
                </p>
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="handleSubmit">
                <div
                    v-if="error"
                    class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"
                >
                    {{ error }}
                </div>

                <div class="space-y-4">
                    <div>
                        <label
                            for="email"
                            class="block text-sm font-medium text-gray-700"
                            >Email address</label
                        >
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="input mt-1"
                            placeholder="you@example.com"
                        />
                    </div>

                    <div>
                        <label
                            for="password"
                            class="block text-sm font-medium text-gray-700"
                            >Password</label
                        >
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            class="input mt-1"
                            placeholder="••••••••"
                        />
                    </div>
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full btn-primary py-3"
                >
                    <svg
                        v-if="loading"
                        class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    {{ loading ? "Signing in..." : "Sign in" }}
                </button>

                <p class="text-center text-sm text-gray-600">
                    Don't have an account?
                    <router-link
                        to="/register"
                        class="font-medium text-primary-600 hover:text-primary-500"
                    >
                        Create one
                    </router-link>
                </p>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const form = reactive({
    email: "",
    password: "",
});

const loading = ref(false);
const error = ref("");

const handleSubmit = async () => {
    loading.value = true;
    error.value = "";

    try {
        await authStore.login(form.email, form.password);
        const redirect = route.query.redirect || "/dashboard";
        router.push(redirect);
    } catch (e) {
        error.value =
            e.response?.data?.message ||
            "Invalid credentials. Please try again.";
    } finally {
        loading.value = false;
    }
};
</script>
