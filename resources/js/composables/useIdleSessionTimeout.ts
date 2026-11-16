const SESSION_IDLE_TIMEOUT_MS = 15 * 60 * 1000;
const SESSION_ACTIVITY_KEY = 'meu-negocio:last-session-activity';
const SESSION_LOGOUT_KEY = 'meu-negocio:session-logout';

let activeStop: (() => void) | null = null;

const reportStorageError = (error: unknown) => {
    console.error('Não foi possível sincronizar a sessão entre abas:', error);
};

export const stopIdleSessionTimeout = () => {
    activeStop?.();
};

export const startIdleSessionTimeout = (
    onTimeout: () => void,
    onRemoteLogout: () => void,
) => {
    stopIdleSessionTimeout();

    let stopped = false;
    let lastActivityAt = Date.now();
    let lastActivityBroadcastAt = 0;
    let lastTimerResetAt = 0;
    let timeoutId = 0;
    let storageUnavailable = false;

    const handleStorageError = (error: unknown) => {
        if (!storageUnavailable) {
            reportStorageError(error);
            storageUnavailable = true;
        }
    };

    const readSharedActivity = () => {
        if (storageUnavailable) {
            return 0;
        }

        try {
            const timestamp = Number(
                window.localStorage.getItem(SESSION_ACTIVITY_KEY),
            );
            return Number.isFinite(timestamp) && timestamp > 0 ? timestamp : 0;
        } catch (error) {
            handleStorageError(error);
            return 0;
        }
    };

    const stop = () => {
        if (stopped) {
            return;
        }

        stopped = true;
        window.clearTimeout(timeoutId);
        activityEvents.forEach((eventName) => {
            document.removeEventListener(eventName, recordActivity);
        });
        window.removeEventListener('storage', handleStorage);

        if (activeStop === stop) {
            activeStop = null;
        }
    };

    const expireSession = () => {
        if (stopped) {
            return;
        }

        stop();

        try {
            window.localStorage.setItem(SESSION_LOGOUT_KEY, String(Date.now()));
        } catch (error) {
            handleStorageError(error);
        }

        onTimeout();
    };

    const scheduleTimeout = () => {
        if (stopped) {
            return;
        }

        window.clearTimeout(timeoutId);
        lastActivityAt = Math.max(lastActivityAt, readSharedActivity());
        const remainingTime =
            SESSION_IDLE_TIMEOUT_MS - (Date.now() - lastActivityAt);

        if (remainingTime <= 0) {
            expireSession();
            return;
        }

        timeoutId = window.setTimeout(() => {
            lastActivityAt = Math.max(lastActivityAt, readSharedActivity());

            if (Date.now() - lastActivityAt >= SESSION_IDLE_TIMEOUT_MS) {
                expireSession();
                return;
            }

            scheduleTimeout();
        }, remainingTime);
    };

    function recordActivity() {
        const now = Date.now();
        lastActivityAt = now;

        if (now - lastActivityBroadcastAt >= 5000) {
            try {
                window.localStorage.setItem(SESSION_ACTIVITY_KEY, String(now));
                lastActivityBroadcastAt = now;
            } catch (error) {
                handleStorageError(error);
            }
        }

        if (now - lastTimerResetAt >= 1000) {
            lastTimerResetAt = now;
            scheduleTimeout();
        }
    }

    function handleStorage(event: StorageEvent) {
        if (event.key === SESSION_LOGOUT_KEY && event.newValue) {
            stop();
            onRemoteLogout();
            return;
        }

        if (event.key === SESSION_ACTIVITY_KEY) {
            const timestamp = Number(event.newValue);

            if (Number.isFinite(timestamp) && timestamp > lastActivityAt) {
                lastActivityAt = timestamp;
                scheduleTimeout();
            }
        }
    }

    const activityEvents: Array<keyof DocumentEventMap> = [
        'pointerdown',
        'keydown',
        'mousemove',
        'scroll',
        'touchstart',
        'input',
    ];

    try {
        window.localStorage.removeItem(SESSION_LOGOUT_KEY);
        window.localStorage.setItem(
            SESSION_ACTIVITY_KEY,
            String(lastActivityAt),
        );
        lastActivityBroadcastAt = lastActivityAt;
    } catch (error) {
        handleStorageError(error);
    }

    activityEvents.forEach((eventName) => {
        document.addEventListener(eventName, recordActivity, { passive: true });
    });
    window.addEventListener('storage', handleStorage);

    activeStop = stop;
    scheduleTimeout();
};
