import { Controller } from "@hotwired/stimulus";

/*
 * Live filtering for the project list. The form targets a Turbo Frame
 * (data-turbo-frame), so submitting it swaps only the results — no full page
 * load. Typing is debounced; selects submit on change. The submit button is
 * gone: this controller drives the submit, and clear() resets the fields.
 *
 * The frame carries data-turbo-action="advance", so the query it was fetched
 * with becomes the address bar URL — the deeplink trimQuery() keeps clean.
 */
export default class extends Controller {
    static targets = ["form"];

    static values = {
        debounce: { type: Number, default: 300 },
        exportUrl: String,
    };

    connect() {
        this.timer = null;
    }

    disconnect() {
        window.clearTimeout(this.timer);
    }

    submit() {
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(
            () => this.formTarget.requestSubmit(),
            this.debounceValue,
        );
    }

    // Filtering is live, so Enter has nothing left to run — and without a
    // submit button the browser would submit the form itself on every press.
    ignoreEnter(event) {
        event.preventDefault();
    }

    clear() {
        for (const input of this.formTarget.querySelectorAll("input")) {
            if (!["submit", "button", "reset"].includes(input.type)) {
                input.value = "";
            }
        }
        for (const select of this.formTarget.querySelectorAll("select")) {
            select.selectedIndex = 0;
        }
        window.clearTimeout(this.timer);
        this.formTarget.requestSubmit();
    }

    // Turbo re-reads detail.url after this event, and the frame adopts the
    // response URL — so dropping empty filters here is what shortens the link.
    trimQuery(event) {
        const url = new URL(event.detail.url);
        url.search = this.query();
        event.detail.url = url;
    }

    // Downloads a file, so it leaves Turbo behind.
    exportCsv() {
        const query = this.query();
        window.location.assign(
            query ? `${this.exportUrlValue}?${query}` : this.exportUrlValue,
        );
    }

    // Sorting is driven by links inside the frame rather than by a form field,
    // so it has to come off the URL or a filter change would reset it.
    query() {
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(this.formTarget)) {
            if ("" !== value) {
                params.append(name, value);
            }
        }

        const current = new URLSearchParams(window.location.search);
        for (const name of ["sort", "direction"]) {
            const value = current.get(name);
            if (value) {
                params.set(name, value);
            }
        }

        return params.toString();
    }
}
