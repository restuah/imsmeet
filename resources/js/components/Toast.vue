<template>
    <div class="fixed top-4 right-4 z-50 space-y-2">
        <TransitionGroup name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="[
                    'flex items-center p-4 rounded-lg shadow-lg max-w-sm',
                    toastClasses[toast.type],
                ]"
            >
                <div class="flex-shrink-0">
                    <component :is="toastIcon[toast.type]" class="w-5 h-5" />
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium">{{ toast.message }}</p>
                </div>
                <button
                    @click="removeToast(toast.id)"
                    class="ml-4 flex-shrink-0 text-current opacity-70 hover:opacity-100"
                >
                    <svg
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { ref, h } from "vue";

const toasts = ref([]);
let toastId = 0;

const toastClasses = {
    success: "bg-green-50 text-green-800 border border-green-200",
    error: "bg-red-50 text-red-800 border border-red-200",
    warning: "bg-yellow-50 text-yellow-800 border border-yellow-200",
    info: "bg-blue-50 text-blue-800 border border-blue-200",
};

const SuccessIcon = {
    render() {
        return h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M5 13l4 4L19 7",
                }),
            ],
        );
    },
};

const ErrorIcon = {
    render() {
        return h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M6 18L18 6M6 6l12 12",
                }),
            ],
        );
    },
};

const WarningIcon = {
    render() {
        return h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z",
                }),
            ],
        );
    },
};

const InfoIcon = {
    render() {
        return h(
            "svg",
            { fill: "none", stroke: "currentColor", viewBox: "0 0 24 24" },
            [
                h("path", {
                    "stroke-linecap": "round",
                    "stroke-linejoin": "round",
                    "stroke-width": "2",
                    d: "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
                }),
            ],
        );
    },
};

const toastIcon = {
    success: SuccessIcon,
    error: ErrorIcon,
    warning: WarningIcon,
    info: InfoIcon,
};

const addToast = (message, type = "info", duration = 5000) => {
    const id = ++toastId;
    toasts.value.push({ id, message, type });

    if (duration > 0) {
        setTimeout(() => removeToast(id), duration);
    }

    return id;
};

const removeToast = (id) => {
    const index = toasts.value.findIndex((t) => t.id === id);
    if (index > -1) {
        toasts.value.splice(index, 1);
    }
};

// Expose methods globally
window.$toast = {
    success: (message) => addToast(message, "success"),
    error: (message) => addToast(message, "error"),
    warning: (message) => addToast(message, "warning"),
    info: (message) => addToast(message, "info"),
};

defineExpose({ addToast, removeToast });
</script>

<style scoped>
.toast-enter-active {
    transition: all 0.3s ease-out;
}
.toast-leave-active {
    transition: all 0.2s ease-in;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
