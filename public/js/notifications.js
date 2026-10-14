document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('notificationCenter');
    if (!root) return;

    const bellBtn = document.getElementById('notificationBellBtn');
    const panel = document.getElementById('notificationPanel');
    const badge = document.getElementById('notificationBadge');
    const list = document.getElementById('notificationList');
    const markAllBtn = document.getElementById('notificationMarkAllBtn');

    const ENDPOINT = '../src/actions/handle_notifications.php';

    // Read the current language live (from <html lang="">, falling back to
    // localStorage) instead of caching it once at page load. Switching
    // language is a client-side toggle (no page reload), so anything that
    // cached `lang` in a const at DOMContentLoaded stayed in whatever
    // language the page happened to be in when it first loaded.
    function getLang() {
        return document.documentElement.lang || localStorage.getItem('language') || 'en';
    }

    function noNotificationsText() {
        return getLang() === 'ar' ? 'لا توجد إشعارات بعد.' : 'No notifications yet.';
    }

    // Notification titles are a small fixed set of server-generated strings
    // (e.g. "Follow-up Due", "New Lead"). Translate them via the shared
    // dictionary, falling back to the raw title for anything unmapped.
    function translateNotificationTitle(title) {
        if (!title) return title;
        const lang = getLang();
        const key = String(title).replace(/[ -]/g, '_');
        if (typeof translations !== 'undefined' && translations[key] && translations[key][lang]) {
            return translations[key][lang];
        }
        return title;
    }

    // Message bodies are free-form sentences generated server-side with
    // interpolated client data (name, dates, statuses) baked in — there's
    // no key to look up. Instead, match the small set of fixed sentence
    // shapes per notification `type` with a regex, pull out the dynamic
    // parts, and rebuild the sentence in Arabic. Anything that doesn't
    // match a known shape (or isn't Arabic) is left exactly as stored.
    const MESSAGE_PATTERNS = {
        new_lead: [
            { re: /^New lead "(.+)" was added by (.+)\.$/, ar: (m) => `تمت إضافة عميل جديد "${m[1]}" بواسطة ${m[2]}.` }
        ],
        followup_due: [
            { re: /^Follow-up with "(.+)" is due today\.$/, ar: (m) => `موعد المتابعة مع "${m[1]}" اليوم.` }
        ],
        followup_alert: [
            { re: /^Follow-up with "(.+)" is due today\.$/, ar: (m) => `موعد المتابعة مع "${m[1]}" اليوم.` }
        ],
        missed_followup: [
            { re: /^Follow-up with "(.+)" was missed \(was due (.+)\)\.$/, ar: (m) => `فاتت متابعة "${m[1]}" (كانت مستحقة في ${m[2]}).` }
        ],
        status_changed: [
            { re: /^"(.+)"'s status changed from "(.+)" to "(.+)"\.$/, ar: (m) => `تغيرت حالة "${m[1]}" من "${m[2]}" إلى "${m[3]}".` },
            { re: /^"(.+)" moved from "(.+)" to "(.+)" in the Sales Pipeline\.$/, ar: (m) => `تم نقل "${m[1]}" من "${m[2]}" إلى "${m[3]}" في مسار المبيعات.` }
        ],
        sale_completed: [
            { re: /^Sale completed for "(.+)" \(Won\)\.$/, ar: (m) => `تم إتمام عملية بيع لـ "${m[1]}" (تم الفوز بالصفقة).` },
            { re: /^Sale completed for "(.+)"\.$/, ar: (m) => `تم إتمام عملية بيع لـ "${m[1]}".` }
        ]
    };

    function translateNotificationMessage(type, message) {
        if (!message) return message;
        if (getLang() !== 'ar') return message;
        const patterns = MESSAGE_PATTERNS[type];
        if (!patterns) return message;
        for (let i = 0; i < patterns.length; i++) {
            const match = message.match(patterns[i].re);
            if (match) return patterns[i].ar(match);
        }
        return message;
    }

    function timeAgo(dateStr) {
        const lang = getLang();
        const then = new Date(dateStr.replace(' ', 'T'));
        const diffMs = Date.now() - then.getTime();
        const diffMin = Math.floor(diffMs / 60000);
        if (isNaN(diffMin)) return '';
        if (diffMin < 1) return lang === 'ar' ? 'الآن' : 'just now';
        if (diffMin < 60) return diffMin + (lang === 'ar' ? ' دقيقة' : 'm ago');
        const diffHr = Math.floor(diffMin / 60);
        if (diffHr < 24) return diffHr + (lang === 'ar' ? ' ساعة' : 'h ago');
        const diffDay = Math.floor(diffHr / 24);
        return diffDay + (lang === 'ar' ? ' يوم' : 'd ago');
    }

    function updateBadge(count) {
        if (count > 0) {
            badge.style.display = '';
            badge.textContent = count > 99 ? '99+' : count;
        } else {
            badge.style.display = 'none';
            badge.textContent = '0';
        }
    }

    let lastItems = null;

    function renderNotifications(items) {
        lastItems = items;
        list.innerHTML = '';
        if (!items || items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'notification-empty';
            empty.textContent = noNotificationsText();
            list.appendChild(empty);
            return;
        }

        items.forEach(function (n) {
            const item = document.createElement('div');
            item.className = 'notification-item type-' + n.type + (n.is_read == 0 ? ' unread' : '');
            item.dataset.id = n.id;
            if (n.client_id) item.dataset.clientId = n.client_id;

            const icon = document.createElement('div');
            icon.className = 'notification-item-icon';
            icon.innerHTML = '<i class="fa-solid ' + (n.icon || 'fa-bell') + '"></i>';

            const body = document.createElement('div');
            body.className = 'notification-item-body';
            body.innerHTML =
                '<div class="notification-item-title"></div>' +
                '<div class="notification-item-message"></div>' +
                '<div class="notification-item-time"></div>';
            body.querySelector('.notification-item-title').textContent = translateNotificationTitle(n.title);
            body.querySelector('.notification-item-message').textContent = translateNotificationMessage(n.type, n.message);
            body.querySelector('.notification-item-time').textContent = timeAgo(n.created_at);

            item.appendChild(icon);
            item.appendChild(body);

            if (n.is_read == 0) {
                const dot = document.createElement('div');
                dot.className = 'notification-item-dot';
                item.appendChild(dot);
            }

            item.addEventListener('click', function () {
                if (n.is_read == 0) {
                    markAsRead(n.id);
                }
                if (n.client_id) {
                    window.location.href = 'client_timeline.php?id=' + n.client_id;
                }
            });

            list.appendChild(item);
        });
    }

    function loadNotifications() {
        fetch(ENDPOINT + '?action=list', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) return;
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
            })
            .catch(function () {});
    }

    function refreshUnreadCount() {
        fetch(ENDPOINT + '?action=unread_count', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) return;
                updateBadge(data.unread_count);
            })
            .catch(function () {});
    }

    function markAsRead(id) {
        const body = new URLSearchParams();
        body.set('action', 'mark_read');
        body.set('id', id);
        fetch(ENDPOINT, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) return;
                updateBadge(data.unread_count);
                const el = list.querySelector('.notification-item[data-id="' + id + '"]');
                if (el) {
                    el.classList.remove('unread');
                    const dot = el.querySelector('.notification-item-dot');
                    if (dot) dot.remove();
                }
            })
            .catch(function () {});
    }

    markAllBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const body = new URLSearchParams();
        body.set('action', 'mark_all_read');
        fetch(ENDPOINT, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) return;
                updateBadge(0);
                list.querySelectorAll('.notification-item').forEach(function (el) {
                    el.classList.remove('unread');
                    const dot = el.querySelector('.notification-item-dot');
                    if (dot) dot.remove();
                });
            })
            .catch(function () {});
    });

    bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = panel.classList.toggle('show');
        bellBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (isOpen) {
            loadNotifications();
        }
    });

    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) {
            panel.classList.remove('show');
            bellBtn.setAttribute('aria-expanded', 'false');
        }
    });

    refreshUnreadCount();
    setInterval(refreshUnreadCount, 60000);

    // Re-render whatever is already in the panel (titles + messages) the
    // instant the language toggle is used, instead of waiting for the
    // next fetch (which only happens when the bell is re-opened).
    document.addEventListener('languageChanged', function () {
        if (lastItems !== null) {
            renderNotifications(lastItems);
        }
    });

    // ------------------------------------------------------------------
    // Smart Alert & Reminder System
    // Polls for clients whose Follow-up Date is today and pops up an
    // actionable alert card for each one, with sound.
    // ------------------------------------------------------------------
    const alertStack = document.getElementById('smartAlertStack');
    if (alertStack) {
        function openClientText() { return getLang() === 'ar' ? 'فتح العميل' : 'Open Client'; }
        function markDoneText() { return getLang() === 'ar' ? 'تم' : 'Mark as Done'; }
        function snoozeText() { return getLang() === 'ar' ? 'تأجيل 10 دقائق' : 'Snooze 10m'; }
        function dismissText() { return getLang() === 'ar' ? 'إغلاق' : 'Dismiss'; }
        function dueTodayText() { return getLang() === 'ar' ? 'موعد متابعة اليوم' : 'Follow-up Due Today'; }

        const shownClientIds = new Set();
        let audioCtx = null;

        // --------------------------------------------------------------
        // Alert sound: plays one clear repeating chime while any Smart
        // Alert card is on screen. Overlap-safe — a single shared loop
        // covers all currently-visible cards, so triggering several
        // alerts at once (or one after another) never stacks sounds.
        // Stops automatically after 30s, or the instant the user
        // interacts with a card (Open / Done / Snooze / Dismiss), or
        // when no cards are left.
        // --------------------------------------------------------------
        const SOUND_MAX_MS = 30000;
        const SOUND_BEEP_INTERVAL_MS = 900;
        let soundTimerId = null;
        let soundStopTimeoutId = null;

        function beepOnce() {
            try {
                audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                const now = audioCtx.currentTime;
                [0, 0.18].forEach(function (offset) {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, now + offset);
                    gain.gain.setValueAtTime(0.0001, now + offset);
                    gain.gain.linearRampToValueAtTime(0.3, now + offset + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.16);
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.start(now + offset);
                    osc.stop(now + offset + 0.18);
                });
            } catch (e) { /* audio not available; alert still shows visually */ }
        }

        function stopAlertSound() {
            if (soundTimerId !== null) {
                clearInterval(soundTimerId);
                soundTimerId = null;
            }
            if (soundStopTimeoutId !== null) {
                clearTimeout(soundStopTimeoutId);
                soundStopTimeoutId = null;
            }
        }

        // Starts the shared alert loop only if it isn't already running,
        // so multiple simultaneous alerts still produce exactly one sound.
        function ensureAlertSoundPlaying() {
            if (soundTimerId !== null) return; // already looping — no overlap
            beepOnce();
            soundTimerId = setInterval(beepOnce, SOUND_BEEP_INTERVAL_MS);
            soundStopTimeoutId = setTimeout(stopAlertSound, SOUND_MAX_MS);
        }

        function removeCard(card) {
            card.classList.add('smart-alert-card-leaving');
            setTimeout(function () {
                card.remove();
                // If that was the last visible alert card, stop the sound.
                if (!alertStack.querySelector('.smart-alert-card')) {
                    stopAlertSound();
                }
            }, 200);
        }

        function postAction(action, clientId, extra) {
            const body = new URLSearchParams();
            body.set('action', action);
            body.set('client_id', clientId);
            if (extra) {
                Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
            }
            return fetch(ENDPOINT, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (res) { return res.json(); });
        }

        function buildAlertCard(item) {
            const card = document.createElement('div');
            card.className = 'smart-alert-card';
            card.dataset.clientId = item.client_id;

            const header = document.createElement('div');
            header.className = 'smart-alert-card-header';
            header.innerHTML =
                '<i class="fa-solid fa-bell smart-alert-ringing"></i><span>' + dueTodayText() + '</span>';

            const body = document.createElement('div');
            body.className = 'smart-alert-card-body';

            const nameEl = document.createElement('div');
            nameEl.className = 'smart-alert-name';
            nameEl.textContent = item.client_name || '';

            const phoneEl = document.createElement('div');
            phoneEl.className = 'smart-alert-row';
            phoneEl.innerHTML = '<i class="fa-solid fa-phone"></i> ';
            phoneEl.appendChild(document.createTextNode(item.phone_number || ''));

            const timeEl = document.createElement('div');
            timeEl.className = 'smart-alert-row';
            timeEl.innerHTML = '<i class="fa-regular fa-clock"></i> ';
            timeEl.appendChild(document.createTextNode(item.follow_up_time || '--:--'));

            body.appendChild(nameEl);
            body.appendChild(phoneEl);
            body.appendChild(timeEl);

            if (item.follow_up_note) {
                const noteEl = document.createElement('div');
                noteEl.className = 'smart-alert-note';
                noteEl.innerHTML = '<i class="fa-solid fa-note-sticky"></i> ';
                noteEl.appendChild(document.createTextNode(item.follow_up_note));
                body.appendChild(noteEl);
            }

            const actions = document.createElement('div');
            actions.className = 'smart-alert-actions';

            const openBtn = document.createElement('a');
            openBtn.className = 'smart-alert-btn smart-alert-btn-open';
            openBtn.href = 'client_timeline.php?id=' + encodeURIComponent(item.client_id);
            openBtn.textContent = openClientText();
            // Opening the client counts as interacting with the alert —
            // stop the sound right away instead of leaving it looping.
            openBtn.addEventListener('click', function () {
                stopAlertSound();
            });

            const doneBtn = document.createElement('button');
            doneBtn.type = 'button';
            doneBtn.className = 'smart-alert-btn smart-alert-btn-done';
            doneBtn.textContent = markDoneText();
            doneBtn.addEventListener('click', function () {
                stopAlertSound();
                postAction('alert_mark_done', item.client_id).then(function (data) {
                    if (data && data.success) {
                        updateBadge(data.unread_count);
                        removeCard(card);
                    }
                });
            });

            const snoozeBtn = document.createElement('button');
            snoozeBtn.type = 'button';
            snoozeBtn.className = 'smart-alert-btn smart-alert-btn-snooze';
            snoozeBtn.textContent = snoozeText();
            snoozeBtn.addEventListener('click', function () {
                stopAlertSound();
                postAction('alert_snooze', item.client_id, { minutes: 10 }).then(function (data) {
                    if (data && data.success) {
                        shownClientIds.delete(item.client_id);
                        removeCard(card);
                    }
                });
            });

            const dismissBtn = document.createElement('button');
            dismissBtn.type = 'button';
            dismissBtn.className = 'smart-alert-btn smart-alert-btn-dismiss';
            dismissBtn.textContent = dismissText();
            dismissBtn.addEventListener('click', function () {
                stopAlertSound();
                postAction('alert_dismiss', item.client_id).then(function () {
                    removeCard(card);
                });
            });

            actions.appendChild(openBtn);
            actions.appendChild(doneBtn);
            actions.appendChild(snoozeBtn);
            actions.appendChild(dismissBtn);

            card.appendChild(header);
            card.appendChild(body);
            card.appendChild(actions);

            return card;
        }

        function checkTodaysFollowUps() {
            fetch(ENDPOINT + '?action=today_popup', { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data.success || !Array.isArray(data.due)) return;
                    let newOnes = 0;
                    data.due.forEach(function (item) {
                        if (shownClientIds.has(item.client_id)) return;
                        shownClientIds.add(item.client_id);
                        newOnes++;
                        alertStack.appendChild(buildAlertCard(item));
                    });
                    if (newOnes > 0) {
                        // One shared sound covers every due alert shown
                        // right now — this call is a no-op if it's
                        // already looping, so overlapping alerts never
                        // overlap sounds.
                        ensureAlertSoundPlaying();
                    }
                })
                .catch(function () {});
        }

        checkTodaysFollowUps();
        setInterval(checkTodaysFollowUps, 60000);
    }
});
