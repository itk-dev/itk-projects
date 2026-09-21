import { Controller } from "@hotwired/stimulus";
import Chart from "chart.js/auto";

/*
 * Renders the dashboard visualisations (heatmap, collaboration panel and the
 * Chart.js charts) from a single JSON data island. Charts are built lazily the
 * first time they scroll into view, so their entrance animation plays when seen
 * rather than off-screen at page load. The live broadcast replaces the data
 * island; a MutationObserver feeds the new numbers to the existing charts so
 * they animate the delta instead of re-mounting from zero on every save.
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
const FUNDING_COLORS = ["#007ba6", "#008d3d", "#f5b800", "#ee0043", "#adb5bd"];
const TEAL = "#007ba6";
// borderWidth must have a numeric base: it is not in the bar animation group,
// so Chart.js animates it from its current value on hover — undefined would
// crash the interpolator ("this._fn is not a function").
const BAR_HOVER = {
    borderWidth: 0,
    hoverBorderColor: "rgba(15, 19, 21, .35)",
    hoverBorderWidth: 2,
};
const MONTHS = [
    "jan",
    "feb",
    "mar",
    "apr",
    "maj",
    "jun",
    "jul",
    "aug",
    "sep",
    "okt",
    "nov",
    "dec",
];

export default class extends Controller {
    static targets = [
        "source",
        "heatmap",
        "collab",
        "statusByDept",
        "statusDist",
        "funding",
        "budget",
        "reach",
        "timeline",
    ];

    static values = {
        empty: String,
    };

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
        this.collabSeen = false;
        this.buildHeatmap();
        this.renderCollab();
        this.lazyCharts();
        // Heatmap and collaboration render eagerly, so hold their entrance
        // animation until they actually scroll into view (otherwise it plays
        // off-screen at load and is never seen).
        this.revealOnView(this.heatmapTarget, () =>
            this.heatmapTarget.classList.remove("heat--paused"),
        );
        this.revealOnView(this.collabTarget, () => this.revealCollab());

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
        (this.revealObservers || []).forEach((o) => o.disconnect());
        Object.values(this.charts).forEach((c) => c.destroy());
        this.charts = {};
    }

    // Run fn the first time el scrolls into view (immediately if reduced motion
    // or no IntersectionObserver). Used to defer eager entrance animations.
    revealOnView(el, fn) {
        if (this.reduce || !("IntersectionObserver" in window)) {
            fn();
            return;
        }
        const obs = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        fn();
                        obs.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: "0px 0px -12% 0px", threshold: 0.15 },
        );
        obs.observe(el);
        (this.revealObservers = this.revealObservers || []).push(obs);
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
        this.updateHeatmap();
        this.renderCollab();
        this.updateCharts();
    }

    // ---- Lazy chart building (animate on scroll into view) -------------
    lazyCharts() {
        const specs = [
            [
                "statusByDept",
                this.statusByDeptTarget,
                () => this.buildStatusByDept(),
            ],
            ["statusDist", this.statusDistTarget, () => this.buildStatusDist()],
            ["funding", this.fundingTarget, () => this.buildFunding()],
            ["budget", this.budgetTarget, () => this.buildBudget()],
            ["timeline", this.timelineTarget, () => this.buildTimeline()],
            ["reach", this.reachTarget, () => this.buildReach()],
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

    // ---- Heatmap -------------------------------------------------------
    buildHeatmap() {
        const d = this.viz;
        const el = this.heatmapTarget;

        // The heatmap only makes sense once there are both rows (departments) and
        // columns (areas) to cross; until then show a placeholder, not bare headers.
        if (!d.areas.length || !d.departments.length) {
            el.style.gridTemplateColumns = "";
            el.classList.remove("heat--paused");
            el.innerHTML = `<p class="heat-empty">${this.esc(this.emptyValue)}</p>`;
            this.heatColHeads = [];
            this.heatCells = [];
            return;
        }

        el.style.gridTemplateColumns = `minmax(120px, 168px) repeat(${d.areas.length}, minmax(40px, 1fr))`;
        if (!this.reduce) {
            el.classList.add("heat--paused");
        }
        el.innerHTML = "";
        el.appendChild(document.createElement("div"));

        this.heatColHeads = d.areas.map((c) => {
            const head = document.createElement("div");
            head.className = "heat__collabel";
            head.innerHTML = `<span class="heat__synbadge" hidden>★ samarbejde</span><span>${this.esc(c.label)}</span>`;
            el.appendChild(head);
            return head;
        });

        this.heatCells = d.departments.map((dep) => {
            const label = document.createElement("div");
            label.className = "heat__rowlabel";
            label.textContent = dep.label;
            el.appendChild(label);
            return d.areas.map(() => {
                const cell = document.createElement("div");
                cell.className = "heat__cell";
                el.appendChild(cell);
                return cell;
            });
        });

        this.updateHeatmap(true);
    }

    updateHeatmap(initial = false) {
        const d = this.viz;
        if (!d.areas.length || !d.departments.length) {
            return;
        }
        let max = 1;
        d.heatmap.forEach((row) =>
            row.forEach((v) => {
                if (v > max) max = v;
            }),
        );

        d.heatmap.forEach((row, di) =>
            row.forEach((v, ci) => {
                const cell = this.heatCells[di][ci];
                cell.textContent = v === 0 ? "·" : v;
                cell.title = `${d.departments[di].label} · ${d.areas[ci].label}: ${v}`;
                const col = this.colorFor(v, max);
                if (col) {
                    cell.classList.remove("is-zero");
                    cell.style.background = col.bg;
                    cell.style.color = col.fg;
                } else {
                    cell.classList.add("is-zero");
                    cell.style.background = "";
                    cell.style.color = "";
                }
                if (initial && !this.reduce) {
                    cell.style.animationDelay =
                        (di * d.areas.length + ci) * 14 + "ms";
                }
            }),
        );

        d.areas.forEach((c, ci) => {
            const depts = d.heatmap.reduce(
                (n, row) => n + (row[ci] > 0 ? 1 : 0),
                0,
            );
            const head = this.heatColHeads[ci];
            head.classList.toggle("is-synergy", depts >= 3);
            head.querySelector(".heat__synbadge").hidden = depts < 3;
        });
    }

    colorFor(count, max) {
        if (count <= 0) {
            return null;
        }
        const l = 84 - (count / max) * 52;
        return { bg: `hsl(193 78% ${l}%)`, fg: l < 56 ? "#fff" : "#202423" };
    }

    // ---- Collaboration -------------------------------------------------
    renderCollab() {
        const el = this.collabTarget;
        el.innerHTML = "";
        if (!this.viz.collaboration.length) {
            el.innerHTML =
                '<p class="collab-empty">Ingen tværgående områder endnu — kategorisér projekter for at finde sammenfald.</p>';
            return;
        }
        // Hold the entrance paused until the panel scrolls into view; once seen,
        // (re)renders from live updates animate immediately.
        const pause = !this.reduce && !this.collabSeen;
        this.viz.collaboration.forEach((o) => {
            const chips = o.inits
                .map(
                    (i) =>
                        `<span class="chip"><span class="dot" style="background:${this.deptColor(i.deptKey)}"></span><b>${this.esc(i.title)}</b> <span>· ${this.esc(i.deptLabel)}</span></span>`,
                )
                .join("");
            const div = document.createElement("div");
            div.className =
                "opp" +
                (this.reduce ? "" : " opp--in") +
                (pause ? " opp--paused" : "");
            div.innerHTML = `<div class="opp__top">
                    <span class="opp__theme">${this.esc(o.theme)}</span>
                    <span class="opp__rank ${o.rank === "high" ? "rank-high" : "rank-med"}">${o.rank === "high" ? "Højt potentiale" : "Muligt"}</span>
                </div>
                <div class="opp__meta">${o.departmentCount} afdelinger · ${o.projectCount} projekter</div>
                <div class="opp__chips">${chips}</div>
                <div class="meter"><i style="width:${this.reduce ? o.strength : 0}%" data-w="${o.strength}"></i></div>`;
            el.appendChild(div);
        });
        if (!this.reduce && !pause) {
            this.fillMeters();
        }
    }

    revealCollab() {
        this.collabSeen = true;
        this.collabTarget
            .querySelectorAll(".opp--paused")
            .forEach((o) => o.classList.remove("opp--paused"));
        this.fillMeters();
    }

    fillMeters() {
        if (this.reduce) {
            return;
        }
        requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                this.collabTarget
                    .querySelectorAll(".meter > i")
                    .forEach((i) => {
                        i.style.width = i.dataset.w + "%";
                    });
            }),
        );
    }

    deptIndex(key) {
        return this.viz.departments.findIndex((d) => d.key === key);
    }

    deptColor(key) {
        const i = this.deptIndex(key);
        return i >= 0 ? DEPT_COLORS[i % DEPT_COLORS.length] : "#64748b";
    }

    esc(s) {
        const d = document.createElement("div");
        d.textContent = s;
        return d.innerHTML;
    }

    // ---- Charts --------------------------------------------------------
    buildStatusByDept() {
        const d = this.viz;
        this.charts.statusByDept = new Chart(this.statusByDeptTarget, {
            type: "bar",
            data: {
                labels: d.departments.map((x) => x.label),
                datasets: d.statuses.map((s, i) => ({
                    label: s.label,
                    data: d.statusByDept[i],
                    backgroundColor: STATUS_COLORS[i % STATUS_COLORS.length],
                    borderRadius: 4,
                    borderSkipped: false,
                    ...BAR_HOVER,
                })),
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false, axis: "y" },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: "#f1f4f8" },
                    },
                    y: { stacked: true, grid: { display: false } },
                },
                plugins: { legend: { position: "bottom" } },
            },
        });
    }

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

    buildFunding() {
        const d = this.viz;
        this.charts.funding = new Chart(this.fundingTarget, {
            type: "doughnut",
            data: {
                labels: d.fundings.map((f) => f.label),
                datasets: [
                    {
                        data: d.fundingCount,
                        backgroundColor: d.fundings.map(
                            (_, i) => FUNDING_COLORS[i % FUNDING_COLORS.length],
                        ),
                        borderWidth: 3,
                        borderColor: "#fff",
                        hoverOffset: 12,
                        hoverBorderWidth: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "60%",
                plugins: { legend: { position: "right" } },
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

    buildReach() {
        const d = this.viz;
        this.charts.reach = new Chart(this.reachTarget, {
            type: "radar",
            data: {
                labels: d.reach.map((r) => r.label),
                datasets: [
                    {
                        label: "Afdelinger",
                        data: d.reach.map((r) => r.depts),
                        backgroundColor: "rgba(0, 123, 166, .18)",
                        borderColor: TEAL,
                        borderWidth: 2,
                        pointBackgroundColor: TEAL,
                        pointBorderColor: "#fff",
                        pointRadius: 3,
                        pointHitRadius: 12,
                        pointHoverRadius: 9,
                        pointHoverBackgroundColor: "#fff",
                        pointHoverBorderColor: TEAL,
                        pointHoverBorderWidth: 2,
                        hoverBorderWidth: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        suggestedMax: d.departments.length,
                        ticks: {
                            stepSize: 1,
                            showLabelBackdrop: false,
                            color: "#868e96",
                        },
                        grid: { color: "#e9ecef" },
                        angleLines: { color: "#e9ecef" },
                        pointLabels: { font: { size: 11 }, color: "#495057" },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: (c) => c.parsed.r + " afdelinger" },
                    },
                },
            },
        });
    }

    buildTimeline() {
        const t = this.timelineData();
        this.charts.timeline = new Chart(this.timelineTarget, {
            type: "bar",
            data: {
                labels: t.labels,
                datasets: [
                    {
                        data: t.spans,
                        backgroundColor: t.colors,
                        borderRadius: 5,
                        borderSkipped: false,
                        barThickness: 16,
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
                        min: 0,
                        max: t.max,
                        ticks: {
                            stepSize: 2,
                            callback: (v) => this.monthLabel(t.base, v),
                        },
                        grid: { color: "#f1f4f8" },
                    },
                    y: { grid: { display: false } },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) =>
                                `${t.depts[c.dataIndex]} · ${this.monthLabel(t.base, c.raw[0])} – ${this.monthLabel(t.base, c.raw[1])}`,
                        },
                    },
                },
            },
        });
    }

    timelineData() {
        const toMonth = (s) => {
            const p = s.split("-").map(Number);
            return p[0] * 12 + (p[1] - 1);
        };
        const items = this.viz.timeline.map((x) => ({
            s: toMonth(x.start),
            e: toMonth(x.end),
            title: x.title,
            dept: x.dept,
        }));
        let base = Infinity,
            maxEnd = 0;
        items.forEach((i) => {
            if (i.s < base) base = i.s;
        });
        if (!isFinite(base)) base = 0;
        items.forEach((i) => {
            if (i.e - base > maxEnd) maxEnd = i.e - base;
        });
        return {
            base,
            max: Math.max(maxEnd + 1, 6),
            labels: items.map((i) => i.title),
            spans: items.map((i) => [i.s - base, i.e - base]),
            colors: items.map((i) => this.deptColor(i.dept)),
            depts: items.map((i) => {
                const idx = this.deptIndex(i.dept);
                return idx >= 0 ? this.viz.departments[idx].label : "";
            }),
        };
    }

    monthLabel(base, offset) {
        const m = base + Math.round(offset);
        return (
            MONTHS[((m % 12) + 12) % 12] +
            " " +
            String(Math.floor(m / 12)).slice(2)
        );
    }

    // ---- Live updates: feed new data, let Chart.js animate the delta ---
    updateCharts() {
        const d = this.viz;
        const c = this.charts;
        if (c.statusByDept) {
            d.statuses.forEach((s, i) => {
                c.statusByDept.data.datasets[i].data = d.statusByDept[i];
            });
            c.statusByDept.update();
        }
        if (c.statusDist) {
            c.statusDist.data.datasets[0].data = d.statusDistribution;
            c.statusDist.update();
        }
        if (c.funding) {
            c.funding.data.datasets[0].data = d.fundingCount;
            c.funding.update();
        }
        if (c.budget) {
            c.budget.data.datasets[0].data = d.budgetByDept;
            c.budget.update();
        }
        if (c.reach) {
            c.reach.data.labels = d.reach.map((r) => r.label);
            c.reach.data.datasets[0].data = d.reach.map((r) => r.depts);
            c.reach.update();
        }
        if (c.timeline) {
            const t = this.timelineData();
            c.timeline.data.labels = t.labels;
            c.timeline.data.datasets[0].data = t.spans;
            c.timeline.data.datasets[0].backgroundColor = t.colors;
            c.timeline.options.scales.x.max = t.max;
            c.timeline.options.scales.x.ticks.callback = (v) =>
                this.monthLabel(t.base, v);
            c.timeline.update();
        }
    }
}
