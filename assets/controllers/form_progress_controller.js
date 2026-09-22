import { Controller } from "@hotwired/stimulus";

/*
 * Drives the project form's completion bar. Completion = filled / total over a
 * fixed set of fields (the `fields` value, shared with the server so the bar and
 * the list percentage always agree), grouped by field key so a multi-input field
 * (links, funding) counts once. Used on both the new and edit forms.
 *
 * Stars: each starred (completion) field shows a star by its label. Filling the
 * field makes that star fly into the "trophy" star at the end of the bar; clearing
 * the field sends a star back from the trophy to the label. The bar and the trophy
 * advance only once a forward star lands on the trophy, and deduct the moment a
 * star leaves the trophy on the way back — so the motion and the numbers stay in
 * sync. `shownCount` is what the bar currently shows, which trails the true field
 * state during flight.
 */
export default class extends Controller {
    static targets = ["fill", "label", "star"];

    static values = {
        fields: { type: Array, default: [] },
        stars: { type: Boolean, default: true },
    };

    connect() {
        this.filledKeys = null;
        this.shownCount = 0;
        this.recompute();
    }

    recompute() {
        if (!this.hasFillTarget) {
            return;
        }

        const wanted = new Set(this.fieldsValue);
        const filled = new Set();
        const starForKey = new Map();

        for (const el of this.element.querySelectorAll(
            "input, select, textarea",
        )) {
            const key = this.fieldKey(el);
            if (!key || !wanted.has(key)) {
                continue;
            }
            if (!starForKey.has(key)) {
                starForKey.set(
                    key,
                    el
                        .closest(".form-row")
                        ?.querySelector(".completion-star") ?? null,
                );
            }
            if (this.isFilled(el)) {
                filled.add(key);
            }
        }

        this.total = wanted.size;

        // First pass (page load): reflect the current state with no animation.
        if (!this.filledKeys) {
            this.shownCount = filled.size;
            this.render();
            for (const [key, star] of starForKey) {
                star?.classList.toggle(
                    "completion-star--gone",
                    filled.has(key),
                );
            }
            this.filledKeys = filled;

            return;
        }

        for (const [key, star] of starForKey) {
            const isFilled = filled.has(key);
            const wasFilled = this.filledKeys.has(key);
            if (isFilled && !wasFilled) {
                this.sendStarToTrophy(star);
            } else if (!isFilled && wasFilled) {
                this.sendStarToLabel(star);
            }
        }

        this.filledKeys = filled;
    }

    // Field filled: the label star flies up; the bar/trophy advance only on arrival.
    sendStarToTrophy(star) {
        if (!this.hasStarTarget || !star) {
            return;
        }

        const fromRect = this.realRect(star);
        star.classList.add("completion-star--gone");

        this.flyBetween(
            fromRect,
            this.starTarget.getBoundingClientRect(),
            () => {
                this.shownCount += 1;
                this.render();
                this.popTrophy();
            },
            38,
        );
    }

    // Field cleared: deduct from the trophy now, then a star flies back to the label.
    sendStarToLabel(star) {
        if (!this.hasStarTarget || !star) {
            return;
        }

        this.shownCount -= 1;
        this.render();

        const fromRect = this.starTarget.getBoundingClientRect();
        this.flyBetween(fromRect, this.realRect(star), () => {
            star.classList.remove("completion-star--gone");
        });
    }

    // The label star's real on-screen box, even while collapsed (--gone), so a
    // returning star lands exactly where the star will sit, not at the text end.
    realRect(star) {
        const gone = star.classList.contains("completion-star--gone");
        if (gone) {
            // Drop the transition while measuring so the box reflects the star's
            // settled size, not its collapsed (mid-transition) one.
            star.style.transition = "none";
            star.classList.remove("completion-star--gone");
        }
        const rect = star.getBoundingClientRect();
        if (gone) {
            star.classList.add("completion-star--gone");
            // Flush the collapsed state while the transition is still off, so
            // re-enabling it below doesn't animate the star from full back to
            // hidden — that was the brief flash beside the label.
            void star.offsetWidth;
            star.style.transition = "";
        }

        return rect;
    }

    render() {
        const total = this.total || 0;
        const count = Math.max(0, Math.min(this.shownCount, total));
        const ratio = total ? count / total : 0;
        const percent = Math.round(ratio * 100);

        this.fillTarget.style.width = `${percent}%`;
        this.fillTarget.style.backgroundColor = `hsl(${Math.round(ratio * 120)}, 72%, 45%)`;
        this.fillTarget.parentElement?.setAttribute(
            "aria-valuenow",
            String(percent),
        );

        if (this.hasLabelTarget) {
            this.labelTarget.textContent = `${percent}%`;
        }

        this.updateTrophy(ratio);
    }

    updateTrophy(ratio) {
        if (!this.hasStarTarget) {
            return;
        }

        // Only the size tracks progress; colour stays gold like the label stars.
        this.starTarget.style.fontSize = `${(1 + ratio * 0.85).toFixed(2)}em`;
    }

    // `lift` (px) gives the forward flight a slow celebratory rise before it
    // launches fast into the trophy; 0 keeps the plain arc used on the way back.
    flyBetween(fromRect, toRect, onArrive, lift = 0) {
        const startX = fromRect.left + fromRect.width / 2;
        const startY = fromRect.top + fromRect.height / 2;
        const dx = toRect.left + toRect.width / 2 - startX;
        const dy = toRect.top + toRect.height / 2 - startY;

        // No flight when the user turned stars off (or prefers reduced motion):
        // the bar still advances, the star just doesn't fly.
        if (!this.animateStars) {
            onArrive();

            return;
        }

        const star = document.createElement("span");
        star.className = "flying-star";
        star.textContent = "★";
        star.setAttribute("aria-hidden", "true");
        star.style.left = `${startX}px`;
        star.style.top = `${startY}px`;
        document.body.appendChild(star);

        const arrive = {
            transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(0.85) rotate(360deg)`,
            opacity: 0,
        };

        let keyframes;
        let duration;
        if (lift > 0) {
            keyframes = [
                {
                    transform: "translate(-50%, -50%) scale(1.3) rotate(0deg)",
                    opacity: 1,
                    easing: "cubic-bezier(0.16, 1, 0.3, 1)",
                },
                {
                    transform: `translate(-50%, calc(-50% - ${lift}px)) scale(1.45) rotate(35deg)`,
                    opacity: 1,
                    offset: 0.5,
                    easing: "cubic-bezier(0.7, 0, 0.84, 0)",
                },
                {
                    transform: `translate(calc(-50% + ${dx * 0.94}px), calc(-50% + ${dy * 0.94}px)) scale(1) rotate(345deg)`,
                    opacity: 1,
                    offset: 0.94,
                },
                arrive,
            ];
            duration = 1000;
        } else {
            const apex = dy < 0 ? -14 : -8;
            keyframes = [
                {
                    transform: "translate(-50%, -50%) scale(1.3) rotate(0deg)",
                    opacity: 1,
                },
                {
                    transform: `translate(calc(-50% + ${dx * 0.5}px), calc(-50% + ${dy * 0.5 + apex}px)) scale(1.15) rotate(180deg)`,
                    opacity: 1,
                    offset: 0.5,
                },
                {
                    transform: `translate(calc(-50% + ${dx * 0.92}px), calc(-50% + ${dy * 0.92}px)) scale(1) rotate(330deg)`,
                    opacity: 1,
                    offset: 0.9,
                },
                arrive,
            ];
            duration = 760;
        }

        const flight = star.animate(keyframes, {
            duration,
            easing: "cubic-bezier(0.3, 0, 0.5, 1)",
        });

        flight.onfinish = () => {
            star.remove();
            onArrive();
        };
    }

    popTrophy() {
        if (!this.hasStarTarget || !this.animateStars) {
            return;
        }

        this.starTarget.animate(
            [
                { transform: "scale(1)" },
                { transform: "scale(1.55)" },
                { transform: "scale(1)" },
            ],
            { duration: 360, easing: "ease-out" },
        );
    }

    get prefersReducedMotion() {
        return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    }

    get animateStars() {
        return this.starsValue && !this.prefersReducedMotion;
    }

    // "project[links][0]" -> "links", "project[funding][]" -> "funding".
    fieldKey(el) {
        const match = el.name?.match(/\[([^\]]+)\]/);

        return match ? match[1] : null;
    }

    isFilled(el) {
        if ("checkbox" === el.type || "radio" === el.type) {
            return el.checked;
        }
        if ("SELECT" === el.tagName) {
            return el.multiple
                ? Array.from(el.selectedOptions).some((o) => "" !== o.value)
                : "" !== el.value;
        }
        return "" !== el.value.trim();
    }
}
