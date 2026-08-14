let speakTimeout: any = null;
let isLoopingCount: number = 0; // Using a count as an ID to cancel superseded loops

export type VoiceMessage = string | { text: string; pause?: number };

export const stopSpeaking = () => {
    isLoopingCount++; // invalidate any active loop
    if (speakTimeout) clearTimeout(speakTimeout);
    if (typeof window !== 'undefined' && window.speechSynthesis) {
        window.speechSynthesis.cancel();
    }
};

export const speak = (input: VoiceMessage | VoiceMessage[], options?: { loop?: boolean }) => {
    if (typeof window === 'undefined' || !window.speechSynthesis) return;

    // Cancel any existing speech and timeout
    stopSpeaking();
    
    const currentLoopId = isLoopingCount;
    const items = Array.isArray(input) ? input : [input];
    let index = 0;
    let consecutiveErrors = 0;

    const speakItem = () => {
        if (currentLoopId !== isLoopingCount) return; // A newer call cancelled this

        if (index >= items.length) {
            if (options?.loop) {
                index = 0; // restart
            } else {
                return; // done
            }
        }

        const current = items[index];
        const text = typeof current === 'string' ? current : current.text;
        const pause = typeof current === 'string' ? 0 : (current.pause || 0);

        // Create utterance
        const utterance = new SpeechSynthesisUtterance(text);
        const voices = window.speechSynthesis.getVoices();

        // Expanded search for female voices
        const femaleFound = voices.find(v => {
            const name = v.name.toLowerCase();
            // Ensure English
            if (!v.lang.startsWith('en')) return false;

            return (
                name.includes('female') ||
                name.includes('zira') ||
                name.includes('samantha') ||
                name.includes('google us english') ||
                name.includes('victoria') ||
                name.includes('hazel') ||
                name.includes('susan') ||
                name.includes('monica') ||
                name.includes('heera') ||
                name.includes('shruthi') ||
                name.includes('neur') // Neural voices often have names like this
            );
        });

        if (femaleFound) {
            utterance.voice = femaleFound;
        }

        utterance.rate = 0.9;
        utterance.pitch = 1.1; // Slightly higher pitch often sounds more feminine
        utterance.volume = 1;

        utterance.onend = () => {
            if (currentLoopId !== isLoopingCount) return;
            consecutiveErrors = 0;
            index++;
            // Apply pause before next message asynchronously
            const delay = pause > 0 ? pause : 10;
            speakTimeout = setTimeout(speakItem, delay);
        };

        // Handle errors (e.g. if speech is blocked by browser policy)
        utterance.onerror = (e) => {
            if (currentLoopId !== isLoopingCount) return;

            // If error is 'not-allowed', 'canceled', or 'interrupted', browser policy blocked speech
            if (e.error === 'not-allowed' || e.error === 'canceled' || e.error === 'interrupted') {
                console.warn(`Speech synthesis skipped (${e.error}). User interaction or permissions required.`);
                stopSpeaking();
                return;
            }

            consecutiveErrors++;
            if (consecutiveErrors >= Math.max(3, items.length)) {
                console.warn("Speech synthesis stopped due to repeated errors.");
                stopSpeaking();
                return;
            }

            console.error("Speech synthesis error:", e);
            index++;
            speakTimeout = setTimeout(speakItem, 100);
        };

        try {
            window.speechSynthesis.speak(utterance);
        } catch (err) {
            console.warn("Speech synthesis trigger failed:", err);
            stopSpeaking();
        }
    };

    // Ensure voices are loaded (Chrome sometimes needs this)
    if (window.speechSynthesis.getVoices().length === 0) {
        window.speechSynthesis.onvoiceschanged = () => {
            if (currentLoopId !== isLoopingCount) return;
            speakItem();
            window.speechSynthesis.onvoiceschanged = null;
        };
    } else {
        speakItem();
    }
};
