import RecordRTC from "recordrtc";
import api from "./api";

export class RecordingService {
    constructor() {
        this.recorder = null;
        this.recordingId = null;
        this.meetingId = null;
        this.startTime = null;
    }

    async start(meetingId, streams) {
        this.meetingId = meetingId;

        // Combine all streams
        const combinedStream = new MediaStream();

        streams.forEach((stream) => {
            stream.getTracks().forEach((track) => {
                combinedStream.addTrack(track);
            });
        });

        // Start recording via API
        const response = await api.post(
            `/meetings/${meetingId}/recordings/start`,
        );
        this.recordingId = response.data.recording.id;

        // Initialize RecordRTC
        this.recorder = new RecordRTC(combinedStream, {
            type: "video",
            mimeType: "video/webm;codecs=vp9",
            bitsPerSecond: 2500000,
            frameRate: 30,
        });

        this.recorder.startRecording();
        this.startTime = Date.now();

        return this.recordingId;
    }

    async stop() {
        if (!this.recorder || !this.recordingId) {
            return null;
        }

        return new Promise((resolve, reject) => {
            this.recorder.stopRecording(async () => {
                try {
                    const blob = this.recorder.getBlob();
                    const duration = Math.floor(
                        (Date.now() - this.startTime) / 1000,
                    );

                    // Stop recording on server
                    await api.post(
                        `/meetings/${this.meetingId}/recordings/${this.recordingId}/stop`,
                    );

                    // Upload the recording
                    const formData = new FormData();
                    formData.append("recording", blob, "recording.webm");
                    formData.append("duration", duration.toString());

                    const response = await api.post(
                        `/recordings/${this.recordingId}/upload`,
                        formData,
                        {
                            headers: {
                                "Content-Type": "multipart/form-data",
                            },
                        },
                    );

                    this.reset();
                    resolve(response.data.recording);
                } catch (error) {
                    reject(error);
                }
            });
        });
    }

    pause() {
        if (this.recorder) {
            this.recorder.pauseRecording();
        }
    }

    resume() {
        if (this.recorder) {
            this.recorder.resumeRecording();
        }
    }

    reset() {
        if (this.recorder) {
            this.recorder.destroy();
            this.recorder = null;
        }
        this.recordingId = null;
        this.meetingId = null;
        this.startTime = null;
    }

    isRecording() {
        return this.recorder !== null && this.recorder.state === "recording";
    }

    getDuration() {
        if (!this.startTime) return 0;
        return Math.floor((Date.now() - this.startTime) / 1000);
    }
}

export default RecordingService;
