import { Controller } from "@hotwired/stimulus";
import Chart from "chart.js/auto";

/*
 * Renders the dashboard charts (Chart.js) from a single JSON data island.
 * Charts are built lazily the first time they scroll into view, so their
 * entrance animation plays when seen rather than off-screen at page load. The
 * live broadcast replaces the data island; a MutationObserver feeds the new
 * numbers to the existing charts so they animate the delta instead of
 * re-mounting from zero on every save.
 */
// Palette from the itk-workspace prototype-ds v1 tokens (teal-led brand), with
// variations where more distinct hues were needed.
const DEPT_COLORS = [
    "#007ba6",
    "#89bd23",
    "#ee0043",
    "#f5b800",
    "#00a5cd",
    "#73bc99",
];
const STATUS_COLORS = [
    "#adb5bd",
    "#00a5cd",
    "#008d3d",
    "#f5b800",
    "#005876",
    "#e44930",
];
// borderWidth must have a numeric base: it is not in the bar animation group,
// so Chart.js animates it from its current value on hover — undefined would
// crash the interpolator ("this._fn is not a function").
const BAR_HOVER = {
    borderWidth: 0,
    hoverBorderColor: "rgba(15, 19, 21, .35)",
    hoverBorderWidth: 2,
};

export default class extends Controller {
    static targets = ["source", "statusDist", "budget"];

    connect() {
        this.reduce = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        ).matches;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 12;
        Chart.defaults.color = "#52606e";
        Chart.defaults.borderColor = "#eef1f5";
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.legend.labels.padding = 12;
        Chart.defaults.plugins.tooltip.cornerRadius = 8;
        Chart.defaults.plugins.tooltip.padding = 10;
        // Mutate the existing animation defaults rather than replacing the object:
        // replacing it drops internal keys Chart.js needs to resolve the tooltip/
        // hover interpolator, which throws "this._fn is not a function" on hover.
        Chart.defaults.animation.duration = this.reduce ? 0 : 800;
        Chart.defaults.animation.easing = "easeOutQuart";
        // Pointer cursor whenever the mouse is over a hoverable data element.
        Chart.defaults.onHover = (event, elements) => {
            const target = event.native && event.native.target;
            if (target) {
                target.style.cursor = elements.length ? "pointer" : "default";
            }
        };

        this.charts = {};
        this.viz = this.read();
        if (!this.viz) {
            return;
        }
        this.lazyCharts();

        this.observer = new MutationObserver(() => this.refresh());
        this.observer.observe(this.sourceTarget, {
            childList: true,
            characterData: true,
            subtree: true,
        });
    }

    disconnect() {
        this.observer && this.observer.disconnect();
        this.chartObserver && this.chartObserver.disconnect();
        Object.values(this.charts).forEach((c) => c.destroy());
        this.charts = {};
    }

    read() {
        try {
            return JSON.parse(this.sourceTarget.textContent);
        } catch (e) {
            return null;
        }
    }

    refresh() {
        const next = this.read();
        if (!next) {
            return;
        }
        this.viz = next;
        this.updateCharts();
    }

    // ---- Lazy chart building (animate on scroll into view) -------------
    lazyCharts() {
        const specs = [
            ["statusDist", this.statusDistTarget, () => this.buildStatusDist()],
            ["budget", this.budgetTarget, () => this.buildBudget()],
        ];

        // A single bad chart config must not take down the whole dashboard; log
        // which one failed so it can be fixed without blanking the rest.
        const build = (spec) => {
            try {
                spec[2]();
            } catch (e) {
                console.error(
                    `dashboard-viz: chart "${spec[0]}" failed to render`,
                    e,
                );
            }
        };

        if (this.reduce || !("IntersectionObserver" in window)) {
            specs.forEach(build);
            return;
        }

        this.chartObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    const spec = specs.find((s) => s[1] === entry.target);
                    if (spec) {
                        build(spec);
                        this.chartObserver.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: "0px 0px -10% 0px", threshold: 0.18 },
        );

        specs.forEach((s) => this.chartObserver.observe(s[1]));
    }

    // ---- Charts --------------------------------------------------------
    buildStatusDist() {
        const d = this.viz;
        this.charts.statusDist = new Chart(this.statusDistTarget, {
            type: "polarArea",
            data: {
                labels: d.statuses.map((s) => s.label),
                datasets: [
                    {
                        data: d.statusDistribution,
                        backgroundColor: d.statuses.map(
                            (_, i) => STATUS_COLORS[i % STATUS_COLORS.length],
                        ),
                        borderColor: "#fff",
                        borderWidth: 1,
                        hoverOffset: 12,
                        hoverBorderColor: "#fff",
                        hoverBorderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "right" } },
                scales: {
                    r: {
                        ticks: { display: false, backdropColor: "transparent" },
                        grid: { color: "#e9ecef" },
                        angleLines: { color: "#e9ecef" },
                    },
                },
            },
        });
    }

    buildBudget() {
        const d = this.viz;
        const kr = new Intl.NumberFormat("da-DK", {
            notation: "compact",
            maximumFractionDigits: 1,
        });
        this.charts.budget = new Chart(this.budgetTarget, {
            type: "bar",
            data: {
                labels: d.departments.map((x) => x.label),
                datasets: [
                    {
                        label: "Budget",
                        data: d.budgetByDept,
                        backgroundColor: d.departments.map(
                            (_, i) => DEPT_COLORS[i % DEPT_COLORS.length],
                        ),
                        borderRadius: 6,
                        borderSkipped: false,
                        ...BAR_HOVER,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: "#f1f4f8" },
                        ticks: { callback: (v) => kr.format(v) + " kr" },
                    },
                    y: { grid: { display: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => kr.format(c.parsed.x) + " kr",
                        },
                    },
                },
            },
        });
    }

    // ---- Live updates: feed new data, let Chart.js animate the delta ---
    updateCharts() {
        const d = this.viz;
        const c = this.charts;
        if (c.statusDist) {
            c.statusDist.data.datasets[0].data = d.statusDistribution;
            c.statusDist.update();
        }
        if (c.budget) {
            c.budget.data.datasets[0].data = d.budgetByDept;
            c.budget.update();
        }
    }
}
