import { Controller } from "@hotwired/stimulus";

/*
 * Mirrors the project title into the page heading and breadcrumb as it is
 * typed, so a new (or freshly auto-created) project is identifiable before
 * the page is ever reloaded. Falls back to the original text when emptied.
 */
export default class extends Controller {
    static targets = ["heading", "crumb"];

    connect() {
        this.defaults = new Map();
        for (const el of this.mirrors) {
            this.defaults.set(el, el.textContent);
        }
    }

    update(event) {
        const value = event.target.value.trim();
        for (const el of this.mirrors) {
            el.textContent = value || this.defaults.get(el);
        }
    }

    // Once the draft is created it has a page of its own, so turn the breadcrumb
    // crumb from plain text into a link to it (keeping it a live-updating target).
    linkCrumb(event) {
        const url = event.detail?.showUrl;
        if (!url) {
            return;
        }
        this.crumbTargets.forEach((el) => {
            if ("A" === el.tagName) {
                return;
            }
            const link = document.createElement("a");
            link.href = url;
            link.textContent = el.textContent;
            link.setAttribute("data-title-mirror-target", "crumb");
            this.defaults.set(link, this.defaults.get(el) ?? el.textContent);
            el.replaceWith(link);
        });
    }

    get mirrors() {
        return [...this.headingTargets, ...this.crumbTargets];
    }
}
