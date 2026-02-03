<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    User Management
                </h1>
                <p class="text-gray-600">Manage system users and their roles</p>
            </div>
            <button @click="showCreateModal = true" class="btn-primary">
                <svg
                    class="w-4 h-4 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
                Add User
            </button>
        </div>

        <div class="card p-4 mb-6">
            <div class="flex flex-wrap gap-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search users..."
                    class="input w-64"
                    @input="debouncedSearch"
                />
                <select
                    v-model="roleFilter"
                    class="input w-40"
                    @change="fetchUsers"
                >
                    <option value="">All Roles</option>
                    <option value="superadmin">Superadmin</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>
                </select>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            User
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Role
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Meetings
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"
                        >
                            Joined
                        </th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"
                        >
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-6 py-4">
                            <div class="flex items-center">
                                <div
                                    class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center"
                                >
                                    <span
                                        class="text-sm font-medium text-primary-700"
                                        >{{ getInitials(user.name) }}</span
                                    >
                                </div>
                                <div class="ml-3">
                                    <p
                                        class="text-sm font-medium text-gray-900"
                                    >
                                        {{ user.name }}
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        {{ user.email }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span
                                :class="roleClass(user.roles?.[0]?.name)"
                                class="px-2 py-1 text-xs font-medium rounded-full"
                            >
                                {{ user.roles?.[0]?.name || "No role" }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ user.hosted_meetings_count || 0 }} hosted
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            {{ formatDate(user.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                @click="editUser(user)"
                                class="text-gray-400 hover:text-primary-600 mr-2"
                            >
                                Edit
                            </button>
                            <button
                                v-if="
                                    !user.roles?.some(
                                        (r) => r.name === 'superadmin',
                                    )
                                "
                                @click="deleteUser(user)"
                                class="text-gray-400 hover:text-red-600"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div
                v-if="users.length === 0"
                class="p-8 text-center text-gray-500"
            >
                No users found
            </div>
        </div>

        <div
            v-if="showCreateModal || editingUser"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        >
            <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ editingUser ? "Edit User" : "Create User" }}
                </h3>
                <form @submit.prevent="handleSaveUser" class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Name</label
                        ><input
                            v-model="form.name"
                            type="text"
                            class="input"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Email</label
                        ><input
                            v-model="form.email"
                            type="email"
                            class="input"
                            required
                        />
                    </div>
                    <div v-if="!editingUser">
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Password</label
                        ><input
                            v-model="form.password"
                            type="password"
                            class="input"
                            required
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 mb-1"
                            >Role</label
                        >
                        <select v-model="form.role" class="input" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <option v-if="isSuperAdmin" value="superadmin">
                                Superadmin
                            </option>
                        </select>
                    </div>
                    <div v-if="formError" class="text-red-600 text-sm">
                        {{ formError }}
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="btn-secondary"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="saving"
                            class="btn-primary"
                        >
                            {{ saving ? "Saving..." : "Save" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useAuthStore } from "../../stores/auth";
import api from "../../services/api";

const authStore = useAuthStore();
const isSuperAdmin = computed(() => authStore.isSuperAdmin);
const users = ref([]);
const search = ref("");
const roleFilter = ref("");
const showCreateModal = ref(false);
const editingUser = ref(null);
const saving = ref(false);
const formError = ref("");
const form = reactive({ name: "", email: "", password: "", role: "user" });
let searchTimeout = null;

onMounted(() => fetchUsers());

const fetchUsers = async () => {
    try {
        const response = await api.get("/admin/users", {
            params: { search: search.value, role: roleFilter.value },
        });
        users.value = response.data.data;
    } catch (e) {
        console.error(e);
    }
};

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(fetchUsers, 300);
};

const editUser = (user) => {
    editingUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.password = "";
    form.role = user.roles?.[0]?.name || "user";
};

const closeModal = () => {
    showCreateModal.value = false;
    editingUser.value = null;
    form.name = "";
    form.email = "";
    form.password = "";
    form.role = "user";
    formError.value = "";
};

const handleSaveUser = async () => {
    saving.value = true;
    formError.value = "";
    try {
        if (editingUser.value) {
            const data = { name: form.name, email: form.email };
            if (form.password) data.password = form.password;
            await api.put(`/admin/users/${editingUser.value.id}`, data);
            await api.post(`/admin/users/${editingUser.value.id}/role`, {
                role: form.role,
            });
        } else {
            await api.post("/admin/users", form);
        }
        await fetchUsers();
        closeModal();
        window.$toast?.success(
            editingUser.value ? "User updated" : "User created",
        );
    } catch (e) {
        formError.value = e.response?.data?.message || "Failed to save user";
    } finally {
        saving.value = false;
    }
};

const deleteUser = async (user) => {
    if (!confirm(`Delete user "${user.name}"?`)) return;
    try {
        await api.delete(`/admin/users/${user.id}`);
        await fetchUsers();
        window.$toast?.success("User deleted");
    } catch (e) {
        window.$toast?.error("Failed to delete user");
    }
};

const getInitials = (name) =>
    name
        ?.split(" ")
        .map((n) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2) || "?";
const formatDate = (date) => new Date(date).toLocaleDateString();
const roleClass = (role) =>
    ({
        "bg-red-100 text-red-800": role === "superadmin",
        "bg-blue-100 text-blue-800": role === "admin",
        "bg-gray-100 text-gray-800": role === "user",
    })[role] || "bg-gray-100 text-gray-800";
</script>
