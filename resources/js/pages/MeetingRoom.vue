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
        <h2 class="text-xl font-semibold text-white mb-2">Connection Error</h2>
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
        class="bg-gray-800 px-4 py-3 flex items-center justify-between flex-shrink-0"
      >
        <div class="flex items-center space-x-4 min-w-0">
          <h1 class="text-white font-semibold truncate max-w-xs">
            {{ meeting?.title }}
          </h1>
          <span
            v-if="isRecording"
            class="flex items-center text-red-400 text-sm flex-shrink-0"
          >
            <span
              class="w-2 h-2 bg-red-500 rounded-full animate-pulse mr-2"
            ></span>
            Recording
          </span>
        </div>
        <div class="flex items-center space-x-4 flex-shrink-0">
          <span class="text-gray-400 text-sm hidden sm:inline">{{
            formattedDuration
          }}</span>
          <span class="text-gray-400 text-sm hidden md:inline"
            >{{ participants.length }} participants</span
          >
        </div>
      </header>

      <!-- Main Content - Responsive Layout -->
      <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
        <!-- Video Grid Area -->
        <div
          class="flex-1 p-2 sm:p-4 min-h-0"
          :class="{
            'lg:mr-80': showSidePanel,
          }"
        >
          <VideoGrid
            :local-stream="localStream"
            :remote-streams="remoteStreams"
            :participants="participants"
            :current-user-id="user?.id"
            :screen-sharing-user-id="screenSharingUserId"
            :active-speaker-id="activeSpeakerId"
          />
        </div>

        <!-- Side Panel - Responsive -->
        <transition
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-x-full"
          enter-to-class="translate-x-0"
          leave-active-class="transition-transform duration-300 ease-in"
          leave-from-class="translate-x-0"
          leave-to-class="translate-x-full"
        >
          <div
            v-if="showSidePanel"
            class="fixed lg:absolute right-0 top-0 bottom-0 w-full sm:w-80 bg-gray-800 border-l border-gray-700 flex flex-col z-40 lg:top-auto lg:bottom-auto lg:h-full"
          >
            <!-- Panel Tabs -->
            <div class="flex border-b border-gray-700 flex-shrink-0">
              <button
                v-for="tab in panelTabs"
                :key="tab.id"
                @click="activePanel = tab.id"
                :class="[
                  'flex-1 px-4 py-3 text-sm font-medium transition-colors',
                  activePanel === tab.id
                    ? 'text-white bg-gray-700'
                    : 'text-gray-400 hover:text-white',
                ]"
              >
                {{ tab.label }}
                <span
                  v-if="tab.id === 'chat' && unreadMessages > 0"
                  class="ml-2 px-2 py-0.5 bg-red-500 text-white text-xs rounded-full"
                >
                  {{ unreadMessages }}
                </span>
              </button>

              <!-- Close button for mobile -->
              <button
                @click="showSidePanel = false"
                class="lg:hidden p-3 text-gray-400 hover:text-white"
              >
                <svg
                  class="w-5 h-5"
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

            <!-- Panel Content -->
            <div class="flex-1 overflow-hidden">
              <ParticipantsList
                v-if="activePanel === 'participants'"
                :participants="participants"
                :current-user-id="user?.id"
                :is-host="isHost"
                :active-speaker-id="activeSpeakerId"
                @mute="handleMuteParticipant"
                @kick="handleKickParticipant"
                @promote="handlePromoteParticipant"
              />
              <ChatPanel
                v-else-if="activePanel === 'chat'"
                :messages="chatMessages"
                :current-user="user"
                @send="sendChatMessage"
              />
              <WhiteboardPanel
                v-else-if="activePanel === 'whiteboard'"
                :strokes="whiteboardStrokes"
                :current-user-id="user?.id"
                :is-host="isHost"
                @draw="handleWhiteboardDraw"
                @clear="handleWhiteboardClear"
              />
            </div>
          </div>
        </transition>
      </div>

      <!-- Footer Controls - Responsive -->
      <footer
        class="bg-gray-800 px-2 sm:px-4 py-3 flex items-center justify-center flex-shrink-0"
      >
        <div class="flex items-center space-x-2 sm:space-x-4">
          <!-- Mic Toggle -->
          <button
            @click="toggleAudio"
            :class="[
              'control-btn',
              isMuted ? 'control-btn-inactive' : 'control-btn-active',
            ]"
            :title="isMuted ? 'Unmute' : 'Mute'"
          >
            <svg
              v-if="!isMuted"
              class="w-5 h-5 sm:w-6 sm:h-6"
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
              class="w-5 h-5 sm:w-6 sm:h-6"
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

          <!-- Video Toggle -->
          <button
            @click="toggleVideo"
            :class="[
              'control-btn',
              isVideoOff ? 'control-btn-inactive' : 'control-btn-active',
            ]"
            :title="isVideoOff ? 'Turn on camera' : 'Turn off camera'"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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

          <!-- Screen Share (hidden on mobile) -->
          <button
            @click="toggleScreenShare"
            :class="[
              'control-btn hidden sm:block',
              isScreenSharing
                ? 'bg-green-500 text-white hover:bg-green-600'
                : 'control-btn-active',
            ]"
            title="Screen share"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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

          <!-- Recording (Host only, hidden on mobile) -->
          <button
            v-if="isHost && meeting?.is_recording_enabled"
            @click="toggleRecording"
            :class="[
              'control-btn hidden sm:block',
              isRecording
                ? 'bg-red-500 text-white hover:bg-red-600'
                : 'control-btn-active',
            ]"
            title="Recording"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"
              />
            </svg>
          </button>

          <!-- Hand Raise - FIX #3: Independent button -->
          <button
            @click="toggleHandRaise"
            :class="[
              'control-btn',
              isHandRaised
                ? 'bg-yellow-500 text-white hover:bg-yellow-600'
                : 'control-btn-active',
            ]"
            title="Raise hand"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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

          <!-- Divider -->
          <div class="w-px h-8 bg-gray-600 hidden sm:block"></div>

          <!-- Participants Panel Toggle -->
          <button
            @click="togglePanel('participants')"
            :class="[
              'control-btn',
              activePanel === 'participants' && showSidePanel
                ? 'bg-gray-600'
                : 'control-btn-active',
            ]"
            title="Participants"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
              />
            </svg>
          </button>

          <!-- Chat Panel Toggle -->
          <button
            v-if="meeting?.is_chat_enabled"
            @click="togglePanel('chat')"
            :class="[
              'control-btn relative',
              activePanel === 'chat' && showSidePanel
                ? 'bg-gray-600'
                : 'control-btn-active',
            ]"
            title="Chat"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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

          <!-- Divider -->
          <div class="w-px h-8 bg-gray-600"></div>

          <!-- Leave/End Meeting -->
          <button
            @click="handleLeaveMeeting"
            class="control-btn bg-red-600 text-white hover:bg-red-700"
            title="Leave meeting"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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
            title="End meeting"
          >
            <svg
              class="w-5 h-5 sm:w-6 sm:h-6"
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
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
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

// ============================================
// STATE VARIABLES - With HARD defaults
// ============================================
const isMuted = ref(false);
const isVideoOff = ref(false);
const isScreenSharing = ref(false);
const isRecording = ref(false);
const isHandRaised = ref(false);
const screenSharingUserId = ref(null);
const meetingEnded = ref(false);

// Active Speaker Detection
const activeSpeakerId = ref(null);
const audioContexts = ref(new Map());
let speakerDetectionInterval = null;

// UI State
const showSidePanel = ref(false);
const activePanel = ref("participants");
const chatMessages = ref([]);
const whiteboardStrokes = ref([]);
const unreadMessages = ref(0);
const meetingDuration = ref(0);
let durationInterval = null;

// Grid visibility tracking for lazy loading
const visibleUserIds = ref(new Set());
const MAX_VISIBLE_TILES = 9; // Maximum tiles shown in grid

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
  if (meeting.value?.is_chat_enabled) tabs.push({ id: "chat", label: "Chat" });
  if (meeting.value?.is_whiteboard_enabled)
    tabs.push({ id: "whiteboard", label: "Whiteboard" });
  return tabs;
});

// ============================================
// COMPUTED: Priority users for lazy loading
// ============================================
const priorityUserIds = computed(() => {
  const priority = new Set();

  // Active speaker
  if (activeSpeakerId.value) {
    priority.add(activeSpeakerId.value);
  }

  // Users with raised hands
  participants.value.forEach((p) => {
    if (p.is_hand_raised) {
      priority.add(p.user_id);
    }
  });

  // Screen sharer
  if (screenSharingUserId.value) {
    priority.add(screenSharingUserId.value);
  }

  return priority;
});

// ============================================
// HARD RESET - Called on mount and unmount
// ============================================
const resetLocalState = () => {
  // console.log("[MeetingRoom] Hard resetting all state to defaults");

  isMuted.value = false;
  isVideoOff.value = false;
  isScreenSharing.value = false;
  isRecording.value = false;
  isHandRaised.value = false;
  screenSharingUserId.value = null;
  activeSpeakerId.value = null;
  meetingDuration.value = 0;
  meetingEnded.value = false;

  chatMessages.value = [];
  whiteboardStrokes.value = [];
  unreadMessages.value = 0;
  showSidePanel.value = false;
  activePanel.value = "participants";

  // Clear remote streams
  remoteStreams.value.clear();

  // Clear audio contexts
  audioContexts.value.forEach((ctx) => {
    try {
      ctx.audioContext?.close();
    } catch (e) {}
  });
  audioContexts.value.clear();

  // Clear visibility tracking
  visibleUserIds.value.clear();

  sessionStorage.removeItem("mediaState");
};

// ============================================
// onMounted - With complete hardware cleanup
// ============================================
onMounted(async () => {
  // CRITICAL: Reset state first
  resetLocalState();

  try {
    const joinPrefs = JSON.parse(sessionStorage.getItem("joinPrefs") || "{}");

    const meetingData = await meetingStore.fetchMeetingByUuid(
      route.params.uuid,
    );

    if (meetingData.status === "ended") {
      error.value = "This meeting has ended and cannot be rejoined";
      loading.value = false;
      return;
    }

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

    // Initialize media with FRESH stream
    await initializeMedia(joinPrefs.video !== false, joinPrefs.audio !== false);

    setupBroadcastChannels();

    await Promise.all([
      meetingStore.fetchParticipants(meeting.value.id),
      fetchChatMessages(),
      fetchWhiteboardStrokes(),
    ]);

    // Connect to existing participants
    participants.value.forEach((p) => {
      if (p.user_id !== user.value.id) {
        webrtcService.value?.connectToParticipant(p.user_id);
      }
    });

    // Start speaker detection
    startSpeakerDetection();

    // Update visible users for lazy loading
    updateVisibleUsers();

    durationInterval = setInterval(() => meetingDuration.value++, 1000);
    loading.value = false;
  } catch (e) {
    console.error("[MeetingRoom] Mount error:", e);
    error.value = e.response?.data?.message || "Failed to connect to meeting";
    loading.value = false;
  }
});

// ============================================
// onBeforeUnmount - CRITICAL cleanup
// ============================================
onBeforeUnmount(() => {
  // console.log("[MeetingRoom] Component unmounting, running cleanup...");
  cleanup();
  resetLocalState();
});

// ============================================
// Watch for participant changes to update visibility
// ============================================
watch(
  () => participants.value,
  () => {
    updateVisibleUsers();
  },
  { deep: true },
);

watch(
  () => [activeSpeakerId.value, screenSharingUserId.value],
  () => {
    // Update priority users in WebRTC service
    if (webrtcService.value) {
      webrtcService.value.setPriorityUsers(priorityUserIds.value);
    }
  },
);

// ============================================
// Initialize Media - Fresh stream always
// ============================================
const initializeMedia = async (video = true, audio = true) => {
  // console.log(
  //   "[MeetingRoom] Initializing media - video:",
  //   video,
  //   "audio:",
  //   audio,
  // );

  // Create WebRTC service
  webrtcService.value = new WebRTCService(
    meeting.value.id,
    user.value.id,
    handleRemoteStream,
    handleRemoveStream,
    handleBitrateChange,
  );

  // Load ICE servers from backend
  await webrtcService.value.loadIceServers();

  try {
    // Get fresh local stream
    localStream.value = await webrtcService.value.initLocalStream(audio, video);

    // Set state based on what we got
    isMuted.value = !audio;
    isVideoOff.value = !video;

    // Ensure tracks are enabled
    if (localStream.value) {
      localStream.value.getAudioTracks().forEach((track) => {
        track.enabled = audio;
      });
      localStream.value.getVideoTracks().forEach((track) => {
        track.enabled = video;
      });
    }

    // Setup audio analyser for local user
    if (localStream.value) {
      setupAudioAnalyser(user.value.id, localStream.value);
    }
  } catch (e) {
    console.error("[MeetingRoom] Media init failed:", e);

    // Try audio only fallback
    try {
      localStream.value = await webrtcService.value.initLocalStream(
        true,
        false,
      );
      isMuted.value = false;
      isVideoOff.value = true;

      if (localStream.value) {
        localStream.value.getAudioTracks().forEach((track) => {
          track.enabled = true;
        });
        setupAudioAnalyser(user.value.id, localStream.value);
      }

      window.$toast?.warning("Camera unavailable, using audio only");
    } catch (e2) {
      console.error("[MeetingRoom] Fallback failed:", e2);
      window.$toast?.error("Could not access microphone or camera");
    }
  }
};

// ============================================
// Update visible users for lazy loading
// ============================================
const updateVisibleUsers = () => {
  const newVisible = new Set();

  // Always include self
  newVisible.add(user.value.id);

  // Include priority users
  priorityUserIds.value.forEach((id) => newVisible.add(id));

  // Fill remaining slots with other participants
  let count = newVisible.size;
  for (const p of participants.value) {
    if (count >= MAX_VISIBLE_TILES) break;
    if (!newVisible.has(p.user_id)) {
      newVisible.add(p.user_id);
      count++;
    }
  }

  visibleUserIds.value = newVisible;

  // Update WebRTC service
  if (webrtcService.value) {
    webrtcService.value.setVisibleUsers(newVisible);
  }
};

// ============================================
// Broadcast Channels Setup
// ============================================
const setupBroadcastChannels = () => {
  const meetingId = meeting.value.id;

  window.Echo.private(`meeting.${meetingId}`)
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
      // console.log("[Broadcast] Participant joined:", data.participant);
      meetingStore.addParticipant(data.participant);
      webrtcService.value?.connectToParticipant(data.participant.user_id);
      window.$toast?.info(`${data.participant.display_name} joined`);
      updateVisibleUsers();
    })
    // FIX: Ghost User - Complete cleanup on participant leave
    .listen(".participant.left", (data) => {
      // console.log("[Broadcast] Participant left:", data);

      const leftUserId = data.user_id || data.participant_id;
      if (!leftUserId) {
        console.error("[Broadcast] No user_id in leave event");
        return;
      }

      // 1. Remove from store
      meetingStore.removeParticipant(leftUserId);

      // 2. Disconnect WebRTC - This closes peer connection
      webrtcService.value?.disconnectFromParticipant(leftUserId);

      // 3. Remove remote stream
      remoteStreams.value.delete(leftUserId);
      remoteStreams.value = new Map(remoteStreams.value);

      // 4. Cleanup audio context
      const audioCtx = audioContexts.value.get(leftUserId);
      if (audioCtx) {
        try {
          audioCtx.audioContext?.close();
        } catch (e) {}
        audioContexts.value.delete(leftUserId);
      }

      // 5. Clear if active speaker
      if (activeSpeakerId.value === leftUserId) {
        activeSpeakerId.value = null;
      }

      // 6. Clear if screen sharer
      if (screenSharingUserId.value === leftUserId) {
        screenSharingUserId.value = null;
      }

      // 7. Update visibility
      visibleUserIds.value.delete(leftUserId);
      updateVisibleUsers();

      // console.log("[Broadcast] User completely removed:", leftUserId);
    })
    .listen(".participant.updated", (data) => {
      meetingStore.updateParticipant(data.participant);
    })
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
    .listen(".screenshare.started", (data) => {
      screenSharingUserId.value = data.user_id;
      updateVisibleUsers();
    })
    .listen(".screenshare.stopped", () => {
      screenSharingUserId.value = null;
      updateVisibleUsers();
    })
    .listen(".meeting.ended", (data) => {
      // console.log("[Broadcast] Meeting ended");
      meetingEnded.value = true;
      window.$toast?.info("The meeting has been ended by the host");
      forceEndMeeting();
    })
    .listen(".recording.started", () => (isRecording.value = true))
    .listen(".recording.stopped", () => (isRecording.value = false))
    .listen(".hand.raised", (data) => {
      if (data.user_id !== user.value.id) {
        const participant = participants.value.find(
          (p) => p.user_id === data.user_id,
        );
        if (participant) {
          meetingStore.updateParticipant({
            ...participant,
            is_hand_raised: true,
          });
        }
        window.$toast?.info(`${data.display_name} raised their hand`);
        updateVisibleUsers();
      }
    })
    .listen(".hand.lowered", (data) => {
      if (data.user_id !== user.value.id) {
        const participant = participants.value.find(
          (p) => p.user_id === data.user_id,
        );
        if (participant) {
          meetingStore.updateParticipant({
            ...participant,
            is_hand_raised: false,
          });
        }
        updateVisibleUsers();
      }
    });

  // Chat channel
  if (meeting.value?.is_chat_enabled) {
    window.Echo.private(`chat.${meetingId}`).listen(".message.sent", (data) => {
      chatMessages.value.push(data.message);
      if (activePanel.value !== "chat" || !showSidePanel.value)
        unreadMessages.value++;
    });
  }

  // Whiteboard channel
  if (meeting.value?.is_whiteboard_enabled) {
    window.Echo.private(`whiteboard.${meetingId}`)
      .listen(".stroke.added", (data) =>
        whiteboardStrokes.value.push(data.stroke),
      )
      .listen(".stroke.removed", (data) => {
        whiteboardStrokes.value = whiteboardStrokes.value.filter(
          (s) => s.stroke_id !== data.stroke_id,
        );
      })
      .listen(".whiteboard.cleared", () => (whiteboardStrokes.value = []));
  }
};

// ============================================
// Stream Handlers
// ============================================
const handleRemoteStream = (remoteUserId, stream) => {
  // console.log(`[MeetingRoom] Remote stream from ${remoteUserId}`);
  remoteStreams.value.set(remoteUserId, stream);
  remoteStreams.value = new Map(remoteStreams.value);

  // Setup audio analyser
  setupAudioAnalyser(remoteUserId, stream);
};

const handleRemoveStream = (removedUserId) => {
  // console.log(`[MeetingRoom] Removing stream for ${removedUserId}`);
  remoteStreams.value.delete(removedUserId);
  remoteStreams.value = new Map(remoteStreams.value);

  // Cleanup audio context
  const audioCtx = audioContexts.value.get(removedUserId);
  if (audioCtx) {
    try {
      audioCtx.audioContext?.close();
    } catch (e) {}
    audioContexts.value.delete(removedUserId);
  }

  if (activeSpeakerId.value === removedUserId) {
    activeSpeakerId.value = null;
  }
};

const handleBitrateChange = (userId, quality, config) => {
  // console.log(`[MeetingRoom] Bitrate changed for ${userId}: ${quality}`);
};

// ============================================
// Active Speaker Detection
// ============================================
const setupAudioAnalyser = (odtUserId, stream) => {
  try {
    const audioTracks = stream.getAudioTracks();
    if (!audioTracks.length) return;

    const audioContext = new (
      window.AudioContext || window.webkitAudioContext
    )();
    const analyser = audioContext.createAnalyser();
    analyser.fftSize = 256;
    analyser.smoothingTimeConstant = 0.8;

    const source = audioContext.createMediaStreamSource(stream);
    source.connect(analyser);

    const dataArray = new Uint8Array(analyser.frequencyBinCount);

    audioContexts.value.set(odtUserId, {
      audioContext,
      analyser,
      dataArray,
      source,
    });
  } catch (e) {
    console.error(`[SpeakerDetection] Setup failed for ${odtUserId}:`, e);
  }
};

const startSpeakerDetection = () => {
  speakerDetectionInterval = setInterval(() => {
    let maxVolume = 0;
    let loudestUser = null;
    const volumeThreshold = 20;

    audioContexts.value.forEach((ctx, odtUserId) => {
      if (!ctx.analyser || !ctx.dataArray) return;

      ctx.analyser.getByteFrequencyData(ctx.dataArray);

      const sum = ctx.dataArray.reduce((a, b) => a + b, 0);
      const average = sum / ctx.dataArray.length;

      if (average > maxVolume && average > volumeThreshold) {
        maxVolume = average;
        loudestUser = odtUserId;
      }
    });

    if (loudestUser !== null && loudestUser !== activeSpeakerId.value) {
      activeSpeakerId.value = loudestUser;
    }
  }, 100);
};

const stopSpeakerDetection = () => {
  if (speakerDetectionInterval) {
    clearInterval(speakerDetectionInterval);
    speakerDetectionInterval = null;
  }

  audioContexts.value.forEach((ctx) => {
    try {
      ctx.audioContext?.close();
    } catch (e) {}
  });
  audioContexts.value.clear();
};

// ============================================
// Media Controls - Using track.enabled ONLY
// ============================================
const toggleAudio = async () => {
  if (meetingEnded.value) return;

  const newMutedState = !isMuted.value;
  isMuted.value = newMutedState;

  // Use enabled, NEVER stop
  if (localStream.value) {
    localStream.value.getAudioTracks().forEach((track) => {
      track.enabled = !newMutedState;
    });
  }

  webrtcService.value?.toggleAudio(!newMutedState);

  try {
    await api.post(`/meetings/${meeting.value.id}/toggle-audio`);
  } catch (e) {
    console.error("Failed to sync audio state:", e);
  }
};

const toggleVideo = async () => {
  if (meetingEnded.value) return;

  const newVideoOffState = !isVideoOff.value;
  isVideoOff.value = newVideoOffState;

  // Use enabled, NEVER stop
  if (localStream.value) {
    localStream.value.getVideoTracks().forEach((track) => {
      track.enabled = !newVideoOffState;
    });
  }

  webrtcService.value?.toggleVideo(!newVideoOffState);

  try {
    await api.post(`/meetings/${meeting.value.id}/toggle-video`);
  } catch (e) {
    console.error("Failed to sync video state:", e);
  }
};

const toggleHandRaise = async () => {
  if (meetingEnded.value) return;

  const newState = !isHandRaised.value;
  isHandRaised.value = newState;

  const endpoint = newState ? "raise-hand" : "lower-hand";

  try {
    await api.post(`/meetings/${meeting.value.id}/${endpoint}`);
  } catch (e) {
    isHandRaised.value = !newState;
    window.$toast?.error("Failed to update hand status");
  }
};

const toggleScreenShare = async () => {
  if (meetingEnded.value) return;

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
  if (meetingEnded.value) return;

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

// ... (keep all other existing functions: togglePanel, sendChatMessage, etc.)

const togglePanel = (panel) => {
  if (activePanel.value === panel && showSidePanel.value) {
    showSidePanel.value = false;
  } else {
    activePanel.value = panel;
    showSidePanel.value = true;
    if (panel === "chat") unreadMessages.value = 0;
  }
};

const fetchChatMessages = async () => {
  if (!meeting.value?.is_chat_enabled) return;
  try {
    const response = await api.get(`/meetings/${meeting.value.id}/messages`);
    chatMessages.value = response.data.data || [];
  } catch (e) {
    console.error("Failed to fetch chat:", e);
  }
};

const fetchWhiteboardStrokes = async () => {
  if (!meeting.value?.is_whiteboard_enabled) return;
  try {
    const response = await api.get(`/meetings/${meeting.value.id}/whiteboard`);
    whiteboardStrokes.value = response.data.strokes || [];
  } catch (e) {
    console.error("Failed to fetch whiteboard:", e);
  }
};

const sendChatMessage = async (message) => {
  if (meetingEnded.value) return;
  try {
    const response = await api.post(`/meetings/${meeting.value.id}/messages`, {
      message,
    });
    chatMessages.value.push(response.data.message);
  } catch (e) {
    window.$toast?.error("Failed to send message");
  }
};

const handleWhiteboardDraw = async (stroke) => {
  if (meetingEnded.value) return;
  try {
    await api.post(`/meetings/${meeting.value.id}/whiteboard`, stroke);
  } catch (e) {
    console.error("Failed to save stroke:", e);
  }
};

const handleWhiteboardClear = async () => {
  if (meetingEnded.value) return;
  try {
    await api.post(`/meetings/${meeting.value.id}/whiteboard/clear`);
    whiteboardStrokes.value = [];
  } catch (e) {
    window.$toast?.error("Failed to clear whiteboard");
  }
};

const handleMuteParticipant = async (participant) => {
  if (meetingEnded.value) return;
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
  if (meetingEnded.value) return;
  if (!confirm(`Remove ${participant.display_name} from the meeting?`)) return;
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
  if (meetingEnded.value) return;
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

  try {
    await meetingStore.endMeeting(meeting.value.id);
    cleanup();
    router.push("/dashboard");
  } catch (e) {
    window.$toast?.error("Failed to end meeting");
  }
};

const forceEndMeeting = () => {
  // console.log("[MeetingRoom] Force ending meeting");

  if (durationInterval) {
    clearInterval(durationInterval);
    durationInterval = null;
  }

  cleanup();
  router.push("/dashboard");
};

// ============================================
// CLEANUP - CRITICAL for hardware release
// ============================================
const cleanup = () => {
  // console.log("[MeetingRoom] Running complete cleanup...");

  // Stop duration timer
  if (durationInterval) {
    clearInterval(durationInterval);
    durationInterval = null;
  }

  // Stop speaker detection
  stopSpeakerDetection();

  // Stop recording
  if (isRecording.value) {
    recordingService.value.stop().catch(() => {});
  }

  // CRITICAL: Destroy WebRTC service - releases hardware
  if (webrtcService.value) {
    webrtcService.value.destroy();
    webrtcService.value = null;
  }

  // Stop local stream tracks explicitly (belt and suspenders)
  if (localStream.value) {
    localStream.value.getTracks().forEach((track) => {
      track.stop();
      // console.log(`[Cleanup] Stopped local ${track.kind} track`);
    });
    localStream.value = null;
  }

  // Leave Echo channels
  if (meeting.value?.id) {
    window.Echo.leave(`meeting.${meeting.value.id}`);
    window.Echo.leave(`chat.${meeting.value.id}`);
    window.Echo.leave(`whiteboard.${meeting.value.id}`);
  }

  // Clear meeting store
  meetingStore.clearMeeting();

  // Clear session
  sessionStorage.removeItem("joinPrefs");

  // console.log("[MeetingRoom] Cleanup complete, hardware released");
};

// Handle browser/tab close
const handleBeforeUnload = () => {
  cleanup();
};

window.addEventListener("beforeunload", handleBeforeUnload);

onBeforeUnmount(() => {
  window.removeEventListener("beforeunload", handleBeforeUnload);
});
</script>
