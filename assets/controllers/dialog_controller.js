import { Controller } from "@hotwired/stimulus";

// A modal <dialog> opened from any button inside the controller's scope, so
// one dialog can be reached from several places on a page (the page header,
// an empty state inside a Turbo frame, …).
export default class extends Controller {
    static targets = ["dialog"];

    open() {
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    // A modal dialog fills the top layer, so a click on the backdrop reports the
    // dialog itself as the target; anything inside reports a descendant.
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }
}
