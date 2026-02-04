import api from './api';

// ICE Server Configuration
const ICE_SERVERS = [
    { urls: 'stun:stun.l.google.com:19302' },
    { urls: 'stun:stun1.l.google.com:19302' },
    { urls: 'stun:stun2.l.google.com:19302' },
    {
        urls: 'turn:coturn.rrstuah.my.id:3478', // Port standar
        username: 'imsmeetuser',
        credential: 'imsmeetp@ssword',
    },
    {
        urls: 'turns:coturn.rrstuah.my.id:5349', // Port TLS/SSL
        username: 'imsmeetuser',
        credential: 'imsmeetp@ssword',
    }
    // Tambahkan TURN server untuk production
    // {
    //     urls: 'turn:your-turn-server.com:3478',
    //     username: 'username',
    //     credential: 'password',
    // },
];

function encodeSDP(sdp) {
    return btoa(unescape(encodeURIComponent(sdp)));
}

// Helper: Decode SDP dari Base64
function decodeSDP(encoded) {
    return decodeURIComponent(escape(atob(encoded)));
}

export class WebRTCService {
    constructor(meetingId, userId, onRemoteStream, onRemoveStream) {
        this.meetingId = meetingId;
        this.userId = userId;
        this.onRemoteStream = onRemoteStream;
        this.onRemoveStream = onRemoveStream;
        
        this.localStream = null;
        this.peers = new Map();
        this.pendingCandidates = new Map();
        
        console.log('[WebRTC] Service initialized for user:', userId);
    }

    async initLocalStream(audio = true, video = true) {
        console.log('[WebRTC] Initializing local stream...');
        
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: audio ? {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                } : false,
                video: video ? {
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 },
                    facingMode: 'user',
                } : false,
            });
            
            console.log('[WebRTC] Local stream obtained');
            return this.localStream;
            
        } catch (error) {
            console.error('[WebRTC] Failed to get local stream:', error);
            
            if (video) {
                console.log('[WebRTC] Retrying with audio only...');
                return this.initLocalStream(audio, false);
            }
            
            throw error;
        }
    }

    createPeer(remoteUserId, initiator = false) {
        console.log(`[WebRTC] Creating peer for user ${remoteUserId}, initiator: ${initiator}`);
        
        if (this.peers.has(remoteUserId)) {
            this.peers.get(remoteUserId).close();
            this.peers.delete(remoteUserId);
        }

        const pc = new RTCPeerConnection({
            iceServers: ICE_SERVERS,
        });

        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                console.log(`[WebRTC] Adding track: ${track.kind}`);
                pc.addTrack(track, this.localStream);
            });
        }

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                console.log(`[WebRTC] Sending ICE candidate to user ${remoteUserId}`);
                this.sendIceCandidate(remoteUserId, event.candidate);
            }
        };

        pc.onconnectionstatechange = () => {
            console.log(`[WebRTC] Connection state with ${remoteUserId}: ${pc.connectionState}`);
        };

        pc.oniceconnectionstatechange = () => {
            console.log(`[WebRTC] ICE state with ${remoteUserId}: ${pc.iceConnectionState}`);
        };

        pc.ontrack = (event) => {
            console.log(`[WebRTC] Received track from ${remoteUserId}: ${event.track.kind}`);
            
            if (event.streams && event.streams[0]) {
                this.onRemoteStream(remoteUserId, event.streams[0]);
            }
        };

        this.peers.set(remoteUserId, pc);

        if (initiator) {
            this.createOffer(remoteUserId);
        }

        return pc;
    }

    async createOffer(remoteUserId) {
        const pc = this.peers.get(remoteUserId);
        if (!pc) return;

        try {
            console.log(`[WebRTC] Creating offer for user ${remoteUserId}`);
            
            const offer = await pc.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true,
            });
            
            await pc.setLocalDescription(offer);
            
            // Encode SDP sebelum kirim
            const encodedSdp = encodeSDP(offer.sdp);
            
            console.log(`[WebRTC] Sending offer to user ${remoteUserId}`);
            await this.sendSignal(remoteUserId, 'offer', encodedSdp);
            
        } catch (error) {
            console.error(`[WebRTC] Failed to create offer:`, error);
        }
    }

    async handleSignal(fromUserId, type, encodedSdp) {
        console.log(`[WebRTC] Received ${type} from user ${fromUserId}`);
        
        // Decode SDP
        let sdp;
        try {
            sdp = decodeSDP(encodedSdp);
        } catch (e) {
            console.error('[WebRTC] Failed to decode SDP:', e);
            return;
        }
        
        let pc = this.peers.get(fromUserId);
        
        if (type === 'offer') {
            if (!pc) {
                pc = this.createPeer(fromUserId, false);
            }
            
            try {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'offer',
                    sdp: sdp,
                }));
                
                await this.applyPendingCandidates(fromUserId);
                
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                
                // Encode SDP sebelum kirim
                const encodedAnswer = encodeSDP(answer.sdp);
                
                console.log(`[WebRTC] Sending answer to user ${fromUserId}`);
                await this.sendSignal(fromUserId, 'answer', encodedAnswer);
                
            } catch (error) {
                console.error(`[WebRTC] Failed to handle offer:`, error);
            }
            
        } else if (type === 'answer') {
            if (!pc) {
                console.error(`[WebRTC] No peer connection for answer from ${fromUserId}`);
                return;
            }
            
            try {
                await pc.setRemoteDescription(new RTCSessionDescription({
                    type: 'answer',
                    sdp: sdp,
                }));
                
                await this.applyPendingCandidates(fromUserId);
                
            } catch (error) {
                console.error(`[WebRTC] Failed to handle answer:`, error);
            }
        }
    }

    async handleIceCandidate(fromUserId, candidate) {
        console.log(`[WebRTC] Received ICE candidate from user ${fromUserId}`);
        
        const pc = this.peers.get(fromUserId);
        
        if (!pc || !pc.remoteDescription) {
            console.log(`[WebRTC] Queuing ICE candidate for user ${fromUserId}`);
            if (!this.pendingCandidates.has(fromUserId)) {
                this.pendingCandidates.set(fromUserId, []);
            }
            this.pendingCandidates.get(fromUserId).push(candidate);
            return;
        }
        
        try {
            await pc.addIceCandidate(new RTCIceCandidate(candidate));
            console.log(`[WebRTC] Added ICE candidate from user ${fromUserId}`);
        } catch (error) {
            console.error(`[WebRTC] Failed to add ICE candidate:`, error);
        }
    }

    async applyPendingCandidates(userId) {
        const candidates = this.pendingCandidates.get(userId);
        if (!candidates || candidates.length === 0) return;
        
        const pc = this.peers.get(userId);
        if (!pc) return;
        
        console.log(`[WebRTC] Applying ${candidates.length} pending candidates for user ${userId}`);
        
        for (const candidate of candidates) {
            try {
                await pc.addIceCandidate(new RTCIceCandidate(candidate));
            } catch (error) {
                console.error('[WebRTC] Failed to add pending candidate:', error);
            }
        }
        
        this.pendingCandidates.delete(userId);
    }

    async sendSignal(targetUserId, type, sdp) {
        console.log(`[WebRTC] Sending ${type} to user ${targetUserId}`);
        
        try {
            await api.post(`/meetings/${this.meetingId}/signal`, {
                target_user_id: targetUserId,
                type: type,
                sdp: sdp,
            });
            console.log(`[WebRTC] Signal sent successfully`);
        } catch (error) {
            console.error('[WebRTC] Failed to send signal:', error.response?.data || error);
        }
    }

    async sendIceCandidate(targetUserId, candidate) {
        try {
            await api.post(`/meetings/${this.meetingId}/ice-candidate`, {
                target_user_id: targetUserId,
                candidate: candidate.toJSON(),
            });
        } catch (error) {
            console.error('[WebRTC] Failed to send ICE candidate:', error.response?.data || error);
        }
    }

    connectToParticipant(userId) {
        console.log(`[WebRTC] Connecting to participant ${userId}`);
        this.createPeer(userId, true);
    }

    disconnectFromParticipant(userId) {
        console.log(`[WebRTC] Disconnecting from participant ${userId}`);
        
        const pc = this.peers.get(userId);
        if (pc) {
            pc.close();
            this.peers.delete(userId);
        }
        
        this.pendingCandidates.delete(userId);
        this.onRemoveStream(userId);
    }

    toggleAudio(enabled) {
        if (this.localStream) {
            this.localStream.getAudioTracks().forEach(track => {
                track.enabled = enabled;
            });
        }
    }

    toggleVideo(enabled) {
        if (this.localStream) {
            this.localStream.getVideoTracks().forEach(track => {
                track.enabled = enabled;
            });
        }
    }

    async startScreenShare() {
        try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: { cursor: 'always' },
                audio: false,
            });
            
            const screenTrack = screenStream.getVideoTracks()[0];
            
            this.peers.forEach((pc, odtUserId) => {
                const sender = pc.getSenders().find(s => s.track?.kind === 'video');
                if (sender) {
                    sender.replaceTrack(screenTrack);
                }
            });
            
            screenTrack.onended = () => {
                this.stopScreenShare();
            };
            
            return screenStream;
            
        } catch (error) {
            console.error('[WebRTC] Failed to start screen share:', error);
            throw error;
        }
    }

    async stopScreenShare() {
        if (!this.localStream) return;
        
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (!videoTrack) return;
        
        this.peers.forEach((pc) => {
            const sender = pc.getSenders().find(s => s.track?.kind === 'video');
            if (sender) {
                sender.replaceTrack(videoTrack);
            }
        });
    }

    destroy() {
        console.log('[WebRTC] Destroying service...');
        
        this.peers.forEach((pc) => {
            pc.close();
        });
        this.peers.clear();
        this.pendingCandidates.clear();
        
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                track.stop();
            });
            this.localStream = null;
        }
    }
}

export default WebRTCService;
