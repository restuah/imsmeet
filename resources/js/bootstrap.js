import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// Get token from localStorage
const token = localStorage.getItem("token");
if (token) {
    window.axios.defaults.headers.common["Authorization"] = `Bearer ${token}`;
}

// Laravel Echo configuration for Reverb
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
    enabledTransports: ["ws", "wss"],
    authEndpoint: "/broadcasting/auth",
    auth: {
        headers: {
            Authorization: token ? `Bearer ${token}` : "",
        },
    },
});

// Update Echo auth when token changes

//export function updateEchoAuth(newToken) {
//    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
//        window.Echo.connector.pusher.config.auth = {
//            headers: {
//                Authorization: newToken ? `Bearer ${newToken}` : "",
//            },
//        };
//    }
//}
export function updateEchoAuth(newToken) {
    window.Echo.connector.options.auth.headers.Authorization = newToken ? `Bearer ${newToken}` : '';
}
