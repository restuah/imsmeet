import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "../services/api";

export const useMeetingStore = defineStore("meeting", () => {
    const meetings = ref([]);
    const currentMeeting = ref(null);
    const participants = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const activeMeetings = computed(() =>
        meetings.value.filter((m) => m.status === "active"),
    );

    const scheduledMeetings = computed(() =>
        meetings.value.filter((m) => m.status === "scheduled"),
    );

    async function fetchMeetings(params = {}) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get("/meetings", { params });
            meetings.value = response.data.data;
            return response.data;
        } catch (e) {
            error.value =
                e.response?.data?.message || "Failed to fetch meetings";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMeeting(id) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get(`/meetings/${id}`);
            currentMeeting.value = response.data.meeting;
            return response.data.meeting;
        } catch (e) {
            error.value =
                e.response?.data?.message || "Failed to fetch meeting";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMeetingByUuid(uuid) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.get(`/meetings/join/${uuid}`);
            return response.data.meeting;
        } catch (e) {
            error.value = e.response?.data?.message || "Meeting not found";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function createMeeting(data) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.post("/meetings", data);
            meetings.value.unshift(response.data.meeting);
            return response.data.meeting;
        } catch (e) {
            error.value =
                e.response?.data?.message || "Failed to create meeting";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function updateMeeting(id, data) {
        loading.value = true;
        error.value = null;
        try {
            const response = await api.put(`/meetings/${id}`, data);
            const index = meetings.value.findIndex((m) => m.id === id);
            if (index !== -1) {
                meetings.value[index] = response.data.meeting;
            }
            if (currentMeeting.value?.id === id) {
                currentMeeting.value = response.data.meeting;
            }
            return response.data.meeting;
        } catch (e) {
            error.value =
                e.response?.data?.message || "Failed to update meeting";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function deleteMeeting(id) {
        loading.value = true;
        error.value = null;
        try {
            await api.delete(`/meetings/${id}`);
            meetings.value = meetings.value.filter((m) => m.id !== id);
        } catch (e) {
            error.value =
                e.response?.data?.message || "Failed to delete meeting";
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function startMeeting(id) {
        const response = await api.post(`/meetings/${id}/start`);
        if (currentMeeting.value?.id === id) {
            currentMeeting.value = response.data.meeting;
        }
        return response.data.meeting;
    }

    async function endMeeting(id) {
        const response = await api.post(`/meetings/${id}/end`);
        if (currentMeeting.value?.id === id) {
            currentMeeting.value = response.data.meeting;
        }
        return response.data.meeting;
    }

    async function joinMeeting(id, data = {}) {
        const response = await api.post(`/meetings/${id}/join`, data);
        currentMeeting.value = response.data.meeting;
        return response.data;
    }

    async function leaveMeeting(id) {
        await api.post(`/meetings/${id}/leave`);
        currentMeeting.value = null;
        participants.value = [];
    }

    async function fetchParticipants(meetingId) {
        const response = await api.get(`/meetings/${meetingId}/participants`);
        participants.value = response.data.participants;
        return response.data.participants;
    }

    function addParticipant(participant) {
        const exists = participants.value.find(
            (p) => p.user_id === participant.user_id,
        );
        if (!exists) {
            participants.value.push(participant);
        }
    }

    function removeParticipant(userId) {
        participants.value = participants.value.filter(
            (p) => p.user_id !== userId,
        );
    }

    function updateParticipant(participant) {
        const index = participants.value.findIndex(
            (p) => p.id === participant.id,
        );
        if (index !== -1) {
            participants.value[index] = {
                ...participants.value[index],
                ...participant,
            };
        }
    }

    function clearMeeting() {
        currentMeeting.value = null;
        participants.value = [];
    }

    return {
        meetings,
        currentMeeting,
        participants,
        loading,
        error,
        activeMeetings,
        scheduledMeetings,
        fetchMeetings,
        fetchMeeting,
        fetchMeetingByUuid,
        createMeeting,
        updateMeeting,
        deleteMeeting,
        startMeeting,
        endMeeting,
        joinMeeting,
        leaveMeeting,
        fetchParticipants,
        addParticipant,
        removeParticipant,
        updateParticipant,
        clearMeeting,
    };
});
