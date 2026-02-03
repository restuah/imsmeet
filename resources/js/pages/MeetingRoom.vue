<template>
    <div class="h-screen flex flex-col bg-gray-900">
        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div
                    class="animate-spin w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full mx-auto"
                ></div>
                <p class="mt-4 text-gray-300">Connecting to meeting...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <svg
                    class="w-16 h-16 text-red-400 mx-auto mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                    />
                </svg>
                <h2 class="text-xl font-semibold text-white mb-2">
                    Connection Error
                </h2>
                <p class="text-gray-400 mb-4">{{ error }}</p>
                <router-link to="/dashboard" class="btn-primary"
                    >Return to Dashboard</router-link
                >
            </div>
        </div>

        <!-- Meeting Room -->
        <template v-else>
            <!-- Header -->
            <header
                class="bg-gray-800 px-4 py-3 flex items-center justify-between"
            >
                <div class="flex items-center space-x-4">
                    <h1 class="text-white font-semibold truncate max-w-xs">
                        {{ meeting?.title }}
                    </h1>
                    <span
                        v-if="isRecording"
                        class="flex items-center text-red-400 text-sm"
                    >
                        <span
                            class="w-2 h-2 bg-red-500 rounded-full animate-pulse mr-2"
                        ></span>
                        Recording
                    </span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 text-sm">{{
                        formattedDuration
                    }}</span>
                    <span class="text-gray-400 text-sm"
                        >{{ participants.length }} participants</span
                    >
                </div>
            </header>

            <!-- Main Content -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Video Grid -->
                <div class="flex-1 p-4">
                    <VideoGrid
                        :local-stream="localStream"
                        :remote-streams="remoteStreams"
                        :participants="participants"
                        :current-user-id="user?.id"
                        :screen-sharing-user-id="screenSharingUserId"
                    />
                </div>

                <!-- Side Panel -->
                <div
                    v-if="showSidePanel"
                    class="w-80 bg-gray-800 border-l border-gray-700 flex flex-col"
                >
                    <div class="flex border-b border-gray-700">
                        <button
                            v-for="tab in panelTabs"
                            :key="tab.id"
                            @click="activePanel = tab.id"
                            :class="[
                                'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                                activePanel === tab.id
                                    ? 'text-white border-b-2 border-primary-500'
                                    : 'text-gray-400 hover:text-gray-200',
                            ]"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <ParticipantsList
                            v-if="activePanel === 'participants'"
                            :participants="participants"
                            :current-user-id="user?.id"
                            :is-host="isHost"
                            @mute="handleMuteParticipant"
                            @kick="handleKickParticipant"
                            @promote="handlePromoteParticipant"
                        />
                        <ChatPanel
                            v-else-if="activePanel === 'chat'"
                            :meeting-id="meeting?.id"
                            :messages="chatMessages"
                            @send="sendChatMessage"
                        />
                        <WhiteboardPanel
                            v-else-if="activePanel === 'whiteboard'"
                            :meeting-id="meeting?.id"
                            :strokes="whiteboardStrokes"
                            :is-host="isHost"
                            @draw="handleWhiteboardDraw"
                            @clear="handleWhiteboardClear"
                        />
                    </div>
                </div>
            </div>

            <!-- Controls Bar -->
            <footer class="bg-gray-800 px-4 py-4">
                <div class="flex items-center justify-center space-x-4">
                    <button
                        @click="toggleAudio"
                        :class="[
                            'control-btn',
                            isMuted
                                ? 'control-btn-inactive'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            v-if="!isMuted"
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                            />
                        </svg>
                        <svg
                            v-else
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"
                            />
                        </svg>
                    </button>

                    <button
                        @click="toggleVideo"
                        :class="[
                            'control-btn',
                            isVideoOff
                                ? 'control-btn-inactive'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
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
                            <path
                                v-if="isVideoOff"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 3l18 18"
                            />
                        </svg>
                    </button>

                    <button
                        @click="toggleScreenShare"
                        :class="[
                            'control-btn',
                            isScreenSharing
                                ? 'bg-green-500 text-white hover:bg-green-600'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </button>

                    <button
                        v-if="isHost && meeting?.is_recording_enabled"
                        @click="toggleRecording"
                        :class="[
                            'control-btn',
                            isRecording
                                ? 'bg-red-500 text-white hover:bg-red-600'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <circle cx="12" cy="12" r="10" stroke-width="2" />
                            <circle
                                v-if="isRecording"
                                cx="12"
                                cy="12"
                                r="4"
                                fill="currentColor"
                            />
                        </svg>
                    </button>

                    <button
                        @click="toggleHandRaise"
                        :class="[
                            'control-btn',
                            isHandRaised
                                ? 'bg-yellow-500 text-white hover:bg-yellow-600'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11"
                            />
                        </svg>
                    </button>

                    <div class="w-px h-8 bg-gray-600"></div>

                    <button
                        @click="togglePanel('participants')"
                        :class="[
                            'control-btn',
                            activePanel === 'participants' && showSidePanel
                                ? 'bg-primary-500 text-white'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                    </button>

                    <button
                        v-if="meeting?.is_chat_enabled"
                        @click="togglePanel('chat')"
                        :class="[
                            'control-btn relative',
                            activePanel === 'chat' && showSidePanel
                                ? 'bg-primary-500 text-white'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                            />
                        </svg>
                        <span
                            v-if="unreadMessages > 0"
                            class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"
                        >
                            {{ unreadMessages > 9 ? "9+" : unreadMessages }}
                        </span>
                    </button>

                    <button
                        v-if="meeting?.is_whiteboard_enabled"
                        @click="togglePanel('whiteboard')"
                        :class="[
                            'control-btn',
                            activePanel === 'whiteboard' && showSidePanel
                                ? 'bg-primary-500 text-white'
                                : 'control-btn-active',
                        ]"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                            />
                        </svg>
                    </button>

                    <div class="w-px h-8 bg-gray-600"></div>

                    <button
                        @click="handleLeaveMeeting"
                        class="control-btn bg-red-600 text-white hover:bg-red-700"
                    >
                        <svg
                            class="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                            />
                        </svg>
                    </button>

                    <button
                        v-if="isHost"
                        @click="handleEndMeeting"
                        class="control-btn bg-red-600 text-white hover:bg-red-700"
                        title="End meeting for all"
                    >
                        <svg
                            class="w-6 h-6"
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
            </footer>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth";
import { useMeetingStore } from "../stores/meeting";
import { WebRTCService } from "../services/webrtc";
import { RecordingService } from "../services/recording";
import api from "../services/api";
import VideoGrid from "../components/meeting/VideoGrid.vue";
import ParticipantsList from "../components/meeting/ParticipantsList.vue";
import ChatPanel from "../components/meeting/ChatPanel.vue";
import WhiteboardPanel from "../components/meeting/WhiteboardPanel.vue";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const meetingStore = useMeetingStore();

const user = computed(() => authStore.user);
const meeting = computed(() => meetingStore.currentMeeting);
const participants = computed(() => meetingStore.participants);

const loading = ref(true);
const error = ref("");
const localStream = ref(null);
const remoteStreams = ref(new Map());
const webrtcService = ref(null);
const recordingService = ref(new RecordingService());

const isMuted = ref(false);
const isVideoOff = ref(false);
const isScreenSharing = ref(false);
const isRecording = ref(false);
const isHandRaised = ref(false);
const screenSharingUserId = ref(null);

const showSidePanel = ref(false);
const activePanel = ref("participants");
const chatMessages = ref([]);
const whiteboardStrokes = ref([]);
const unreadMessages = ref(0);

const meetingDuration = ref(0);
let durationInterval = null;

const isHost = computed(() => {
    const participant = participants.value.find(
        (p) => p.user_id === user.value?.id,
    );
    return participant?.role === "host" || participant?.role === "co-host";
});

const formattedDuration = computed(() => {
    const hours = Math.floor(meetingDuration.value / 3600);
    const minutes = Math.floor((meetingDuration.value % 3600) / 60);
    const seconds = meetingDuration.value % 60;
    if (hours > 0)
        return `${hours}:${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
    return `${minutes}:${String(seconds).padStart(2, "0")}`;
});

const panelTabs = computed(() => {
    const tabs = [{ id: "participants", label: "Participants" }];
    if (meeting.value?.is_chat_enabled)
        tabs.push({ id: "chat", label: "Chat" });
    if (meeting.value?.is_whiteboard_enabled)
        tabs.push({ id: "whiteboard", label: "Whiteboard" });
    return tabs;
});

onMounted(async () => {
    try {
        const joinPrefs = JSON.parse(
            sessionStorage.getItem("joinPrefs") || "{}",
        );
        const meetingData = await meetingStore.fetchMeetingByUuid(
            route.params.uuid,
        );

        const joinResult = await meetingStore.joinMeeting(meetingData.id, {
            display_name: joinPrefs.displayName,
        });

        if (joinResult.in_waiting_room) {
            window.$toast?.info("You are in the waiting room");
            return;
        }

        if (
            meeting.value.status === "scheduled" &&
            meeting.value.host_id === user.value.id
        ) {
            await meetingStore.startMeeting(meeting.value.id);
        }

        await initializeMedia(
            joinPrefs.video !== false,
            joinPrefs.audio !== false,
        );
        setupBroadcastChannels();

        await Promise.all([
            meetingStore.fetchParticipants(meeting.value.id),
            fetchChatMessages(),
            fetchWhiteboardStrokes(),
        ]);

        participants.value.forEach((p) => {
            if (p.user_id !== user.value.id) {
                webrtcService.value?.connectToParticipant(p.user_id);
            }
        });

        durationInterval = setInterval(() => meetingDuration.value++, 1000);
        loading.value = false;
    } catch (e) {
        error.value =
            e.response?.data?.message || "Failed to connect to meeting";
        loading.value = false;
    }
});

onBeforeUnmount(() => cleanup());

const initializeMedia = async (video = true, audio = true) => {
    webrtcService.value = new WebRTCService(
        meeting.value.id,
        user.value.id,
        handleRemoteStream,
        handleRemoveStream,
    );
    try {
        localStream.value = await webrtcService.value.initLocalStream(
            audio,
            video,
        );
        isMuted.value = !audio;
        isVideoOff.value = !video;
    } catch (e) {
        try {
            localStream.value = await webrtcService.value.initLocalStream(
                true,
                false,
            );
            isMuted.value = false;
            isVideoOff.value = true;
        } catch (e2) {
            console.error("Failed to get media:", e2);
        }
    }
};

const setupBroadcastChannels = () => {
    window.Echo.private(`meeting.${meeting.value.id}`)
        .listen(".webrtc.signal", (data) => {
            if (data.to_user_id === user.value.id) {
                webrtcService.value?.handleSignal(
                    data.from_user_id,
                    data.type,
                    data.sdp,
                );
            }
        })
        .listen(".webrtc.ice-candidate", (data) => {
            if (data.to_user_id === user.value.id) {
                webrtcService.value?.handleIceCandidate(
                    data.from_user_id,
                    data.candidate,
                );
            }
        })
        .listen(".participant.joined", (data) => {
            meetingStore.addParticipant(data.participant);
            webrtcService.value?.connectToParticipant(data.participant.user_id);
            window.$toast?.info(`${data.participant.display_name} joined`);
        })
        .listen(".participant.left", (data) => {
            meetingStore.removeParticipant(data.user_id);
            webrtcService.value?.disconnectFromParticipant(data.user_id);
            remoteStreams.value.delete(data.user_id);
        })
        .listen(".participant.updated", (data) =>
            meetingStore.updateParticipant(data.participant),
        )
        .listen(".participant.muted", (data) => {
            if (data.is_all || data.user_id === user.value.id) {
                isMuted.value = data.is_muted;
                webrtcService.value?.toggleAudio(!data.is_muted);
            }
        })
        .listen(".participant.kicked", (data) => {
            if (data.user_id === user.value.id) {
                window.$toast?.error("You have been removed from the meeting");
                handleLeaveMeeting();
            }
        })
        .listen(
            ".screenshare.started",
            (data) => (screenSharingUserId.value = data.user_id),
        )
        .listen(
            ".screenshare.stopped",
            () => (screenSharingUserId.value = null),
        )
        .listen(".meeting.ended", () => {
            window.$toast?.info("Meeting has ended");
            handleLeaveMeeting();
        })
        .listen(".recording.started", () => (isRecording.value = true))
        .listen(".recording.stopped", () => (isRecording.value = false));

    window.Echo.private(`chat.${meeting.value.id}`).listen(
        ".message.sent",
        (data) => {
            chatMessages.value.push(data.message);
            if (activePanel.value !== "chat" || !showSidePanel.value)
                unreadMessages.value++;
        },
    );

    window.Echo.private(`whiteboard.${meeting.value.id}`)
        .listen(".stroke.added", (data) =>
            whiteboardStrokes.value.push(data.stroke),
        )
        .listen(".stroke.removed", (data) => {
            whiteboardStrokes.value = whiteboardStrokes.value.filter(
                (s) => s.stroke_id !== data.stroke_id,
            );
        })
        .listen(".whiteboard.cleared", () => (whiteboardStrokes.value = []));
};

const handleRemoteStream = (userId, stream) => {
    remoteStreams.value.set(userId, stream);
    remoteStreams.value = new Map(remoteStreams.value);
};

const handleRemoveStream = (userId) => {
    remoteStreams.value.delete(userId);
    remoteStreams.value = new Map(remoteStreams.value);
};

const fetchChatMessages = async () => {
    if (!meeting.value?.is_chat_enabled) return;
    try {
        const response = await api.get(
            `/meetings/${meeting.value.id}/messages`,
        );
        chatMessages.value = response.data.data || [];
    } catch (e) {
        console.error("Failed to fetch chat:", e);
    }
};

const fetchWhiteboardStrokes = async () => {
    if (!meeting.value?.is_whiteboard_enabled) return;
    try {
        const response = await api.get(
            `/meetings/${meeting.value.id}/whiteboard`,
        );
        whiteboardStrokes.value = response.data.strokes || [];
    } catch (e) {
        console.error("Failed to fetch whiteboard:", e);
    }
};

const toggleAudio = async () => {
    isMuted.value = !isMuted.value;
    webrtcService.value?.toggleAudio(!isMuted.value);
    await api.post(`/meetings/${meeting.value.id}/toggle-audio`);
};

const toggleVideo = async () => {
    isVideoOff.value = !isVideoOff.value;
    webrtcService.value?.toggleVideo(!isVideoOff.value);
    await api.post(`/meetings/${meeting.value.id}/toggle-video`);
};

const toggleScreenShare = async () => {
    if (isScreenSharing.value) {
        await webrtcService.value?.stopScreenShare();
        await api.post(`/meetings/${meeting.value.id}/screen-share/stop`);
        isScreenSharing.value = false;
    } else {
        try {
            await webrtcService.value?.startScreenShare();
            await api.post(`/meetings/${meeting.value.id}/screen-share/start`);
            isScreenSharing.value = true;
        } catch (e) {
            window.$toast?.error("Failed to start screen share");
        }
    }
};

const toggleRecording = async () => {
    if (isRecording.value) {
        try {
            await recordingService.value.stop();
            isRecording.value = false;
            window.$toast?.success("Recording saved");
        } catch (e) {
            window.$toast?.error("Failed to save recording");
        }
    } else {
        try {
            const streams = [
                localStream.value,
                ...remoteStreams.value.values(),
            ].filter(Boolean);
            await recordingService.value.start(meeting.value.id, streams);
            isRecording.value = true;
            window.$toast?.info("Recording started");
        } catch (e) {
            window.$toast?.error("Failed to start recording");
        }
    }
};

const toggleHandRaise = async () => {
    isHandRaised.value = !isHandRaised.value;
    const endpoint = isHandRaised.value ? "raise-hand" : "lower-hand";
    await api.post(`/meetings/${meeting.value.id}/${endpoint}`);
};

const togglePanel = (panel) => {
    if (activePanel.value === panel && showSidePanel.value) {
        showSidePanel.value = false;
    } else {
        activePanel.value = panel;
        showSidePanel.value = true;
        if (panel === "chat") unreadMessages.value = 0;
    }
};

const sendChatMessage = async (message) => {
    try {
        const response = await api.post(
            `/meetings/${meeting.value.id}/messages`,
            { message },
        );
        chatMessages.value.push(response.data.message);
    } catch (e) {
        window.$toast?.error("Failed to send message");
    }
};

const handleWhiteboardDraw = async (stroke) => {
    try {
        await api.post(`/meetings/${meeting.value.id}/whiteboard`, stroke);
    } catch (e) {
        console.error("Failed to save stroke:", e);
    }
};

const handleWhiteboardClear = async () => {
    try {
        await api.post(`/meetings/${meeting.value.id}/whiteboard/clear`);
        whiteboardStrokes.value = [];
    } catch (e) {
        window.$toast?.error("Failed to clear whiteboard");
    }
};

const handleMuteParticipant = async (participant) => {
    try {
        const endpoint = participant.is_muted ? "unmute" : "mute";
        await api.post(
            `/meetings/${meeting.value.id}/participants/${participant.id}/${endpoint}`,
        );
    } catch (e) {
        window.$toast?.error("Failed to update participant");
    }
};

const handleKickParticipant = async (participant) => {
    if (!confirm(`Remove ${participant.display_name} from the meeting?`))
        return;
    try {
        await api.post(
            `/meetings/${meeting.value.id}/participants/${participant.id}/kick`,
        );
        window.$toast?.success("Participant removed");
    } catch (e) {
        window.$toast?.error("Failed to remove participant");
    }
};

const handlePromoteParticipant = async (participant) => {
    try {
        const endpoint = participant.role === "co-host" ? "demote" : "promote";
        await api.post(
            `/meetings/${meeting.value.id}/participants/${participant.id}/${endpoint}`,
        );
    } catch (e) {
        window.$toast?.error("Failed to update participant role");
    }
};

const handleLeaveMeeting = async () => {
    cleanup();
    try {
        await meetingStore.leaveMeeting(meeting.value.id);
    } catch (e) {
        /* ignore */
    }
    router.push("/dashboard");
};

const handleEndMeeting = async () => {
    if (!confirm("End the meeting for all participants?")) return;
    cleanup();
    try {
        await meetingStore.endMeeting(meeting.value.id);
    } catch (e) {
        /* ignore */
    }
    router.push("/dashboard");
};

const cleanup = () => {
    if (durationInterval) clearInterval(durationInterval);
    if (isRecording.value) recordingService.value.stop().catch(() => {});
    webrtcService.value?.destroy();
    if (meeting.value?.id) {
        window.Echo.leave(`meeting.${meeting.value.id}`);
        window.Echo.leave(`chat.${meeting.value.id}`);
        window.Echo.leave(`whiteboard.${meeting.value.id}`);
    }
    meetingStore.clearMeeting();
};
</script>
