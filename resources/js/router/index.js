import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../stores/auth";

const routes = [
    {
        path: "/",
        name: "home",
        component: () => import("../pages/Home.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/login",
        name: "login",
        component: () => import("../pages/Login.vue"),
        meta: { guest: true },
    },
    {
        path: "/register",
        name: "register",
        component: () => import("../pages/Register.vue"),
        meta: { guest: true },
    },
    {
        path: "/dashboard",
        name: "dashboard",
        component: () => import("../pages/Dashboard.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/meetings",
        name: "meetings",
        component: () => import("../pages/Meetings.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/meetings/new",
        name: "meetings.create",
        component: () => import("../pages/CreateMeeting.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/meeting/:uuid",
        name: "meeting.room",
        component: () => import("../pages/MeetingRoom.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/join/:uuid",
        name: "meeting.join",
        component: () => import("../pages/JoinMeeting.vue"),
        meta: { requiresAuth: true },
    },
    {
        path: "/admin",
        name: "admin",
        component: () => import("../pages/admin/AdminDashboard.vue"),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: "/admin/users",
        name: "admin.users",
        component: () => import("../pages/admin/UserManagement.vue"),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: "/admin/meetings",
        name: "admin.meetings",
        component: () => import("../pages/admin/MeetingManagement.vue"),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: "/profile",
        name: "profile",
        component: () => import("../pages/Profile.vue"),
        meta: { requiresAuth: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Initialize auth state if not already done
    if (!authStore.initialized) {
        await authStore.initialize();
    }

    const isAuthenticated = authStore.isAuthenticated;
    const isAdmin = authStore.isAdmin;

    if (to.meta.requiresAuth && !isAuthenticated) {
        next({ name: "login", query: { redirect: to.fullPath } });
    } else if (to.meta.guest && isAuthenticated) {
        next({ name: "dashboard" });
    } else if (to.meta.requiresAdmin && !isAdmin) {
        next({ name: "dashboard" });
    } else {
        next();
    }
});

export default router;
