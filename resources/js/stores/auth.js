import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../services/api";
import { updateEchoAuth } from "../bootstrap";

export const useAuthStore = defineStore("auth", () => {
    const user = ref(null);
    const token = ref(localStorage.getItem("token"));
    const initialized = ref(false);

    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const isAdmin = computed(() => {
        if (!user.value) return false;
        return (
            user.value.roles?.includes("admin") ||
            user.value.roles?.includes("superadmin")
        );
    });
    const isSuperAdmin = computed(() => {
        if (!user.value) return false;
        return user.value.roles?.includes("superadmin");
    });

    async function initialize() {
        if (token.value) {
            try {
                await fetchUser();
            } catch (error) {
                logout();
            }
        }
        initialized.value = true;
    }

    async function login(email, password) {
        const response = await api.post("/login", { email, password });
        token.value = response.data.token;
        user.value = response.data.user;
        localStorage.setItem("token", response.data.token);
        api.defaults.headers.common["Authorization"] =
            `Bearer ${response.data.token}`;
        updateEchoAuth(response.data.token);
        return response.data;
    }

    async function register(name, email, password, password_confirmation) {
        const response = await api.post("/register", {
            name,
            email,
            password,
            password_confirmation,
        });
        token.value = response.data.token;
        user.value = response.data.user;
        localStorage.setItem("token", response.data.token);
        api.defaults.headers.common["Authorization"] =
            `Bearer ${response.data.token}`;
        updateEchoAuth(response.data.token);
        return response.data;
    }

    async function logout() {
        try {
            await api.post("/logout");
        } catch (error) {
            // Ignore logout errors
        }
        token.value = null;
        user.value = null;
        localStorage.removeItem("token");
        delete api.defaults.headers.common["Authorization"];
        updateEchoAuth(null);
    }

    async function fetchUser() {
        const response = await api.get("/user");
        user.value = response.data.user;
        return response.data.user;
    }

    async function updateProfile(data) {
        const response = await api.put("/user/profile", data);
        user.value = response.data.user;
        return response.data;
    }

    return {
        user,
        token,
        initialized,
        isAuthenticated,
        isAdmin,
        isSuperAdmin,
        initialize,
        login,
        register,
        logout,
        fetchUser,
        updateProfile,
    };
});
