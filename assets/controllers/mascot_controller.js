import { Controller } from "@hotwired/stimulus";

/*
 * A friendly star mascot in the bottom-right corner. It greets the user, then
 * periodically pops a speech bubble cheering them on to start or finish an
 * project. Rarely it invites the user to a game of catch: click it and it
 * darts away from the pointer; catch it and it turns the tables and chases the
 * pointer until it tags it back. Messages arrive already translated.
 */
export default class extends Controller {
    static targets = [
        "bubble",
        "text",
        "cta",
        "play",
        "finish",
        "finishContact",
        "tourNext",
        "tourSkip",
    ];

    static values = {
        messages: { type: Array, default: [] },
        interval: { type: Number, default: 50000 },
        playChance: { type: Number, default: 0.01 },
        invite: String,
        caught: String,
        gotcha: String,
        giveup: String,
        escaped: String,
        finishTexts: { type: Array, default: [] },
        finishContactTexts: { type: Array, default: [] },
        enabled: { type: Boolean, default: true },
        farewell: String,
        welcome: String,
        intro: String,
        tour: { type: Array, default: [] },
        tourPropose: String,
        tourStart: String,
        tourDecline: String,
        tourNext: String,
        tourSkip: String,
        tourDone: String,
        tourSeenUrl: String,
        tourSeenToken: String,
    };

    connect() {
        this.mode = "idle";
        this.timers = {};
        this.wireToggle();
        const state = this.loadState();
        this.lastIndex = state.lastIndex ?? -1;
        this.lastShownAt = state.lastShownAt ?? 0;
        this.introduced = state.introduced ?? false;
        // Only run the cheer cadence while enabled; a disabled mascot is rendered
        // parked off-screen (the `mascot--away` class) and stays silent until the
        // user turns it back on. Resume where the previous page left off so
        // navigating around doesn't pop a fresh message on every load.
        if (this.enabledValue) {
            if (this.tourValue.length > 0) {
                // A first-time visitor on the dashboard: offer Glimt's guided tour
                // instead of the usual cheer cadence.
                this.startTimer("cycle", () => this.proposeTour(), 1400);
            } else {
                const sinceLast = Date.now() - this.lastShownAt;
                this.scheduleNext(
                    Math.max(1800, this.intervalValue - sinceLast),
                );
            }
        }
    }

    disconnect() {
        this.endGame();
        this.clearNamedTimer("cycle");
        this.clearNamedTimer("show");
        window.clearTimeout(this.fadeTimer);
        window.clearTimeout(this.inviteTimer);
        window.clearTimeout(this.quietTimer);
        window.clearTimeout(this.toggleTimer);
        if (this.toggleForm && this.onToggleSubmit) {
            this.toggleForm.removeEventListener("submit", this.onToggleSubmit);
        }
    }

    // The disable/enable control lives in the user menu (outside this element),
    // so we reach for its <form> by id and intercept its submit. Without JS the
    // form posts normally and the server still persists the choice.
    wireToggle() {
        this.toggleForm = document.getElementById("mascotToggleForm");
        if (!this.toggleForm) {
            return;
        }
        this.toggleButton = this.toggleForm.querySelector("button");
        this.onToggleSubmit = (event) => {
            event.preventDefault();
            this.persistToggle();
        };
        this.toggleForm.addEventListener("submit", this.onToggleSubmit);
    }

    persistToggle() {
        const next = !this.enabledValue;
        const token = this.toggleForm.querySelector(
            'input[name="_token"]',
        ).value;
        fetch(this.toggleForm.action, {
            method: "POST",
            headers: {
                "X-Requested-With": "fetch",
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({ _token: token }),
        })
            .then((response) => {
                if (response.ok) {
                    this.applyEnabled(next);
                }
            })
            .catch(() => {});
    }

    // Drive the leaving/returning choreography once the server has stored it.
    applyEnabled(enabled) {
        this.enabledValue = enabled;
        if (this.toggleButton) {
            this.toggleButton.setAttribute(
                "aria-checked",
                enabled ? "true" : "false",
            );
        }
        window.clearTimeout(this.toggleTimer);

        if (enabled) {
            // Slide back in, then say how happy it is to be back.
            this.element.classList.remove("mascot--away");
            this.mode = "idle";
            this.toggleTimer = window.setTimeout(() => {
                this.say(this.welcomeValue, "cta");
                this.scheduleNext(this.intervalValue);
            }, 520);

            return;
        }

        // Wave goodbye, let it linger long enough to read, then slide off-screen.
        this.clearNamedTimer("cycle");
        this.say(this.farewellValue);
        this.toggleTimer = window.setTimeout(() => {
            this.hide();
            this.element.classList.add("mascot--away");
        }, 2600);
    }

    scheduleNext(delay) {
        this.startTimer("cycle", () => this.tick(), delay);
    }

    // Pausable timers: the cycle to the next message and the bubble's auto-hide.
    // Hovering the mascot freezes whatever time is left; leaving resumes it, so a
    // user reading or admiring the bubble is never rushed or interrupted.
    startTimer(name, callback, delay) {
        this.clearNamedTimer(name);
        const timer = { callback, remaining: delay, startedAt: Date.now() };
        timer.id = window.setTimeout(() => {
            delete this.timers[name];
            callback();
        }, delay);
        this.timers[name] = timer;
    }

    clearNamedTimer(name) {
        const timer = this.timers[name];
        if (timer) {
            window.clearTimeout(timer.id);
            delete this.timers[name];
        }
    }

    pause() {
        const now = Date.now();
        for (const timer of Object.values(this.timers)) {
            if (null === timer.id) {
                continue;
            }
            window.clearTimeout(timer.id);
            timer.id = null;
            timer.remaining = Math.max(
                0,
                timer.remaining - (now - timer.startedAt),
            );
        }
    }

    resume() {
        for (const [name, timer] of Object.entries(this.timers)) {
            if (null !== timer.id) {
                continue;
            }
            timer.startedAt = Date.now();
            timer.id = window.setTimeout(() => {
                delete this.timers[name];
                timer.callback();
            }, timer.remaining);
        }
    }

    tick() {
        if ("idle" === this.mode && !this.quiet) {
            if (
                !this.prefersReducedMotion &&
                Math.random() < this.playChanceValue
            ) {
                this.invite();
            } else {
                this.speak();
            }
        }
        this.scheduleNext(this.intervalValue);
    }

    // Clicking the avatar plays catch mid-game; an idle click pops a fresh
    // message and resets the cadence so the next auto-message isn't right behind.
    poke() {
        if ("invited" === this.mode) {
            this.startFlee();
        } else if ("flee" === this.mode) {
            this.caught();
        } else if ("idle" === this.mode) {
            this.speak();
            this.scheduleNext(this.intervalValue);
        }
    }

    speak() {
        // The first time it speaks in a session, Glimt introduces itself by name.
        if (!this.introduced && this.introValue) {
            this.introduced = true;
            this.say(this.introValue, "cta");

            return;
        }

        // A contact created on the fly (name only) gets a gentle reminder to
        // finish it, linking straight to its edit page.
        const contactTexts = this.finishContactTextsValue;
        if (
            this.hasFinishContactTarget &&
            contactTexts.length > 0 &&
            Math.random() < 0.15
        ) {
            this.say(
                contactTexts[Math.floor(Math.random() * contactTexts.length)],
                "finishContact",
            );

            return;
        }

        // Now and then, nudge the user to finish their least-complete project,
        // picking one of the finish lines at random for variety.
        const finishTexts = this.finishTextsValue;
        if (
            this.hasFinishTarget &&
            finishTexts.length > 0 &&
            Math.random() < 0.15
        ) {
            this.say(
                finishTexts[Math.floor(Math.random() * finishTexts.length)],
                "finish",
            );

            return;
        }
        this.say(this.nextMessage(), "cta");
    }

    invite() {
        this.mode = "invited";
        this.say(this.inviteValue, "play");
        window.clearTimeout(this.inviteTimer);
        this.inviteTimer = window.setTimeout(() => {
            if ("invited" === this.mode) {
                this.mode = "idle";
                this.hide();
            }
        }, 15000);
    }

    startFlee() {
        window.clearTimeout(this.inviteTimer);
        this.hide();
        this.mode = "flee";
        this.anchorPosition();
        this.fleeHandler = (event) => this.onFlee(event);
        document.addEventListener("pointermove", this.fleeHandler);
        this.giveUpTimer = window.setTimeout(() => this.giveUp(), 15000);
    }

    onFlee(event) {
        if (this.fleeCooldown) {
            return;
        }
        const box = this.avatar.getBoundingClientRect();
        const cx = box.left + box.width / 2;
        const cy = box.top + box.height / 2;
        if (Math.hypot(cx - event.clientX, cy - event.clientY) >= 75) {
            return;
        }
        // A short, bounded hop straight away from the pointer (clamped on-screen),
        // so the mascot can be cornered and caught instead of teleporting away.
        const angle = Math.atan2(cy - event.clientY, cx - event.clientX);
        const here = this.element.getBoundingClientRect();
        this.moveTo(
            here.left + Math.cos(angle) * 95,
            here.top + Math.sin(angle) * 95,
        );
        this.fleeCooldown = window.setTimeout(() => {
            this.fleeCooldown = null;
        }, 220);
    }

    caught() {
        window.clearTimeout(this.giveUpTimer);
        document.removeEventListener("pointermove", this.fleeHandler);
        this.mode = "chase-prep";
        this.say(this.caughtValue);
        this.prepTimer = window.setTimeout(() => this.startChase(), 1700);
    }

    startChase() {
        this.hide();
        this.mode = "chase";
        this.element.classList.add("mascot--chasing");
        this.pointer = { x: window.innerWidth / 2, y: window.innerHeight / 2 };
        this.chaseHandler = (event) => {
            this.pointer.x = event.clientX;
            this.pointer.y = event.clientY;
        };
        document.addEventListener("pointermove", this.chaseHandler);
        this.giveUpTimer = window.setTimeout(() => this.escape(), 8000);
        this.chaseStep();
    }

    chaseStep() {
        if ("chase" !== this.mode) {
            return;
        }
        const box = this.avatar.getBoundingClientRect();
        const dx = this.pointer.x - (box.left + box.width / 2);
        const dy = this.pointer.y - (box.top + box.height / 2);
        const dist = Math.hypot(dx, dy);
        if (dist < 38) {
            this.gotcha();

            return;
        }
        // Move toward the pointer, capped at a gentle top speed so the chase is
        // playful and evadable rather than instant.
        const step = Math.min(7, dist * 0.12) / dist;
        const here = this.element.getBoundingClientRect();
        this.moveTo(here.left + dx * step, here.top + dy * step);
        this.raf = window.requestAnimationFrame(() => this.chaseStep());
    }

    gotcha() {
        this.endGame();
        this.say(this.gotchaValue);
    }

    giveUp() {
        this.endGame();
        this.say(this.giveupValue);
    }

    // The mascot ran out of time chasing — it owns the loss, no "draw".
    escape() {
        this.endGame();
        this.say(this.escapedValue);
    }

    endGame() {
        window.cancelAnimationFrame(this.raf);
        window.clearTimeout(this.giveUpTimer);
        window.clearTimeout(this.prepTimer);
        window.clearTimeout(this.fleeCooldown);
        this.fleeCooldown = null;
        if (this.fleeHandler) {
            document.removeEventListener("pointermove", this.fleeHandler);
        }
        if (this.chaseHandler) {
            document.removeEventListener("pointermove", this.chaseHandler);
        }
        this.element.classList.remove("mascot--playing", "mascot--chasing");
        this.element.style.left = "";
        this.element.style.top = "";
        this.mode = "idle";

        // Stay quiet after a game so the closing quip can be read and nothing new
        // pops up right after: it shows for ~8s, then ~5s of calm before cheering.
        this.quiet = true;
        window.clearTimeout(this.quietTimer);
        this.quietTimer = window.setTimeout(() => {
            this.quiet = false;
        }, 13000);
    }

    anchorPosition() {
        const box = this.element.getBoundingClientRect();
        this.element.classList.add("mascot--playing");
        this.moveTo(box.left, box.top);
    }

    moveTo(left, top) {
        const margin = 8;
        const w = this.element.offsetWidth;
        const h = this.element.offsetHeight;
        this.element.style.left = `${Math.max(margin, Math.min(left, window.innerWidth - w - margin))}px`;
        this.element.style.top = `${Math.max(margin, Math.min(top, window.innerHeight - h - margin))}px`;
    }

    say(text, action = null) {
        if (!this.hasTextTarget || !text) {
            return;
        }
        this.textTarget.textContent = text;
        if (this.hasCtaTarget) {
            this.ctaTarget.hidden = "cta" !== action;
        }
        if (this.hasPlayTarget) {
            this.playTarget.hidden = "play" !== action;
        }
        if (this.hasFinishTarget) {
            this.finishTarget.hidden = "finish" !== action;
        }
        if (this.hasFinishContactTarget) {
            this.finishContactTarget.hidden = "finishContact" !== action;
        }
        // A cheer message never carries the tour's own buttons; make sure none
        // linger after the tour has ended.
        if (this.hasTourNextTarget) {
            this.tourNextTarget.hidden = true;
        }
        if (this.hasTourSkipTarget) {
            this.tourSkipTarget.hidden = true;
        }
        this.bubbleTarget.hidden = false;
        window.requestAnimationFrame(() => {
            this.bubbleTarget.classList.add("is-visible");
        });
        this.startTimer("show", () => this.hide(), 20000);
        this.lastShownAt = Date.now();
        this.saveState();
    }

    hide() {
        this.bubbleTarget.classList.remove("is-visible");
        this.clearNamedTimer("show");
        window.clearTimeout(this.fadeTimer);
        this.fadeTimer = window.setTimeout(() => {
            this.bubbleTarget.hidden = true;
        }, 250);
    }

    // The × just closes the current bubble; the mascot stays and cheers again later.
    close() {
        if ("tour" === this.mode) {
            this.tourSkip();

            return;
        }
        if ("invited" === this.mode) {
            this.mode = "idle";
            window.clearTimeout(this.inviteTimer);
        }
        this.hide();
    }

    // ---- Guided tour -------------------------------------------------------
    // Offered once to a first-time visitor on the dashboard. Glimt flies to each
    // area, spotlights it and explains it; the user steps through with Next (or
    // Skip). Either choice marks the tour seen so it is never proposed again.
    proposeTour() {
        this.mode = "tour";
        this.tourIndex = -1;
        this.introduced = true;
        this.tourSay(this.tourProposeValue, {
            next: this.tourStartValue,
            skip: this.tourDeclineValue,
        });
    }

    tourNext() {
        if ("tour" !== this.mode) {
            return;
        }
        if (-1 === this.tourIndex) {
            this.markTourSeen();
        }
        this.tourIndex += 1;
        if (this.tourIndex >= this.tourValue.length) {
            this.endTour();

            return;
        }
        this.showTourStep();
    }

    tourSkip() {
        this.markTourSeen();
        this.endTour();
    }

    showTourStep() {
        const step = this.tourValue[this.tourIndex];
        const last = this.tourIndex === this.tourValue.length - 1;
        this.spotlight(step.targets);
        if (!this.element.classList.contains("mascot--playing")) {
            // Anchor at the avatar's current corner spot before switching to
            // free positioning, so the first glide starts from where it sits.
            const box = this.avatar.getBoundingClientRect();
            this.element.classList.add("mascot--playing");
            this.moveTo(box.left, box.top);
        }
        this.element.classList.add("mascot--tour");
        // The opening step has no target, so Glimt grows a little and speaks to
        // the user directly.
        this.element.classList.toggle("mascot--hero", 0 === this.tourIndex);
        this.tourSay(step.text, {
            next: last ? this.tourDoneValue : this.tourNextValue,
            skip: last ? null : this.tourSkipValue,
        });
        // Position after the bubble is laid out so it can be measured and kept
        // clear of the spotlighted target.
        window.requestAnimationFrame(() => this.positionNear(step));
    }

    endTour() {
        this.spotlight(null);
        if (this.spotlightEl) {
            this.spotlightEl.remove();
            this.spotlightEl = null;
        }
        this.hide();
        this.element.classList.remove(
            "mascot--playing",
            "mascot--tour",
            "mascot--hero",
        );
        this.element.style.left = "";
        this.element.style.top = "";
        this.mode = "idle";
        this.scheduleNext(this.intervalValue);
    }

    // The tour bubble carries its own Next/Skip buttons and never auto-hides —
    // the user drives it. Hide the cheer-time actions while it is up.
    tourSay(text, { next, skip } = {}) {
        if (!this.hasTextTarget) {
            return;
        }
        this.textTarget.textContent = text;
        for (const target of [
            this.hasCtaTarget && this.ctaTarget,
            this.hasPlayTarget && this.playTarget,
            this.hasFinishTarget && this.finishTarget,
            this.hasFinishContactTarget && this.finishContactTarget,
        ]) {
            if (target) {
                target.hidden = true;
            }
        }
        if (this.hasTourNextTarget) {
            this.tourNextTarget.hidden = !next;
            if (next) {
                this.tourNextTarget.textContent = next;
            }
        }
        if (this.hasTourSkipTarget) {
            this.tourSkipTarget.hidden = !skip;
            if (skip) {
                this.tourSkipTarget.textContent = skip;
            }
        }
        this.clearNamedTimer("show");
        this.bubbleTarget.hidden = false;
        window.requestAnimationFrame(() => {
            this.bubbleTarget.classList.add("is-visible");
        });
    }

    ensureSpotlight() {
        if (!this.spotlightEl) {
            this.spotlightEl = document.createElement("div");
            this.spotlightEl.className = "tour-spotlight";
            document.body.appendChild(this.spotlightEl);
        }

        return this.spotlightEl;
    }

    // One dimming overlay that punches a hole over each target via clip-path.
    // Reusing a single element lets the holes glide (and grow/shrink) from section
    // to section instead of the dim flashing off and back on. No targets = the
    // whole screen dims.
    spotlight(targets) {
        const sp = this.ensureSpotlight();
        const first = !sp.classList.contains("is-visible");
        const rects = this.rectsFor(targets);
        sp.style.clipPath = this.dimPath(rects);
        if (first) {
            window.requestAnimationFrame(() => sp.classList.add("is-visible"));
        }
    }

    rectsFor(targets) {
        return (targets || [])
            .map((selector) => document.querySelector(selector))
            .filter(Boolean)
            .map((el) => el.getBoundingClientRect());
    }

    // A full-screen rectangle with up to two rectangular holes (even-odd fill).
    // Always two holes — an unused one collapses to a point at the centre — so the
    // path keeps a constant shape and animates between steps rather than jumping.
    dimPath(rects) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const pad = 6;
        const cx = w / 2;
        const cy = h / 2;
        const hole = (rect) => {
            if (!rect) {
                return `M${cx} ${cy} L${cx} ${cy} L${cx} ${cy} L${cx} ${cy} Z`;
            }
            const x1 = rect.left - pad;
            const y1 = rect.top - pad;
            const x2 = rect.right + pad;
            const y2 = rect.bottom + pad;
            return `M${x1} ${y1} L${x2} ${y1} L${x2} ${y2} L${x1} ${y2} Z`;
        };
        const outer = `M0 0 L${w} 0 L${w} ${h} L0 ${h} Z`;

        return `path(evenodd, "${outer} ${hole(rects[0])} ${hole(rects[1])}")`;
    }

    unionRect(targets) {
        const rects = this.rectsFor(targets);
        if (0 === rects.length) {
            return null;
        }
        const left = Math.min(...rects.map((r) => r.left));
        const top = Math.min(...rects.map((r) => r.top));
        const right = Math.max(...rects.map((r) => r.right));
        const bottom = Math.max(...rects.map((r) => r.bottom));

        return { left, top, right, bottom, width: right - left };
    }

    // Place Glimt relative to the step's target area, per the step's placement.
    // The bubble grows up-and-left from the avatar, so its measured size drives
    // where the avatar lands and keeps the bubble on-screen.
    positionNear(step) {
        const avatar = 68;
        const w = window.innerWidth;
        const h = window.innerHeight;
        const bubbleW = this.bubbleTarget.offsetWidth || 260;
        const bubbleH = this.bubbleTarget.offsetHeight || 130;
        const clampLeft = (x) =>
            Math.max(bubbleW - 56, Math.min(x, w - avatar - 12));
        const area = this.unionRect(step.targets);
        const placement = step.placement || "below";

        let left;
        let top;
        if ("corner" === placement) {
            left = w - avatar - 24;
            top = h - avatar - 24;
        } else if (!area || "center" === placement) {
            left = clampLeft(w / 2 - avatar + bubbleW / 2);
            top = Math.max(bubbleH + 32, Math.round(h * 0.42));
        } else if ("right" === placement) {
            left = w - avatar - 32;
            top = Math.max(bubbleH + 24, Math.round(h / 2));
        } else if ("left" === placement) {
            left = clampLeft(area.left - 20 - avatar);
            top = Math.max(bubbleH + 16, area.bottom + 16);
        } else {
            const centre = area.left + area.width / 2;
            left = clampLeft(centre - avatar + bubbleW / 2);
            top = area.bottom + bubbleH + 22;
        }
        this.moveTo(left, top);
    }

    markTourSeen() {
        if (this.tourSeenSent || !this.tourSeenUrlValue) {
            return;
        }
        this.tourSeenSent = true;
        fetch(this.tourSeenUrlValue, {
            method: "POST",
            headers: {
                "X-Requested-With": "fetch",
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({ _token: this.tourSeenTokenValue }),
        }).catch(() => {});
    }

    // Pick a message different from the last one shown, so it never repeats twice.
    nextMessage() {
        const messages = this.messagesValue;
        if (0 === messages.length) {
            return "";
        }
        if (1 === messages.length) {
            return messages[0];
        }

        let index = this.lastIndex;
        while (index === this.lastIndex) {
            index = Math.floor(Math.random() * messages.length);
        }
        this.lastIndex = index;

        return messages[index];
    }

    // Persist the cadence across page loads so navigating doesn't re-trigger a
    // greeting, and the last message isn't repeated on the next page.
    loadState() {
        try {
            return JSON.parse(sessionStorage.getItem("mascot-state") || "{}");
        } catch {
            return {};
        }
    }

    saveState() {
        try {
            sessionStorage.setItem(
                "mascot-state",
                JSON.stringify({
                    lastShownAt: this.lastShownAt ?? 0,
                    lastIndex: this.lastIndex,
                    introduced: this.introduced,
                }),
            );
        } catch {
            // sessionStorage may be unavailable (private mode / quota); ignore.
        }
    }

    get avatar() {
        return this.element.querySelector(".mascot__avatar");
    }

    get prefersReducedMotion() {
        return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    }
}
