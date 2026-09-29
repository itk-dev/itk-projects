import { Controller } from "@hotwired/stimulus";

/*
 * Debounced autosave for the project form.
 *
 * On the edit form it posts changes to the edit endpoint without re-rendering,
 * so focus and caret are never lost. On the new form (isNew) the first valid
 * save creates the project and the controller swaps to editing that record
 * in place (URL + action).
 */
export default class extends Controller {
    static targets = ["status", "statusText"];

    static values = {
        debounce: { type: Number, default: 800 },
        savingText: { type: String, default: "Saving…" },
        savedText: { type: String, default: "Saved" },
        unsavedText: { type: String, default: "Unsaved changes" },
        errorText: {
            type: String,
            default: "Couldn’t save — check the required fields",
        },
        offlineText: {
            type: String,
            default: "Save failed — your changes are kept here",
        },
        requiredText: { type: String, default: "Add a title to save" },
        isNew: { type: Boolean, default: false },
    };

    initialize() {
        this.timer = null;
        this.xhr = null;
        this.busy = false;
    }

    disconnect() {
        window.clearTimeout(this.timer);
        this.xhr?.abort();
    }

    schedule() {
        // No "unsaved" text — the animated pen icon carries that state.
        this.setStatus("", "unsaved");
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.save(), this.debounceValue);
    }

    async save() {
        // A required field (the title) is empty: don't POST an invalid form and
        // flash a save error. Show a hint and keep the last saved version — once
        // a title is typed again, the next change saves normally.
        if (this.hasEmptyRequiredField()) {
            this.setStatus(this.requiredTextValue, "unsaved");
            return;
        }

        // A create must run to completion; don't start a second save on top of
        // one (it would double-create the draft).
        if (this.busy) {
            return;
        }

        // Supersede any in-flight plain edit; the new POST carries the whole form.
        this.xhr?.abort();
        this.busy = this.isNewValue;

        // Reveal the saving spinner only for slow saves (> 2s); a quick save jumps
        // straight to "Gemt" with no flicker.
        const savingTimer = window.setTimeout(
            () => this.setStatus(this.savingTextValue, "saving"),
            2000,
        );

        try {
            const { status, location } = await this.request();

            if (201 === status) {
                // The draft now exists — edit it in place from here on.
                if (location) {
                    this.element.action = location;
                    this.isNewValue = false;
                    window.history.replaceState({}, "", location);
                    // Show URL = edit URL minus the trailing /edit; lets the
                    // breadcrumb turn the title into a link to the new record.
                    this.dispatch("created", {
                        detail: { showUrl: location.replace(/\/edit$/, "") },
                    });
                }
                this.setStatus(
                    `${this.savedTextValue} · ${this.timestamp()}`,
                    "saved",
                );
            } else if (204 === status) {
                this.setStatus(
                    `${this.savedTextValue} · ${this.timestamp()}`,
                    "saved",
                );
            } else if (422 === status) {
                this.setStatus(this.errorTextValue, "error");
            } else {
                this.setStatus(this.offlineTextValue, "error");
            }
        } catch (error) {
            if ("AbortError" !== error.name) {
                this.setStatus(this.offlineTextValue, "error");
            }
        } finally {
            window.clearTimeout(savingTimer);
            this.busy = false;
            this.xhr = null;
        }
    }

    // POST the whole form via XHR so an in-flight save can be aborted when a
    // newer one supersedes it. Resolves with the status and the
    // X-Project-Location header (if any); rejects with an AbortError when
    // superseded.
    request() {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            this.xhr = xhr;
            xhr.open("POST", this.element.action, true);
            xhr.setRequestHeader("X-Autosave", "1");
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

            xhr.addEventListener("load", () =>
                resolve({
                    status: xhr.status,
                    location: xhr.getResponseHeader("X-Project-Location"),
                }),
            );
            xhr.addEventListener("error", () =>
                reject(new Error("Network error")),
            );
            xhr.addEventListener("abort", () => {
                const error = new Error("Aborted");
                error.name = "AbortError";
                reject(error);
            });

            xhr.send(new FormData(this.element));
        });
    }

    hasEmptyRequiredField() {
        return Array.from(
            this.element.querySelectorAll("[data-autosave-required]"),
        ).some((field) => "" === field.value.trim());
    }

    setStatus(text, state) {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.className = `autosave-status autosave-status--${state}`;
        if (this.hasStatusTextTarget) {
            this.statusTextTarget.textContent = text;
        }
    }

    timestamp() {
        const locale = document.documentElement.lang || "en";

        return new Date().toLocaleTimeString(locale, {
            hour: "2-digit",
            minute: "2-digit",
        });
    }
}
