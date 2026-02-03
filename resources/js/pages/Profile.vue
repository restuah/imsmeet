<template>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Profile Settings</h1>
            <p class="text-gray-600">Manage your account information</p>
        </div>

        <div class="card p-6">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div class="flex items-center space-x-6">
                    <div
                        class="w-20 h-20 rounded-full bg-primary-100 flex items-center justify-center"
                    >
                        <span class="text-2xl font-bold text-primary-700">{{
                            userInitials
                        }}</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">
                            {{ user?.name }}
                        </h3>
                        <p class="text-sm text-gray-500">{{ user?.email }}</p>
                        <span
                            class="inline-block mt-1 px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 text-primary-800"
                        >
                            {{ user?.roles?.[0] || "User" }}
                        </span>
                    </div>
                </div>

                <hr />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Full Name</label
                    >
                    <input
                        v-model="form.name"
                        type="text"
                        class="input"
                        required
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Email Address</label
                    >
                    <input
                        v-model="form.email"
                        type="email"
                        class="input"
                        required
                    />
                </div>

                <hr />

                <h3 class="text-lg font-medium text-gray-900">
                    Change Password
                </h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Current Password</label
                    >
                    <input
                        v-model="form.current_password"
                        type="password"
                        class="input"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >New Password</label
                    >
                    <input
                        v-model="form.password"
                        type="password"
                        class="input"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"
                        >Confirm New Password</label
                    >
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="input"
                    />
                </div>

                <div
                    v-if="error"
                    class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm"
                >
                    {{ error }}
                </div>

                <div
                    v-if="success"
                    class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm"
                >
                    {{ success }}
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="btn-primary"
                    >
                        {{ loading ? "Saving..." : "Save Changes" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useAuthStore } from "../stores/auth";

const authStore = useAuthStore();
const user = computed(() => authStore.user);

const form = reactive({
    name: "",
    email: "",
    current_password: "",
    password: "",
    password_confirmation: "",
});

const loading = ref(false);
const error = ref("");
const success = ref("");

const userInitials = computed(() => {
    if (!user.value?.name) return "?";
    return user.value.name
        .split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);
});

onMounted(() => {
    form.name = user.value?.name || "";
    form.email = user.value?.email || "";
});

const handleSubmit = async () => {
    loading.value = true;
    error.value = "";
    success.value = "";

    if (form.password && form.password !== form.password_confirmation) {
        error.value = "Passwords do not match";
        loading.value = false;
        return;
    }

    try {
        const data = { name: form.name, email: form.email };

        if (form.password) {
            data.current_password = form.current_password;
            data.password = form.password;
            data.password_confirmation = form.password_confirmation;
        }

        await authStore.updateProfile(data);
        success.value = "Profile updated successfully";

        form.current_password = "";
        form.password = "";
        form.password_confirmation = "";
    } catch (e) {
        error.value = e.response?.data?.message || "Failed to update profile";
    } finally {
        loading.value = false;
    }
};
</script>
