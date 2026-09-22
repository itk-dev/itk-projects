import { Controller } from "@hotwired/stimulus";

/*
 * Debounced autosave for the project form.
 *
 * On the edit form it posts changes to the edit endpoint without re-rendering,
 * so focus and caret are never lost. On the new form (isNew) the first valid
 * save creates the project and the controller swaps to editing that record
 * in place (URL + action). Picking a file uploads it straight away via the same
 * POST; afterwards the media turbo-frame is reloaded so the stored file shows as
 * a link and its (now redundant) input is cleared — without that the file would
 * linger in the input and re-upload on every later keystroke.
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

    schedule(event) {
        // A picked file uploads on its own — save right away, no debounce.
        if (event?.target?.type === "file") {
            window.clearTimeout(this.timer);
            this.save();
            return;
        }
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

        // A create or a file upload must run to completion; don't start a second
        // save on top of one (it would double-create the draft or cut the upload).
        if (this.busy) {
            return;
        }

        const withFiles = this.hasPendingFile();
        // Supersede any in-flight plain edit; the new POST carries the whole form.
        this.xhr?.abort();
        this.busy = this.isNewValue || withFiles;

        // Reveal the saving spinner only for slow saves (> 2s); a quick save jumps
        // straight to "Gemt" with no flicker.
        const savingTimer = window.setTimeout(
            () => this.setStatus(this.savingTextValue, "saving"),
            2000,
        );

        // Picking a file drives a progress bar in the upload field via these events.
        if (withFiles) {
            this.dispatch("uploadstart", { target: document });
        }
        let ok = false;

        try {
            const { status, location } = await this.request(
                withFiles
                    ? (percent) =>
                          this.dispatch("uploadprogress", {
                              target: document,
                              detail: { percent },
                          })
                    : null,
            );

            if (201 === status) {
                ok = true;
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
                if (withFiles) {
                    this.refreshMedia();
                }
            } else if (204 === status) {
                ok = true;
                this.setStatus(
                    `${this.savedTextValue} · ${this.timestamp()}`,
                    "saved",
                );
                if (withFiles) {
                    this.refreshMedia();
                }
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
            if (withFiles) {
                this.dispatch("uploadend", {
                    target: document,
                    detail: { ok },
                });
            }
        }
    }

    // POST the whole form via XHR so file uploads can report real progress.
    // Resolves with the status and the X-Project-Location header (if any);
    // rejects with an AbortError when superseded.
    request(onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            this.xhr = xhr;
            xhr.open("POST", this.element.action, true);
            xhr.setRequestHeader("X-Autosave", "1");
            xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

            if (onProgress && xhr.upload) {
                xhr.upload.addEventListener("progress", (event) => {
                    if (event.lengthComputable) {
                        onProgress(
                            Math.round((event.loaded / event.total) * 100),
                        );
                    }
                });
            }

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

    hasPendingFile() {
        return Array.from(
            this.element.querySelectorAll('input[type="file"]'),
        ).some((input) => input.files && input.files.length > 0);
    }

    hasEmptyRequiredField() {
        return Array.from(
            this.element.querySelectorAll("[data-autosave-required]"),
        ).some((field) => "" === field.value.trim());
    }

    // Reload just the files/images section so a freshly uploaded file shows as a
    // link and its input is reset — the rest of the form keeps its state.
    refreshMedia() {
        const frame = document.getElementById("project-media");
        if (!frame) {
            return;
        }
        if (frame.src) {
            frame.reload();
        } else {
            // First reload also gives the frame its source (the edit URL).
            frame.src = this.element.action;
        }
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
