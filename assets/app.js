import "./stimulus_bootstrap.js";
import TomSelect from "tom-select";

function initCollections() {
    document.querySelectorAll("[data-collection]").forEach((collection) => {
        if (collection.dataset.bound) {
            return;
        }
        const list = collection.querySelector("[data-collection-list]");
        const addButton = collection.querySelector("[data-collection-add]");
        if (!list || !addButton) {
            return;
        }
        collection.dataset.bound = "1";

        let index = list.querySelectorAll("[data-collection-item]").length;

        const addRemoveButton = (item) => {
            if (item.querySelector("[data-collection-remove]")) {
                return;
            }
            const remove = document.createElement("button");
            remove.type = "button";
            remove.className = "btn btn--danger btn--sm";
            remove.dataset.collectionRemove = "";
            remove.textContent = collection.dataset.removeLabel || "Remove";
            remove.addEventListener("click", () => {
                item.remove();
                // Autosave is the only save path now — tell it the form changed so
                // the removed row is persisted.
                collection.dispatchEvent(
                    new Event("change", { bubbles: true }),
                );
            });
            item.appendChild(remove);
        };

        list.querySelectorAll("[data-collection-item]").forEach(
            addRemoveButton,
        );

        addButton.addEventListener("click", () => {
            const prototype = collection.dataset.prototype;
            const html = prototype.replace(/__name__/g, String(index));
            index += 1;

            const wrapper = document.createElement("div");
            wrapper.className = "collection__item";
            wrapper.dataset.collectionItem = "";
            wrapper.innerHTML = html;
            addRemoveButton(wrapper);
            list.appendChild(wrapper);
        });
    });
}

const capitalize = (value) =>
    value ? value.charAt(0).toUpperCase() + value.slice(1) : value;

// A chip multiselect whose dropdown is a shared pool served as inspiration.
// create lets a user add a brand-new value that is persisted on save and then
// shows up in everyone's pool on the next page load; a capitalised value also
// collapses onto an existing option of the same name instead of duplicating.
// `transform` normalises a freshly typed value before it becomes a chip.
function initCreatableSelect(selector, poolKey, transform) {
    document.querySelectorAll(selector).forEach((input) => {
        if (input.dataset.bound) {
            return;
        }
        input.dataset.bound = "1";

        let pool = [];
        try {
            pool = JSON.parse(input.dataset[poolKey] || "[]");
        } catch {
            pool = [];
        }

        // The current values plus the pool become the selectable options; the
        // current ones are kept selected.
        const current = input.value
            .split(",")
            .map((value) => value.trim())
            .filter(Boolean);
        const options = [...new Set([...current, ...pool])].map((name) => ({
            value: name,
            text: name,
        }));

        new TomSelect(input, {
            plugins: ["remove_button"],
            options,
            items: current,
            create: (typed) => {
                const name = transform(typed.trim());
                return { value: name, text: name };
            },
            createOnBlur: true,
            persist: false,
            hideSelected: true,
            maxOptions: null,
        });
    });
}

// Free-tagging term fields (strategies, stakeholders, tags) capitalise new
// entries; contacts keep the typed name as-is.
function initTermSelect() {
    initCreatableSelect("[data-term-select]", "termPool", capitalize);
}

function initContactSelect() {
    initCreatableSelect(
        "[data-contact-select]",
        "contactPool",
        (value) => value,
    );
}

function initPartnerSelect() {
    initCreatableSelect(
        "[data-partner-select]",
        "partnerPool",
        (value) => value,
    );
}

// One delegated handler on the document (which survives Turbo navigations and
// cache restores) both opens the menu — when the click lands on the toggle —
// and closes it on any outside click. Delegation avoids per-page binding, which
// breaks when Turbo restores a cached <body> whose toggle still carries the
// "bound" marker but has lost its (never-cached) listener.
document.addEventListener("click", (event) => {
    const menu = document.getElementById("userMenu");
    if (!menu) {
        return;
    }
    const toggle = document.getElementById("userMenuToggle");
    if (toggle && toggle.contains(event.target)) {
        menu.classList.toggle("is-open");
        return;
    }
    if (!menu.contains(event.target)) {
        menu.classList.remove("is-open");
    }
});

// Turbo Drive swaps <body> on each visit and never fires DOMContentLoaded;
// turbo:load runs on the first load and on every subsequent visit.
document.addEventListener("turbo:load", () => {
    initCollections();
    initContactSelect();
    initPartnerSelect();
    initTermSelect();
});

// Submitting from inside a confirm dialog caches the page with the dialog still
// open; restoring that snapshot would render it inline, out of the top layer.
document.addEventListener("turbo:before-cache", () => {
    document.querySelectorAll("dialog[open]").forEach((dialog) => {
        dialog.close();
    });
});
