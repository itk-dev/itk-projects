import { Controller } from "@hotwired/stimulus";

/*
 * Groups the digits of a money field as they are typed ("1250000" becomes
 * "1.250.000" in Danish, "1,250,000" in English), in the page locale so the
 * value reads the way the server renders and parses it. Only digits and a
 * leading minus survive: amounts are whole kroner, and the minus is kept so a
 * negative amount still reaches the server and gets its validation error.
 */
export default class extends Controller {
    connect() {
        const locale = document.documentElement.lang || "en";
        this.formatter = new Intl.NumberFormat(locale, {
            maximumFractionDigits: 0,
        });
        this.format();
    }

    format() {
        const input = this.element;
        const before = input.value;
        const caret = input.selectionStart ?? before.length;
        // The caret is anchored to the digits typed before it; the separators
        // around them come and go.
        const digitsBeforeCaret = before
            .slice(0, caret)
            .replace(/\D/g, "").length;

        const after = this.group(before);
        if (after === before) {
            return;
        }
        input.value = after;

        let position = 0;
        let seen = 0;
        while (position < after.length && seen < digitsBeforeCaret) {
            if (/\d/.test(after[position])) {
                seen += 1;
            }
            position += 1;
        }
        input.setSelectionRange(position, position);
    }

    // The sign is added by hand: some locales format a negative number with a
    // typographic minus the server would not understand.
    group(value) {
        const negative = value.trimStart().startsWith("-");
        const digits = value.replace(/\D/g, "").replace(/^0+(?=\d)/, "");
        if ("" === digits) {
            return negative ? "-" : "";
        }

        return (negative ? "-" : "") + this.formatter.format(BigInt(digits));
    }
}
