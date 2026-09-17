const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;


document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-text]');
    if (!button) return;

    const text = button.dataset.copyText || '';
    if (!text) return;

    const original = button.textContent;
    try {
        await navigator.clipboard.writeText(text);
        button.textContent = 'コピーしました';
    } catch (_) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
        button.textContent = 'コピーしました';
    }
    window.setTimeout(() => { button.textContent = original; }, 1600);
});

function recordBehavior(root, eventType, payload = {}) {
    if (!root?.dataset.eventUrl || !csrfToken) return;

    fetch(root.dataset.eventUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        keepalive: true,
        body: JSON.stringify({ event_type: eventType, ...payload }),
    }).catch(() => {});
}

function formatTimer(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return hours > 0
        ? `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
        : `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

function updateTimers() {
    document.querySelectorAll('[data-work-timer]').forEach((element) => {
        const startedAt = Date.parse(element.dataset.startedAt || '');
        if (!Number.isFinite(startedAt)) return;

        const pausedSeconds = Number(element.dataset.pausedSeconds || 0);
        const pausedAt = Date.parse(element.dataset.pausedAt || '');
        const status = element.dataset.sessionStatus || 'active';
        const now = Date.now();
        let currentPauseSeconds = 0;

        if (status === 'paused' && Number.isFinite(pausedAt)) {
            currentPauseSeconds = Math.max(0, Math.floor((now - pausedAt) / 1000));
        }

        const wallSeconds = Math.max(0, Math.floor((now - startedAt) / 1000));
        const activeSeconds = Math.max(0, wallSeconds - pausedSeconds - currentPauseSeconds);
        element.textContent = formatTimer(activeSeconds);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateTimers();
    window.setInterval(updateTimers, 1000);

    const reviewRoot = document.getElementById('workSessionReview');
    if (reviewRoot) {
        const continuationFields = reviewRoot.querySelector('[data-continuation-fields]');
        const outcomeInputs = [...reviewRoot.querySelectorAll('input[name="task_outcome"]')];
        const updateContinuationFields = () => {
            const selected = outcomeInputs.find((input) => input.checked)?.value;
            continuationFields?.classList.toggle('hidden', selected !== 'checkpoint');
        };
        outcomeInputs.forEach((input) => input.addEventListener('change', updateContinuationFields));
        updateContinuationFields();
    }

    const root = document.getElementById('behaviorDashboard');
    if (!root) return;

    let planSwitches = 0;
    let taskViews = 0;
    let workStarted = root.dataset.workStarted === '1';
    let idleNudgeShown = false;
    const enteredAt = Date.now();
    let activeTarget = 'overall';
    const viewedTaskIds = new Set();
    const tabs = [...root.querySelectorAll('[data-dashboard-tab]')];
    const panels = [...root.querySelectorAll('[data-dashboard-panel]')];

    function updateNavigationContext(planId = null) {
        const baseUrl = root.dataset.navigationUrl;
        if (!baseUrl) return;

        const url = new URL(baseUrl, window.location.origin);
        if (planId) {
            url.searchParams.set('plan_id', String(planId));
        } else {
            url.searchParams.delete('plan_id');
        }

        document.querySelectorAll('[data-navigation-link]').forEach((link) => {
            link.href = url.pathname + url.search;
        });
    }

    function recordTaskView(details) {
        const taskId = Number(details?.dataset.taskId);
        if (!taskId || viewedTaskIds.has(taskId)) return;
        viewedTaskIds.add(taskId);
        taskViews = viewedTaskIds.size;
        recordBehavior(root, 'task_viewed', {
            plan_id: Number(details.dataset.planId),
            task_id: taskId,
            metadata: { task_views: taskViews },
        });
    }

    function activateTab(target) {
        if (target === activeTarget) return;
        activeTarget = target;
        tabs.forEach((tab) => {
            const active = tab.dataset.dashboardTab === target;
            tab.classList.toggle('nav-link-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.dashboardPanel !== target));

        const tab = tabs.find((item) => item.dataset.dashboardTab === target);
        const contextPlanId = tab?.dataset.planId ? Number(tab.dataset.planId) : null;
        updateNavigationContext(contextPlanId);

        if (contextPlanId) {
            planSwitches += 1;
            recordBehavior(root, 'plan_tab_viewed', { plan_id: contextPlanId, metadata: { plan_switches: planSwitches } });
            const panel = panels.find((item) => item.dataset.dashboardPanel === target);
            recordTaskView(panel?.querySelector('[data-task-view]'));
        }
    }

    updateNavigationContext(null);
    tabs.forEach((tab) => tab.addEventListener('click', () => activateTab(tab.dataset.dashboardTab)));
    root.querySelectorAll('[data-open-dashboard-tab]').forEach((button) => button.addEventListener('click', () => activateTab(button.dataset.openDashboardTab)));
    root.querySelectorAll('[data-task-view]').forEach((element) => element.addEventListener('click', () => recordTaskView(element)));
    document.querySelectorAll('[data-work-start-form]').forEach((form) => form.addEventListener('submit', () => { workStarted = true; }));

    window.setInterval(() => {
        const elapsedSeconds = Math.floor((Date.now() - enteredAt) / 1000);
        if (document.visibilityState !== 'visible' || workStarted || elapsedSeconds < 90 || planSwitches + taskViews < 2) return;
        if (!idleNudgeShown) {
            root.querySelector('[data-idle-nudge]')?.classList.remove('hidden');
            idleNudgeShown = true;
        }
        recordBehavior(root, 'dashboard_idle', {
            metadata: {
                elapsed_seconds: elapsedSeconds,
                plan_switches: planSwitches,
                task_views: taskViews,
                page_visible: true,
                work_started: false,
            },
        });
    }, 15000);
});

// -----------------------------------------------------------------------------
// Mobile app shell enhancements
// -----------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // Remove the one-shot Instant Start network-only flag after the real app
    // has loaded successfully, without triggering another navigation.
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('_pk_network')) {
        currentUrl.searchParams.delete('_pk_network');
        window.history.replaceState({}, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
    }

    const mobileBack = document.querySelector('[data-mobile-back]');
    mobileBack?.addEventListener('click', () => {
        if (window.history.length > 1) {
            window.history.back();
            return;
        }
        window.location.href = '/';
    });

    document.querySelectorAll('[data-auto-toast]').forEach((toast) => {
        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(0.5rem)';
            window.setTimeout(() => toast.remove(), 220);
        }, 3600);
    });

    const loadingOverlay = document.querySelector('[data-route-loading]');
    let loadingTimer = null;

    const showLoading = () => {
        if (!loadingOverlay) return;
        window.clearTimeout(loadingTimer);
        loadingTimer = window.setTimeout(() => {
            loadingOverlay.classList.add('is-visible');
            loadingOverlay.setAttribute('aria-hidden', 'false');
        }, 220);
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.target === '_blank') return;
        if (form.hasAttribute('data-loading-skip')) return;
        showLoading();
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (link.href.startsWith('mailto:') || link.href.startsWith('tel:')) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
        showLoading();
    });

    window.addEventListener('pageshow', () => {
        window.clearTimeout(loadingTimer);
        loadingOverlay?.classList.remove('is-visible');
        loadingOverlay?.setAttribute('aria-hidden', 'true');
    });

    document.querySelectorAll('[data-async-plan-review]').forEach((form) => {
        const submitButton = form.querySelector('[data-async-plan-review-submit]');
        const statusBox = form.querySelector('[data-async-plan-review-status]');
        let inFlight = false;

        const setStatus = (message, mode = 'info') => {
            if (!statusBox) return;
            statusBox.textContent = message;
            statusBox.classList.remove('hidden', 'border-red-400/30', 'bg-red-400/10', 'text-red-100', 'border-cyan-300/20', 'bg-cyan-300/5', 'text-cyan-100');
            if (mode === 'error') {
                statusBox.classList.add('border-red-400/30', 'bg-red-400/10', 'text-red-100');
            } else {
                statusBox.classList.add('border-cyan-300/20', 'bg-cyan-300/5', 'text-cyan-100');
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (inFlight) return;

            // Async review forms manage their own loading state. Make sure an
            // older/global overlay can never trap the user on this screen.
            window.clearTimeout(loadingTimer);
            loadingOverlay?.classList.remove('is-visible');
            loadingOverlay?.setAttribute('aria-hidden', 'true');

            inFlight = true;
            const originalLabel = submitButton?.textContent || '送信';
            const isPromptGeneration = form.action.includes('/review-assistant/prompt');
            const isReset = form.action.includes('/review-assistant/reset');
            const pendingLabel = isPromptGeneration ? 'プロンプトを生成中…' : (isReset ? 'リセット中…' : '変更内容を確認中…');
            const pendingMessage = isPromptGeneration
                ? 'AI用プロンプトを生成しています。通常は数秒で完了します。'
                : (isReset ? '入力内容をリセットしています。' : 'AIの更新内容を確認しています。通常は数秒で完了します。');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = pendingLabel;
            }
            setStatus(pendingMessage);

            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 25000);
            let navigating = false;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                let payload = null;
                try {
                    payload = await response.json();
                } catch (_) {}

                if (!response.ok) {
                    const errors = payload?.errors ? Object.values(payload.errors).flat() : [];
                    throw new Error(errors[0] || payload?.message || `処理に失敗しました (${response.status})`);
                }

                const redirect = payload?.redirect;
                if (!redirect) {
                    throw new Error('次の画面の移動先を取得できませんでした。もう一度お試しください。');
                }

                setStatus(payload?.message || '完了しました。画面を更新します。');
                navigating = true;
                window.location.assign(redirect);
            } catch (error) {
                if (error?.name === 'AbortError') {
                    setStatus('処理に時間がかかりすぎたため中断しました。通信状態を確認して、もう一度お試しください。', 'error');
                } else {
                    setStatus(error?.message || '処理を完了できませんでした。もう一度お試しください。', 'error');
                }
            } finally {
                window.clearTimeout(timeout);
                window.clearTimeout(loadingTimer);
                loadingOverlay?.classList.remove('is-visible');
                loadingOverlay?.setAttribute('aria-hidden', 'true');

                if (!navigating) {
                    inFlight = false;
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalLabel;
                    }
                }
            }
        });
    });

    document.querySelectorAll('[data-candidate-carousel]').forEach((carousel) => {
        const shell = carousel.querySelector('[data-candidate-shell]');
        const toggle = carousel.querySelector('[data-candidate-toggle]');
        const track = carousel.querySelector('[data-candidate-track]');
        const cards = Array.from(carousel.querySelectorAll('[data-candidate-card]'));
        const dots = Array.from(carousel.querySelectorAll('[data-candidate-dot]'));
        const eventUrl = carousel.dataset.eventUrl;
        const viewed = new Set();

        const setActive = (index) => {
            dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
            const card = cards[index];
            if (!card) return;
            const taskId = Number(card.dataset.taskId || 0);
            if (!taskId || viewed.has(taskId) || !eventUrl || !csrfToken) return;
            viewed.add(taskId);
            fetch(eventUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    event_type: 'task_viewed',
                    plan_id: Number(card.dataset.planId || 0) || null,
                    task_id: taskId,
                    metadata: { source: 'navigation_candidate_carousel', candidate_index: index },
                }),
                keepalive: true,
            }).catch(() => {});
        };

        toggle?.addEventListener('click', () => {
            const opening = !shell?.classList.contains('is-open');
            shell?.classList.toggle('is-open', opening);
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            toggle.textContent = opening ? '候補を閉じる' : '別候補を見る';
            if (opening) {
                setActive(0);
                window.setTimeout(() => shell?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 30);
            }
        });

        if (track && cards.length > 0 && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                if (!visible || visible.intersectionRatio < 0.62) return;
                const index = cards.indexOf(visible.target);
                if (index >= 0) setActive(index);
            }, { root: track, threshold: [0.62, 0.8] });
            cards.forEach((card) => observer.observe(card));
        }

        dots.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                cards[index]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                setActive(index);
            });
        });
    });

    document.querySelectorAll('[data-collapsible-copy]').forEach((root) => {
        const text = root.querySelector('[data-collapsible-copy-text]');
        const toggle = root.querySelector('[data-collapsible-copy-toggle]');
        if (!text || !toggle) return;

        const refresh = () => {
            root.classList.remove('is-expanded');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = '続きを読む';
            toggle.classList.toggle('hidden', text.scrollHeight <= text.clientHeight + 2);
        };

        requestAnimationFrame(refresh);
        const parentDetails = root.closest('details');
        parentDetails?.addEventListener('toggle', () => {
            if (parentDetails.open) requestAnimationFrame(refresh);
        });

        toggle.addEventListener('click', () => {
            const expanded = !root.classList.contains('is-expanded');
            root.classList.toggle('is-expanded', expanded);
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded ? '閉じる' : '続きを読む';
        });
    });

    const releaseNotesDialog = document.querySelector('[data-release-notes-dialog]');
    const releaseNotesList = releaseNotesDialog?.querySelector('[data-release-notes-list]');
    const releaseNoteDetails = releaseNotesDialog ? [...releaseNotesDialog.querySelectorAll('[data-release-note-detail]')] : [];
    const latestReleaseVersion = releaseNotesDialog?.dataset.latestReleaseVersion || '';
    const releaseSeenKey = 'canovia.release_notes.seen';

    const updateReleaseNewIndicators = () => {
        let seenVersion = '';
        try {
            seenVersion = localStorage.getItem(releaseSeenKey) || '';
        } catch (_) {}
        const isNew = Boolean(latestReleaseVersion && seenVersion !== latestReleaseVersion);
        document.querySelectorAll('[data-release-notes-new]').forEach((indicator) => {
            indicator.classList.toggle('is-hidden', !isNew);
        });
    };

    const showReleaseNotesList = () => {
        if (!releaseNotesList) return;
        releaseNotesList.classList.remove('hidden');
        releaseNoteDetails.forEach((detail) => detail.classList.add('hidden'));
    };

    const markReleaseNotesSeen = () => {
        if (!latestReleaseVersion) return;
        try {
            localStorage.setItem(releaseSeenKey, latestReleaseVersion);
        } catch (_) {}
        updateReleaseNewIndicators();
    };

    document.querySelectorAll('[data-release-notes-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!releaseNotesDialog) return;
            showReleaseNotesList();
            markReleaseNotesSeen();
            if (typeof releaseNotesDialog.showModal === 'function') releaseNotesDialog.showModal();
            else releaseNotesDialog.setAttribute('open', '');
        });
    });

    releaseNotesDialog?.querySelectorAll('[data-release-note-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const version = button.dataset.releaseNoteOpen;
            const detail = releaseNoteDetails.find((item) => item.dataset.releaseNoteDetail === version);
            if (!detail || !releaseNotesList) return;
            releaseNotesList.classList.add('hidden');
            releaseNoteDetails.forEach((item) => item.classList.add('hidden'));
            detail.classList.remove('hidden');
            detail.scrollTop = 0;
        });
    });

    releaseNotesDialog?.querySelectorAll('[data-release-note-back]').forEach((button) => {
        button.addEventListener('click', showReleaseNotesList);
    });

    document.querySelectorAll('[data-release-notes-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!releaseNotesDialog) return;
            if (typeof releaseNotesDialog.close === 'function') releaseNotesDialog.close();
            else releaseNotesDialog.removeAttribute('open');
        });
    });

    releaseNotesDialog?.addEventListener('click', (event) => {
        if (event.target !== releaseNotesDialog) return;
        if (typeof releaseNotesDialog.close === 'function') releaseNotesDialog.close();
        else releaseNotesDialog.removeAttribute('open');
    });

    releaseNotesDialog?.addEventListener('close', showReleaseNotesList);
    updateReleaseNewIndicators();

    const feedbackDialog = document.querySelector('[data-feedback-dialog]');
    document.querySelectorAll('[data-feedback-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!feedbackDialog) return;
            if (typeof feedbackDialog.showModal === 'function') feedbackDialog.showModal();
            else feedbackDialog.setAttribute('open', '');
        });
    });
    document.querySelectorAll('[data-feedback-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!feedbackDialog) return;
            if (typeof feedbackDialog.close === 'function') feedbackDialog.close();
            else feedbackDialog.removeAttribute('open');
        });
    });
    feedbackDialog?.addEventListener('click', (event) => {
        if (event.target === feedbackDialog && typeof feedbackDialog.close === 'function') feedbackDialog.close();
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        const updateBanner = document.querySelector('[data-app-update]');
        const updateApply = document.querySelector('[data-app-update-apply]');
        const updateLater = document.querySelector('[data-app-update-later]');
        let pendingWorker = null;
        let reloadForUpdate = false;

        const showUpdate = (worker) => {
            if (!worker || !updateBanner) return;
            pendingWorker = worker;
            updateBanner.classList.remove('hidden');
        };

        navigator.serviceWorker.register('/sw.js').then((registration) => {
            if (registration.waiting && navigator.serviceWorker.controller) {
                showUpdate(registration.waiting);
            }

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (!worker) return;
                worker.addEventListener('statechange', () => {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        showUpdate(worker);
                    }
                });
            });

            updateApply?.addEventListener('click', () => {
                if (!pendingWorker) return;
                reloadForUpdate = true;
                updateApply.disabled = true;
                updateApply.textContent = '更新中…';
                pendingWorker.postMessage({ type: 'SKIP_WAITING' });
            });

            updateLater?.addEventListener('click', () => updateBanner?.classList.add('hidden'));
        }).catch(() => {});

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (reloadForUpdate) window.location.reload();
        });
    }
});

// -----------------------------------------------------------------------------
// Instant Start: persist a safe client-side snapshot and expose sync state.
// -----------------------------------------------------------------------------
const offlineDbName = 'pacekeeper-offline-v1';
const offlineStoreName = 'state';

function openOfflineDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(offlineDbName, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(offlineStoreName)) {
                request.result.createObjectStore(offlineStoreName);
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function writeOfflineState(key, value) {
    const db = await openOfflineDb();
    await new Promise((resolve, reject) => {
        const tx = db.transaction(offlineStoreName, 'readwrite');
        tx.objectStore(offlineStoreName).put(value, key);
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });
}


async function readOfflineState(key) {
    const db = await openOfflineDb();
    return await new Promise((resolve, reject) => {
        const tx = db.transaction(offlineStoreName, 'readonly');
        const request = tx.objectStore(offlineStoreName).get(key);
        request.onsuccess = () => resolve(request.result || null);
        request.onerror = () => reject(request.error);
    });
}

async function deleteOfflineState(key) {
    const db = await openOfflineDb();
    await new Promise((resolve, reject) => {
        const tx = db.transaction(offlineStoreName, 'readwrite');
        tx.objectStore(offlineStoreName).delete(key);
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });
}

function offlineSessionElapsedSeconds(session) {
    if (!session?.started_at) return 0;
    const now = Date.now();
    const end = session.ended_at ? Date.parse(session.ended_at) : now;
    const start = Date.parse(session.started_at);
    if (!Number.isFinite(start) || !Number.isFinite(end)) return 0;

    const pausedSeconds = Number(session.paused_seconds || 0);
    const livePauseSeconds = session.paused_at
        ? Math.max(0, (now - Date.parse(session.paused_at)) / 1000)
        : 0;

    return Math.max(0, Math.floor((end - start) / 1000 - pausedSeconds - livePauseSeconds));
}

async function syncOfflineWorkSession(session) {
    if (!session?.ended_at || !csrfToken) return null;

    const response = await fetch('/offline/work-sessions/sync', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            client_session_id: session.client_session_id,
            task_id: session.task_id,
            started_at: session.started_at,
            ended_at: session.ended_at,
            actual_seconds: session.actual_seconds,
            intended_minutes: session.intended_minutes ?? null,
        }),
    });

    if (!response.ok) return null;
    return await response.json().catch(() => ({}));
}

async function mountOfflineTimerCard(session) {
    const card = document.querySelector('[data-offline-timer-card]');
    if (!card || !session || session.ended_at) return false;

    const task = card.querySelector('[data-offline-timer-task]');
    const status = card.querySelector('[data-offline-timer-status]');
    const value = card.querySelector('[data-offline-timer-value]');
    const toggle = card.querySelector('[data-offline-timer-toggle]');
    const complete = card.querySelector('[data-offline-timer-complete]');
    const note = card.querySelector('[data-offline-timer-note]');
    let current = session;
    let busy = false;

    card.classList.remove('hidden');
    if (task) task.textContent = current.task_title || 'オフライン作業';

    const render = () => {
        if (value) value.textContent = formatTimer(offlineSessionElapsedSeconds(current));
        if (toggle) toggle.textContent = current.paused_at ? '再開' : '一時停止';
        const online = navigator.onLine;
        if (complete) {
            complete.disabled = !online || busy;
            complete.classList.toggle('opacity-50', !online || busy);
        }
        if (status) status.textContent = current.paused_at ? '一時停止中' : '計測中';
        if (note) {
            note.textContent = online
                ? '接続できています。終了すると作業記録へ同期します。'
                : 'オフライン中は再生・一時停止だけ利用できます。記録して終了は接続復帰後に使えます。';
        }
    };

    toggle?.addEventListener('click', async () => {
        if (busy || current.ended_at) return;
        if (current.paused_at) {
            current.paused_seconds = Number(current.paused_seconds || 0)
                + Math.max(0, (Date.now() - Date.parse(current.paused_at)) / 1000);
            current.paused_at = null;
        } else {
            current.paused_at = new Date().toISOString();
        }
        await writeOfflineState('offline_session', current).catch(() => {});
        render();
    });

    complete?.addEventListener('click', async () => {
        if (busy || !navigator.onLine || current.ended_at) return;
        busy = true;
        render();

        if (current.paused_at) {
            current.paused_seconds = Number(current.paused_seconds || 0)
                + Math.max(0, (Date.now() - Date.parse(current.paused_at)) / 1000);
            current.paused_at = null;
        }
        current.ended_at = new Date().toISOString();
        current.actual_seconds = Math.max(1, offlineSessionElapsedSeconds(current));
        await writeOfflineState('offline_session', current).catch(() => {});

        if (status) status.textContent = '作業記録を同期中…';
        const result = await syncOfflineWorkSession(current).catch(() => null);
        if (result?.work_session_id) {
            await deleteOfflineState('offline_session').catch(() => {});
            setSyncStatus('online', 'オフライン作業を同期済み', 2200);
            window.location.assign(`/work-sessions/${result.work_session_id}/review`);
            return;
        }

        busy = false;
        if (status) status.textContent = 'まだ同期できていません';
        if (note) note.textContent = '記録は端末に残っています。接続を確認して、もう一度「記録して終了」を押してください。';
        render();
    });

    window.addEventListener('online', render);
    window.addEventListener('offline', render);
    window.setInterval(() => {
        if (!current.paused_at) render();
    }, 1000);
    render();
    return true;
}

async function clearOfflineState() {
    await new Promise((resolve) => {
        const request = indexedDB.deleteDatabase(offlineDbName);
        request.onsuccess = request.onerror = request.onblocked = () => resolve();
    });
}

let syncPassiveTimer = null;
function setSyncStatus(mode, label, passiveAfterMs = null) {
    const root = document.querySelector('[data-sync-status]');
    if (!root) return;
    window.clearTimeout(syncPassiveTimer);
    root.dataset.syncMode = mode;
    root.classList.remove('is-passive');
    const target = root.querySelector('[data-sync-status-label]');
    if (target) target.textContent = label;

    if (passiveAfterMs !== null) {
        syncPassiveTimer = window.setTimeout(() => root.classList.add('is-passive'), passiveAfterMs);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const snapshotElement = document.getElementById('pacekeeper-offline-snapshot');
    if (snapshotElement && 'indexedDB' in window) {
        try {
            const snapshot = JSON.parse(snapshotElement.textContent || '{}');
            if (snapshot && typeof snapshot === 'object') {
                snapshot.last_path = window.location.pathname + window.location.search;
                snapshot.last_title = document.title;
                snapshot.client_captured_at = new Date().toISOString();
                await writeOfflineState('latest_snapshot', snapshot);
            }
        } catch (_) {}
    }

    document.querySelectorAll('[data-clear-offline-state]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (!('indexedDB' in window)) return;
            event.preventDefault();
            await clearOfflineState().catch(() => {});
            form.submit();
        });
    });

    if ('indexedDB' in window) {
        try {
            const pendingOfflineSession = await readOfflineState('offline_session');
            if (pendingOfflineSession?.ended_at && csrfToken) {
                setSyncStatus('syncing', '作業結果を同期中…');
                const result = await syncOfflineWorkSession(pendingOfflineSession).catch(() => null);
                if (result?.work_session_id) {
                    await deleteOfflineState('offline_session');
                    setSyncStatus('online', 'オフライン作業を同期済み', 2200);
                } else {
                    setSyncStatus('pending', '未同期の作業があります');
                }
            } else if (pendingOfflineSession && !pendingOfflineSession.ended_at) {
                const mounted = await mountOfflineTimerCard(pendingOfflineSession);
                if (!mounted) {
                    const banner = document.createElement('a');
                    banner.href = '/navigate?resume_offline_timer=1';
                    banner.className = 'offline-session-banner';
                    banner.textContent = 'オフラインで計測中 · 今日のタイマーへ戻る';
                    document.body.appendChild(banner);
                }
                setSyncStatus('pending', 'オフラインで計測中');
            }
        } catch (_) {
            setSyncStatus('error', '同期状態を確認できません');
        }
    }

    let wakeRequest = null;
    let hiddenAt = null;

    const warmCanoviaServer = async ({ announce = true } = {}) => {
        if (!navigator.onLine) {
            setSyncStatus('offline', 'オフライン');
            return false;
        }
        if (wakeRequest) return wakeRequest;

        if (announce) setSyncStatus('syncing', 'Canoviaを準備中…');
        wakeRequest = fetch(`/health?warm=${Date.now()}`, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'text/plain' },
        })
            .then((response) => {
                if (!response.ok) throw new Error('health-check-failed');
                setSyncStatus('online', '接続できました', 1800);
                return true;
            })
            .catch(() => {
                if (!navigator.onLine) setSyncStatus('offline', 'オフライン');
                else setSyncStatus('pending', '接続を準備しています');
                return false;
            })
            .finally(() => {
                wakeRequest = null;
            });

        return wakeRequest;
    };

    const updateNetworkState = () => {
        if (!navigator.onLine) {
            setSyncStatus('offline', 'オフライン');
            return;
        }
        setSyncStatus('online', '接続済み', 1800);
    };

    window.addEventListener('online', () => {
        updateNetworkState();
        void warmCanoviaServer({ announce: true });
    });
    window.addEventListener('offline', updateNetworkState);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            hiddenAt = Date.now();
            return;
        }

        const sleptFor = hiddenAt ? Date.now() - hiddenAt : 0;
        hiddenAt = null;
        // Do not keep the free Render service alive in the background. Only
        // pre-warm it when the user actually returns after a long absence.
        if (sleptFor >= 10 * 60 * 1000) {
            void warmCanoviaServer({ announce: true });
        }
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) void warmCanoviaServer({ announce: true });
    });

    updateNetworkState();
});

// -----------------------------------------------------------------------------
// Personal UI: per-device theme, accent, density, and Roadmap view preferences.
// -----------------------------------------------------------------------------
function applyUiPreferences() {
    const root = document.documentElement;
    const theme = localStorage.getItem('pacekeeper.ui.theme') || 'dark';
    const accent = localStorage.getItem('pacekeeper.ui.accent') || 'sky';
    const storedDensity = localStorage.getItem('pacekeeper.ui.density');
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    const density = storedDensity || (isMobile ? 'standard' : 'compact');
    const resolved = theme === 'system'
        ? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
        : theme;

    root.dataset.uiTheme = theme;
    root.dataset.themeResolved = resolved;
    root.dataset.uiAccent = accent;
    root.dataset.uiDensity = density;
    const themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = resolved === 'light' ? '#f8fafc' : '#020617';

    document.querySelectorAll('[data-ui-theme-value]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.uiThemeValue === theme);
        button.setAttribute('aria-pressed', button.dataset.uiThemeValue === theme ? 'true' : 'false');
    });
    document.querySelectorAll('[data-ui-accent-value]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.uiAccentValue === accent);
        button.setAttribute('aria-pressed', button.dataset.uiAccentValue === accent ? 'true' : 'false');
    });
    document.querySelectorAll('[data-ui-density-value]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.uiDensityValue === density);
        button.setAttribute('aria-pressed', button.dataset.uiDensityValue === density ? 'true' : 'false');
    });
}

function resolveRoadmapView(root) {
    const planId = root.dataset.roadmapPlanId || 'preview';
    const key = `pacekeeper.roadmap.v19.view.${planId}`;
    const stored = localStorage.getItem(key);
    if (stored === 'map' || stored === 'list') return stored;
    return 'map';
}

function setRoadmapView(root, view, persist = true) {
    const planId = root.dataset.roadmapPlanId || 'preview';
    root.dataset.roadmapView = view;
    root.querySelectorAll('[data-roadmap-view-button]').forEach((button) => {
        const active = button.dataset.roadmapViewButton === view;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-roadmap-view-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.roadmapViewPanel !== view;
    });
    if (persist && planId !== 'preview') {
        localStorage.setItem(`pacekeeper.roadmap.v19.view.${planId}`, view);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    applyUiPreferences();

    const futureMemoHint = document.querySelector('[data-future-memo-home-hint]');
    if (futureMemoHint) {
        let snoozeUntil = 0;
        try {
            snoozeUntil = Number(localStorage.getItem('canovia.future-memo-hint.snooze-until') || 0);
        } catch (_) {}

        if (!Number.isFinite(snoozeUntil) || Date.now() >= snoozeUntil) {
            futureMemoHint.classList.remove('hidden');
        }

        futureMemoHint.querySelector('[data-future-memo-hint-later]')?.addEventListener('click', () => {
            const sevenDays = 7 * 24 * 60 * 60 * 1000;
            try {
                localStorage.setItem('canovia.future-memo-hint.snooze-until', String(Date.now() + sevenDays));
            } catch (_) {}
            futureMemoHint.classList.add('hidden');
        });
    }

    const settingsDialog = document.querySelector('[data-ui-settings-dialog]');
    document.querySelectorAll('[data-ui-settings-open]').forEach((button) => {
        button.addEventListener('click', () => {
            applyUiPreferences();
            if (settingsDialog?.showModal) settingsDialog.showModal();
            else settingsDialog?.setAttribute('open', '');
        });
    });
    document.querySelectorAll('[data-ui-settings-close]').forEach((button) => {
        button.addEventListener('click', () => {
            if (settingsDialog?.close) settingsDialog.close();
            else settingsDialog?.removeAttribute('open');
        });
    });
    settingsDialog?.addEventListener('click', (event) => {
        if (event.target === settingsDialog && settingsDialog.close) settingsDialog.close();
    });

    document.querySelectorAll('[data-ui-theme-value]').forEach((button) => {
        button.addEventListener('click', () => {
            localStorage.setItem('pacekeeper.ui.theme', button.dataset.uiThemeValue);
            applyUiPreferences();
        });
    });
    document.querySelectorAll('[data-ui-accent-value]').forEach((button) => {
        button.addEventListener('click', () => {
            localStorage.setItem('pacekeeper.ui.accent', button.dataset.uiAccentValue);
            applyUiPreferences();
        });
    });
    document.querySelectorAll('[data-ui-density-value]').forEach((button) => {
        button.addEventListener('click', () => {
            localStorage.setItem('pacekeeper.ui.density', button.dataset.uiDensityValue);
            applyUiPreferences();
        });
    });

    const colorScheme = window.matchMedia('(prefers-color-scheme: light)');
    colorScheme.addEventListener?.('change', () => {
        if ((localStorage.getItem('pacekeeper.ui.theme') || 'dark') === 'system') applyUiPreferences();
    });

    document.querySelectorAll('[data-roadmap-view-root]').forEach((root) => {
        setRoadmapView(root, resolveRoadmapView(root), false);
        root.querySelectorAll('[data-roadmap-view-button]').forEach((button) => {
            button.addEventListener('click', () => setRoadmapView(root, button.dataset.roadmapViewButton));
        });
    });

    const roadmapDetailTimers = new WeakMap();
    const ROADMAP_DETAIL_AUTO_CLOSE_MS = 4000;

    const clearRoadmapDetailTimer = (stop) => {
        const timer = roadmapDetailTimers.get(stop);
        if (timer) {
            window.clearTimeout(timer);
            roadmapDetailTimers.delete(stop);
        }
    };

    const scheduleRoadmapDetailClose = (stop) => {
        clearRoadmapDetailTimer(stop);
        if (!stop.open) return;

        const viewRoot = stop.closest('[data-roadmap-view-root]');
        if (viewRoot?.dataset.roadmapPlanId === 'preview') return;

        const timer = window.setTimeout(() => {
            if (stop.open) stop.removeAttribute('open');
            roadmapDetailTimers.delete(stop);
        }, ROADMAP_DETAIL_AUTO_CLOSE_MS);

        roadmapDetailTimers.set(stop, timer);
    };

    document.querySelectorAll('[data-map-stop]').forEach((stop) => {
        stop.addEventListener('toggle', () => {
            if (!stop.open) {
                clearRoadmapDetailTimer(stop);
                return;
            }

            const root = stop.closest('[data-roadmap-map]');
            root?.querySelectorAll('[data-map-stop][open]').forEach((other) => {
                if (other === stop) return;
                clearRoadmapDetailTimer(other);
                other.removeAttribute('open');
            });

            scheduleRoadmapDetailClose(stop);
        });

        // Keep the detail open while the user is interacting with its controls/content.
        stop.addEventListener('pointerdown', () => clearRoadmapDetailTimer(stop));
        stop.addEventListener('focusin', () => clearRoadmapDetailTimer(stop));
        stop.addEventListener('pointerleave', () => scheduleRoadmapDetailClose(stop));
        stop.addEventListener('focusout', (event) => {
            if (!stop.contains(event.relatedTarget)) scheduleRoadmapDetailClose(stop);
        });
    });
});

// v12: Plan Design live preview. Keep the form as the source of truth; preview only reflects it.
document.addEventListener('DOMContentLoaded', () => {
    const worldLabels = {
        default: '🧭 Classic',
        study: '📚 Study',
        sweet: '🍰 Sweet',
        halloween: '🎃 Halloween',
        space: '🪐 Space',
        forest: '🌲 Forest',
    };

    document.querySelectorAll('[data-plan-visual-picker]').forEach((picker) => {
        const preview = picker.querySelector('[data-plan-visual-preview]');
        const iconInput = picker.querySelector('[data-plan-visual-icon-input]');
        const accentInput = picker.querySelector('[data-plan-visual-accent-input]');
        const worldInput = picker.querySelector('[data-plan-visual-world-input]');
        const icon = picker.querySelector('[data-plan-visual-preview-icon]');
        const world = picker.querySelector('[data-plan-visual-preview-world]');
        if (!preview) return;

        const render = () => {
            const fallbackIcon = '🧭';
            if (icon) icon.textContent = (iconInput?.value || '').trim() || fallbackIcon;
            preview.dataset.planAccent = accentInput?.value || 'sky';
            if (world) world.textContent = worldLabels[worldInput?.value] || worldLabels.default;
        };

        iconInput?.addEventListener('input', render);
        accentInput?.addEventListener('change', render);
        worldInput?.addEventListener('change', render);
        render();
    });
});

// v13: Roadmap Plan pager. Tabs provide discoverability; swipe provides speed.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-roadmap-overview]').forEach((button) => {
        button.addEventListener('click', () => {
            const root = button.closest('[data-roadmap-view-root]');
            if (!root) return;
            setRoadmapView(root, 'map');
            root.querySelectorAll('[data-map-stop][open]').forEach((stop) => stop.removeAttribute('open'));
            root.querySelector('[data-roadmap-map]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    document.querySelectorAll('[data-roadmap-plan-pager]').forEach((pager) => {
        let startX = 0;
        let startY = 0;
        let tracking = false;

        const navigate = (url, direction) => {
            if (!url) return;
            pager.classList.add(direction === 'next' ? 'is-leaving-left' : 'is-leaving-right');
            window.setTimeout(() => { window.location.assign(url); }, 110);
        };

        pager.addEventListener('touchstart', (event) => {
            const touch = event.touches?.[0];
            if (!touch) return;
            if (event.target.closest('button, a, input, select, textarea, [data-roadmap-plan-tabs]')) return;
            startX = touch.clientX;
            startY = touch.clientY;
            tracking = true;
        }, { passive: true });

        pager.addEventListener('touchend', (event) => {
            if (!tracking) return;
            tracking = false;
            const touch = event.changedTouches?.[0];
            if (!touch) return;
            const dx = touch.clientX - startX;
            const dy = touch.clientY - startY;
            if (Math.abs(dx) < 58 || Math.abs(dx) < Math.abs(dy) * 1.25) return;
            if (dx < 0) navigate(pager.dataset.nextUrl, 'next');
            else navigate(pager.dataset.prevUrl, 'prev');
        }, { passive: true });

        pager.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowRight' && pager.dataset.nextUrl) {
                event.preventDefault();
                navigate(pager.dataset.nextUrl, 'next');
            }
            if (event.key === 'ArrowLeft' && pager.dataset.prevUrl) {
                event.preventDefault();
                navigate(pager.dataset.prevUrl, 'prev');
            }
        });
    });

    const activePlanTab = document.querySelector('[data-roadmap-plan-tabs] .pk-v19-plan-card.is-active, [data-roadmap-plan-tabs] .roadmap-plan-tab.is-active');
    activePlanTab?.scrollIntoView({ behavior: 'auto', block: 'nearest', inline: 'center' });
});

// v13: optional five-star overall score inside the existing feedback flow.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-feedback-dialog]').forEach((dialog) => {
        const input = dialog.querySelector('[data-feedback-rating-input]');
        const label = dialog.querySelector('[data-feedback-rating-label]');
        const stars = [...dialog.querySelectorAll('[data-feedback-rating-value]')];
        if (!input || stars.length === 0) return;

        const render = (rating) => {
            const value = Number(rating || 0);
            stars.forEach((star) => {
                const selected = Number(star.dataset.feedbackRatingValue) <= value;
                star.classList.toggle('is-selected', selected);
                star.setAttribute('aria-pressed', Number(star.dataset.feedbackRatingValue) === value ? 'true' : 'false');
            });
            if (label) label.textContent = value > 0 ? `${value} / 5` : '未評価';
        };

        stars.forEach((star) => {
            star.addEventListener('click', () => {
                input.value = star.dataset.feedbackRatingValue || '';
                render(input.value);
            });
        });
        render(input.value);
    });
});

// -----------------------------------------------------------------------------
// v15 Guided first-run onboarding + install guidance.
// The tutorial is event-driven and persists across page navigations.
// Existing users are not auto-started simply because a new onboarding version
// ships: automatic start only begins from an empty Home dashboard.
// -----------------------------------------------------------------------------
let pacekeeperDeferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    pacekeeperDeferredInstallPrompt = event;
    window.dispatchEvent(new CustomEvent('pacekeeper:install-ready'));
});

window.addEventListener('appinstalled', () => {
    localStorage.setItem('pacekeeper.install.state', 'installed');
    localStorage.removeItem('pacekeeper.install.offer-pending');
});

function pacekeeperIsStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function pacekeeperVisibleTarget(selector) {
    return [...document.querySelectorAll(selector)].find((element) => {
        const style = window.getComputedStyle(element);
        const rect = element.getBoundingClientRect();
        return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    }) || null;
}

function pacekeeperOpenDialog(dialog) {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
    else dialog.setAttribute('open', '');
}

function pacekeeperCloseDialog(dialog) {
    if (!dialog) return;
    if (typeof dialog.close === 'function' && dialog.open) dialog.close();
    else dialog.removeAttribute('open');
}

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const root = document.querySelector('[data-onboarding-root]');
    const bubble = root?.querySelector('[data-onboarding-bubble]');
    const focusRing = root?.querySelector('[data-onboarding-focus-ring]');
    const blockers = root ? Object.fromEntries(
        [...root.querySelectorAll('[data-onboarding-blocker]')].map((item) => [item.dataset.onboardingBlocker, item])
    ) : {};
    const title = root?.querySelector('[data-onboarding-title]');
    const copy = root?.querySelector('[data-onboarding-copy]');
    const progress = root?.querySelector('[data-onboarding-progress]');
    const actions = root?.querySelector('[data-onboarding-actions]');
    const nextButton = root?.querySelector('[data-onboarding-next]');
    const skipButton = root?.querySelector('[data-onboarding-skip]');
    const introDialog = document.querySelector('[data-onboarding-intro]');
    const introStart = introDialog?.querySelector('[data-onboarding-intro-start]');
    const introSkips = introDialog ? [...introDialog.querySelectorAll('[data-onboarding-intro-skip]')] : [];

    const version = Number(body?.dataset.onboardingVersion || 1);
    const stateKey = `pacekeeper.onboarding.v${version}`;
    const stageKey = `pacekeeper.onboarding.stage.v${version}`;
    const replayKey = `pacekeeper.onboarding.replay.v${version}`;
    const totalSteps = 7;
    let activeTarget = null;
    let cleanupTargetListeners = () => {};
    let currentStage = localStorage.getItem(stageKey);
    let replayMode = localStorage.getItem(replayKey) === '1';

    const steps = {
        'home-create': {
            selector: '[data-onboarding-target="create-plan"]',
            number: 1,
            title: '最初の計画を作ります',
            copy: 'まずは、進めたいことを1つ登録します。細かく決め切らなくて大丈夫です。',
            event: 'click',
            next: 'plan-form',
        },
        'plan-form': {
            selector: '[data-onboarding-target="plan-form"]',
            number: 2,
            title: '最初はざっくりでOK',
            copy: 'まずはタイトルだけで作成できます。期限は決まっていれば入力し、まだなら空欄のままで大丈夫です。細かいタスクは次にAIと整えます。',
            next: 'ai-copy',
            largeTarget: true,
        },
        'ai-copy': {
            selector: '[data-onboarding-target="ai-copy"]',
            number: 3,
            title: 'いつものAIを使えます',
            copy: 'このボタンで相談用の文章をコピーします。ChatGPT、Gemini、Claudeなど、普段使っているAIにそのまま貼り付けてください。',
            event: 'click',
            next: 'ai-import',
        },
        'ai-import': {
            selector: '[data-onboarding-target="ai-import"]',
            number: 4,
            title: '相談結果をCanoviaへ戻します',
            copy: 'AIが最後に出したJSONをここへ貼り付けて登録すると、タスクと進む順番がロードマップになります。',
            next: 'roadmap-nav',
            largeTarget: true,
        },
        'roadmap-nav': {
            selector: '[data-onboarding-target="roadmap-nav"]',
            number: 5,
            title: '先を見るときはロードマップ',
            copy: 'いまいる場所と、この先のタスクをここで確認できます。押して見てみましょう。',
            event: 'click',
            next: 'today-nav',
        },
        'today-nav': {
            selector: '[data-onboarding-target="today-nav"]',
            number: 6,
            title: '迷ったら「今日」へ',
            copy: 'ロードマップを確認できたら、作業を決める場所はここです。Canoviaが今の候補を絞ります。',
            event: 'click',
            next: 'today-start',
        },
        'today-start': {
            selector: '[data-onboarding-target="today-start"]',
            number: 7,
            title: 'あとは始めるだけ',
            copy: 'このまま開始するとタイマーへ移動します。作業した時間はあとで実績として残せます。',
            event: 'click',
            next: 'timer',
        },
        'timer': {
            selector: '[data-onboarding-target="work-timer"]',
            number: null,
            title: '準備完了です',
            copy: 'これがCanoviaの基本の流れです。作業が終わったら「記録して終了」で実績を残してください。',
            actionLabel: '使ってみる',
            next: null,
        },
        'replay-today': {
            selector: '[data-onboarding-target="today-nav"]',
            number: 1,
            title: '「今日」',
            copy: '今やることを決めて、そのまま作業を始める場所です。',
            actionLabel: '次へ',
            next: 'replay-roadmap',
        },
        'replay-roadmap': {
            selector: '[data-onboarding-target="roadmap-nav"]',
            number: 2,
            title: '「ロードマップ」',
            copy: '現在地とこの先を確認する場所です。MapとListはいつでも切り替えられます。',
            actionLabel: '完了',
            next: null,
        },
    };

    const apiPost = (url) => {
        if (!url || !csrfToken) return Promise.resolve();
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            keepalive: true,
        }).catch(() => {});
    };

    const setStage = (stage) => {
        currentStage = stage;
        if (stage) localStorage.setItem(stageKey, stage);
        else localStorage.removeItem(stageKey);
    };

    const hideOnboarding = () => {
        cleanupTargetListeners();
        cleanupTargetListeners = () => {};
        activeTarget = null;
        root?.classList.add('hidden');
        pacekeeperCloseDialog(introDialog);
        body?.classList.remove('onboarding-active');
    };

    const showIntro = () => {
        if (!introDialog) {
            setStage('home-create');
            showStep('home-create');
            return;
        }
        root?.classList.add('hidden');
        body?.classList.remove('onboarding-active');
        pacekeeperOpenDialog(introDialog);
    };

    const completeOnboarding = (isReplay = false) => {
        hideOnboarding();
        setStage(null);
        if (isReplay || replayMode) {
            replayMode = false;
            localStorage.removeItem(replayKey);
            return;
        }
        localStorage.setItem(stateKey, 'completed');
        localStorage.setItem('pacekeeper.install.offer-pending', '1');
        apiPost(root?.dataset.completeUrl);
        window.dispatchEvent(new CustomEvent('pacekeeper:onboarding-complete'));
    };

    const skipOnboarding = () => {
        hideOnboarding();
        setStage(null);
        if (replayMode) {
            replayMode = false;
            localStorage.removeItem(replayKey);
            return;
        }
        localStorage.setItem(stateKey, 'skipped');
        apiPost(root?.dataset.skipUrl);
    };

    const placeOverlay = () => {
        if (!activeTarget || !root || root.classList.contains('hidden')) return;
        const rect = activeTarget.getBoundingClientRect();
        const pad = 8;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const x = Math.max(8, rect.left - pad);
        const y = Math.max(8, rect.top - pad);
        const right = Math.min(viewportWidth - 8, rect.right + pad);
        const bottom = Math.min(viewportHeight - 8, rect.bottom + pad);
        const width = Math.max(0, right - x);
        const height = Math.max(0, bottom - y);

        const setRect = (element, left, top, w, h) => {
            if (!element) return;
            element.style.left = `${Math.max(0, left)}px`;
            element.style.top = `${Math.max(0, top)}px`;
            element.style.width = `${Math.max(0, w)}px`;
            element.style.height = `${Math.max(0, h)}px`;
        };

        setRect(blockers.top, 0, 0, viewportWidth, y);
        setRect(blockers.left, 0, y, x, height);
        setRect(blockers.right, right, y, viewportWidth - right, height);
        setRect(blockers.bottom, 0, bottom, viewportWidth, viewportHeight - bottom);
        setRect(focusRing, x, y, width, height);

        if (!bubble) return;
        const bubbleWidth = Math.min(360, viewportWidth - 24);
        bubble.style.width = `${bubbleWidth}px`;
        const bubbleHeight = bubble.offsetHeight || 180;
        const below = bottom + 12;
        const above = y - bubbleHeight - 12;
        let top = below + bubbleHeight <= viewportHeight - 12 ? below : above;
        if (top < 12) top = Math.max(12, viewportHeight - bubbleHeight - 12);
        const targetCenter = x + width / 2;
        const left = Math.min(viewportWidth - bubbleWidth - 12, Math.max(12, targetCenter - bubbleWidth / 2));
        bubble.style.left = `${left}px`;
        bubble.style.top = `${top}px`;
    };

    const showStep = (stage) => {
        if (!root || !stage) return;
        const step = steps[stage];
        if (!step) return;
        const target = pacekeeperVisibleTarget(step.selector);
        if (!target) return;

        cleanupTargetListeners();
        cleanupTargetListeners = () => {};
        activeTarget = target;
        root.classList.remove('hidden');
        body?.classList.add('onboarding-active');
        if (title) title.textContent = step.title;
        if (copy) copy.textContent = step.copy;
        if (progress) {
            if (replayMode) progress.textContent = '基本操作';
            else progress.textContent = step.number ? `${step.number} / ${totalSteps}` : 'できました';
        }
        if (actions && nextButton) {
            const showAction = Boolean(step.actionLabel);
            actions.classList.toggle('hidden', !showAction);
            nextButton.textContent = step.actionLabel || '次へ';
            nextButton.onclick = showAction ? () => {
                if (step.next) {
                    setStage(step.next);
                    showStep(step.next);
                } else {
                    completeOnboarding(replayMode);
                }
            } : null;
        }

        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        window.setTimeout(placeOverlay, 180);

        if (step.event === 'click') {
            const handler = () => {
                if (!step.next) return;
                setStage(step.next);
                window.setTimeout(() => showStep(step.next), 80);
            };
            target.addEventListener('click', handler, { once: true });
            cleanupTargetListeners = () => target.removeEventListener('click', handler);
        } else if (step.event === 'submit') {
            const form = target.matches('form') ? target : target.querySelector('form');
            if (form) {
                const handler = () => {
                    if (step.next) setStage(step.next);
                };
                form.addEventListener('submit', handler, { once: true });
                cleanupTargetListeners = () => form.removeEventListener('submit', handler);
            }
        }
    };

    introStart?.addEventListener('click', () => {
        pacekeeperCloseDialog(introDialog);
        setStage('home-create');
        window.setTimeout(() => showStep('home-create'), 80);
    });
    introSkips.forEach((button) => button.addEventListener('click', skipOnboarding));
    skipButton?.addEventListener('click', skipOnboarding);
    window.addEventListener('resize', placeOverlay);
    window.addEventListener('scroll', placeOverlay, { passive: true });

    document.querySelectorAll('[data-onboarding-restart]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('[data-ui-settings-dialog]')?.close?.();
            replayMode = true;
            localStorage.setItem(replayKey, '1');
            setStage('replay-today');
            showStep('replay-today');
        });
    });

    const routeName = body?.dataset.routeName || '';
    if (currentStage === 'home-create' && routeName === 'plans.create') setStage('plan-form');
    if (currentStage === 'plan-form' && routeName === 'plans.ai_task_assistant.show') setStage('ai-copy');
    if (currentStage === 'ai-import' && routeName === 'plans.show') setStage('roadmap-nav');
    if (currentStage === 'roadmap-nav' && routeName === 'roadmap.index') setStage('today-nav');
    if (currentStage === 'today-nav' && routeName === 'navigation.index') setStage('today-start');
    if (currentStage === 'today-start' && routeName === 'work_sessions.active') setStage('timer');
    currentStage = localStorage.getItem(stageKey);

    const localState = localStorage.getItem(stateKey);
    const newUserDashboard = document.querySelector('[data-onboarding-new-user="1"]');
    if (replayMode && !currentStage) currentStage = 'replay-today';
    if (!currentStage && body?.dataset.onboardingAuto === '1' && !localState && newUserDashboard) {
        setStage('intro');
    }
    if (currentStage) {
        window.setTimeout(() => {
            if (currentStage === 'intro') showIntro();
            else showStep(currentStage);
        }, 260);
    }

    // PWA / home-screen install guidance. Automatic display is queued only
    // after the guided flow is complete, and never interrupts focus/timer mode.
    const installDialog = document.querySelector('[data-install-guide]');
    const installAction = installDialog?.querySelector('[data-install-guide-action]');
    const installLater = installDialog?.querySelector('[data-install-guide-later]');
    const installCopy = installDialog?.querySelector('[data-install-guide-copy]');
    const iosHelp = installDialog?.querySelector('[data-install-ios-help]');
    const browserHelp = installDialog?.querySelector('[data-install-browser-help]');
    const browserHelpTitle = installDialog?.querySelector('[data-install-browser-title]');
    const browserHelpCopy = installDialog?.querySelector('[data-install-browser-copy]');
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /Android/i.test(navigator.userAgent);

    const configureInstallGuide = () => {
        if (!installDialog || !installAction) return;
        const hasNativePrompt = Boolean(pacekeeperDeferredInstallPrompt);
        iosHelp?.classList.toggle('hidden', !isIos || hasNativePrompt);
        browserHelp?.classList.toggle('hidden', isIos || hasNativePrompt);
        if (pacekeeperDeferredInstallPrompt) {
            installAction.textContent = 'ホーム画面に追加';
            installAction.disabled = false;
            if (installCopy) installCopy.textContent = 'ホーム画面から、普通のアプリのようにすぐ開けます。';
            return;
        }
        if (isIos) {
            installAction.textContent = '手順を確認しました';
            installAction.disabled = false;
            if (installCopy) installCopy.textContent = 'データを引き継ぐ専用画面を開いてから、Safariの共有メニューで追加します。';
            return;
        }
        installAction.textContent = '手順を確認しました';
        installAction.disabled = false;
        if (installCopy) installCopy.textContent = 'ブラウザのメニューからホーム画面へ追加できます。';
        if (browserHelpTitle) browserHelpTitle.textContent = isAndroid ? 'Androidの場合' : 'ブラウザから追加';
        if (browserHelpCopy) {
            browserHelpCopy.textContent = isAndroid
                ? 'ChromeやEdgeの右上メニューから「アプリをインストール」または「ホーム画面に追加」を選んでください。'
                : 'ブラウザのメニューから「アプリをインストール」または「ホーム画面に追加」を選んでください。';
        }
    };

    const showInstallGuide = (force = false) => {
        if (!installDialog || pacekeeperIsStandalone()) return;
        if (!force && body?.dataset.focusMode === '1') return;
        if (!force && localStorage.getItem('pacekeeper.install.offer-pending') !== '1') return;
        if (!force && localStorage.getItem('pacekeeper.install.state') === 'dismissed') return;
        configureInstallGuide();
        pacekeeperOpenDialog(installDialog);
    };

    document.querySelectorAll('[data-install-guide-open]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('[data-ui-settings-dialog]')?.close?.();
            showInstallGuide(true);
        });
    });

    document.querySelectorAll('[data-install-guide-close]').forEach((button) => {
        button.addEventListener('click', () => pacekeeperCloseDialog(installDialog));
    });

    installLater?.addEventListener('click', () => {
        localStorage.setItem('pacekeeper.install.state', 'dismissed');
        localStorage.removeItem('pacekeeper.install.offer-pending');
        pacekeeperCloseDialog(installDialog);
    });

    installAction?.addEventListener('click', async () => {
        if (pacekeeperDeferredInstallPrompt) {
            const prompt = pacekeeperDeferredInstallPrompt;
            pacekeeperDeferredInstallPrompt = null;
            await prompt.prompt();
            const choice = await prompt.userChoice.catch(() => null);
            if (choice?.outcome === 'accepted') {
                localStorage.setItem('pacekeeper.install.state', 'installed');
                localStorage.removeItem('pacekeeper.install.offer-pending');
                pacekeeperCloseDialog(installDialog);
            } else {
                configureInstallGuide();
            }
            return;
        }
        // iOS Home Screen apps can have a separate cookie jar from Safari.
        // Move to the protected install page first so the saved launch URL can
        // carry a one-time account/Guest handoff into the standalone context.
        if (isIos && body?.dataset.pwaInstallUrl) {
            window.location.href = body.dataset.pwaInstallUrl;
            return;
        }

        // Browsers without beforeinstallprompt still use their menu. The
        // dynamic manifest already contains a short-lived protected start URL.
        localStorage.removeItem('pacekeeper.install.offer-pending');
        pacekeeperCloseDialog(installDialog);
    });

    window.addEventListener('pacekeeper:install-ready', configureInstallGuide);
    window.addEventListener('pacekeeper:onboarding-complete', () => {
        if (body?.dataset.focusMode !== '1') window.setTimeout(() => showInstallGuide(), 500);
    });

    // If onboarding completed on the timer screen, the offer waits until the
    // next normal page rather than interrupting the user's work.
    if (body?.dataset.focusMode !== '1') window.setTimeout(() => showInstallGuide(), 900);
});
