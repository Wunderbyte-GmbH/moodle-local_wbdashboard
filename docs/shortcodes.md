# local_wb_dashboard — shortcode reference

Three shortcodes are provided. They require the third-party **`filter_shortcodes`**
filter to be installed and enabled in the context where the content is shown.

- **`[chart ...]`** — renders one chart. Data is loaded client-side from a web
  service, so the tag only emits a canvas; the chart draws once the data arrives.
- **`[digits ...]`** — renders one **single value** (a number, count or percentage)
  as a styleable text field with a constant DOM id (see §4). Same client-side load
  and filter behaviour as `[chart]`, but the value is plain DOM text, not a canvas.
- **`[chartfilter ...]`** — renders one page-level filter control. Every chart or
  digits field on the same page (same `pageid`) that "consumes" the filter's key
  re-queries when the filter changes.

---

## 1. `[chart]`

### Common flags (all chart types)

| Flag | Values | Default | Description |
|------|--------|---------|-------------|
| `type` | `doughnut`, `bar`, `horizontalbar`, `stackedbar`, `progress` | `bar` | The chart type (see §3). |
| `source` | `reportbuilder` | — (required) | The data source (see §2). |
| `title` | text | plugin name | Chart title, also used as the canvas `aria-label`. |
| `width` | number (rem) | `32` | Max width of the chart container. |
| `height` | number (rem) | `20` | Height of the chart container. Use a small value (≈`8`) for `progress`. |
| `consumes` | comma-separated filter keys | *(all)* | Which page filter keys this chart reacts to. Omit to react to every filter the source can map. |
| `pageid` | alphanumeric | `default` | Groups the chart with the filters and other charts that share the same `pageid`. |
| `centertext` | `1`/`0` | `1` | **Doughnut only.** `0` hides the centre value/label text. |

Any flag **not** in the table above is passed to the source as a *source parameter*
(see §2) — unknown parameters are dropped server-side.

Colours are **not** set on the shortcode. Each chart follows the active palette by
default and can be individually recoloured through the per-chart settings gear (see
§7).

### Notes
- Charts never query data during page render; they emit a canvas and load via the
  `local_wb_dashboard_get_chart_data` web service.
- On invalid input (missing/unknown `source`, unknown `type`) the shortcode returns a
  short error message instead of breaking the page.

---

## 2. Source: `reportbuilder`

Pulls from a **core Report Builder** report. There are three shaping modes, chosen
by which parameters you supply. (The modes themselves are shared by every source —
future sources accept the same shaping parameters, with their own ids in place of
report ids.) Access is enforced per report: a viewer who lacks permission on a
referenced report gets an error, not data.

### Mode A — two-report delta (a value and its remainder)

Reads a single number from the first row of each of two reports and renders
`[base, total − base]`. This is the "logged vs remaining" shape.

| Param | Description |
|-------|-------------|
| `idbase` | Report id supplying the **achieved** value. |
| `fieldbase` | Field in that report holding the number. |
| `idtotal` | Report id supplying the **target/total** value. |
| `fieldtotal` | Field in that report holding the number. |

Best rendered as `doughnut` or `progress` (also works as `bar` / `horizontalbar`,
which then show two bars). The source also exposes `total` as the axis maximum, which
`progress` uses to fill the bar to 100 %.

```
[chart type=doughnut source=reportbuilder idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget width=32 height=20]
[chart type=progress  source=reportbuilder idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget width=40 height=8 title="Completion"]
```

### Mode B — rows (one data point per report row)

Reads every row of one report; each row becomes one data point. An optional
`stackfield` groups rows into series (for stacked/grouped bars).

| Param | Description |
|-------|-------------|
| `report` | The report id. |
| `categoryfield` | Field used as the category / x-axis label (one bar/point per distinct value). |
| `valuefield` | Field used as the **numeric** value (bar height). Required unless `aggregation=count`. |
| `stackfield` | *(optional)* Field whose distinct values become separate stacked series. |
| `aggregation` | *(optional)* `sum` (default) adds up `valuefield` per category; `count` tallies one per row (no `valuefield` needed). |

Best rendered as `bar`, `horizontalbar`, or `stackedbar` (with `stackfield`).

```
[chart type=bar        source=reportbuilder report=3 categoryfield=month valuefield=total width=40 height=24]
[chart type=stackedbar source=reportbuilder report=3 categoryfield=month valuefield=total stackfield=status width=40 height=24]
```

**Counting rows** — for a report where each row is an entity (e.g. a user), count
rows per category instead of summing a number. No numeric field is needed:

```
[chart type=bar source=reportbuilder report=3 categoryfield=country aggregation=count width=40 height=24]
[chart type=stackedbar source=reportbuilder report=3 categoryfield=country stackfield=role aggregation=count width=40 height=24]
```

### Mode C — multi-report totals (one bar per report)

Renders one data point per report: its **row count**, or the sum of a value field
across its rows. Labels are the report names. Use this to compare "how many users
(rows) are in report A vs report B vs …".

| Param | Description |
|-------|-------------|
| `reports` | Comma-separated report ids, e.g. `reports=6,3`. |
| `aggregation` | `count` (default here) counts rows per report; `sum` adds `valuefield`. |
| `valuefield` | *(optional)* Field to sum per report when `aggregation=sum`. |

```
[chart type=bar source=reportbuilder reports=6,3 aggregation=count title="Users per report"]
```

### Field names & values
- `valuefield` must resolve to a **numeric** value; text coerces to `0`. Category and
  stack fields are treated as labels.
- Field names are matched case-insensitively against the report column's **name** or
  its **unique identifier** (e.g. `user:fullname`). Use identifiers **without spaces**
  — `filter_shortcodes` splits arguments on whitespace.

---

## 3. Chart types

| `type` | Shape it expects | Renders as |
|--------|------------------|-----------|
| `doughnut` | one series of N slices (e.g. two-report delta) | Ring chart; optional centre text (`centertext`), no legend/tooltip. |
| `bar` | categories × one or more series | Vertical bars. |
| `horizontalbar` | categories × one or more series | Horizontal bars (`indexAxis: y`). |
| `stackedbar` | categories × multiple series (use `stackfield`) | Grouped **stacked** bars. |
| `progress` | one series of N segments + a total | Single horizontal stacked bar with a fixed maximum — a progress/percentage bar. |

`line` and `pie` are intentionally out of the v1 set (easy to add later).

---

## 4. `[digits]`

Renders a **single value** — a number, a count, or a percentage — as plain DOM
text you can style freely. Like `[chart]` it emits an empty field on page render and
loads the value client-side from the `local_wb_dashboard_get_digits_data` web
service, so it reacts to page filters (`consumes` / `pageid`) exactly the same way.

It reuses the same `reportbuilder` source (§2): whatever numbers a chart could draw,
a digits field can reduce to one value. The whole first data series is collapsed to a
single scalar — for `count`/`number` that is the **sum** of the series; for `percent`
it is `base ÷ total × 100`.

### Flags

| Flag | Values | Default | Description |
|------|--------|---------|-------------|
| `source` | `reportbuilder` | — (required) | The data source (see §2). |
| `display` | `number`, `count`, `percent` | `number` | How to reduce the source data (see below). `number` and `count` are equivalent (both sum the series). |
| `label` | text | source-derived | Text shown under the value. Overrides any label the source provides. |
| `decimals` | `0`–`6` | `0` | Decimal places for the formatted value (locale-aware via `format_float`). |
| `unit` | text | — | Suffix appended after the value (e.g. `pts`, `€`). Ignored for `percent`, which always uses `%`. |
| `consumes` | comma-separated filter keys | *(all)* | Which page filter keys this field reacts to. |
| `pageid` | alphanumeric | `default` | Groups the field with the filters/charts sharing the same `pageid`. |

Any flag **not** in this table is passed to the source as a *source parameter* (see
§2) — so `reports`, `report`, `valuefield`, `idbase`/`idtotal`, `aggregation`, etc.
all work exactly as they do for `[chart]`.

### `display` modes

- **`count` / `number`** — sum of the first data series. This is meaningful when the
  points are **parts of one whole**: a single report's row count, or a rows-mode
  `aggregation=count` totalled across its categories. Summing the counts of *unrelated*
  reports (`reports=6,3`) is **not** meaningful — for that, use `percent` (below) or one
  `[digits]` per report.
- **`percent`** — `base ÷ total × 100`. The total is resolved in this order:
  1. **base/total delta** — `idbase`/`fieldbase` (the achieved value) over
     `idtotal`/`fieldtotal` (the target). This reads a numeric field from each report.
  2. **two-report ratio** — `reports=<part>,<whole>` with `aggregation=count`: the
     first report's row count over the second's (e.g. *subset of users ÷ all users*).
  3. a single value on its own resolves to `100%` (or `0%` when it is zero).

  Division by zero yields `0`.

### Examples

```
# Row count of a single report (report 6):
[digits source=reportbuilder display=count reports=6 label="Active users"]

# Sum of a numeric field across a report's rows:
[digits source=reportbuilder display=number report=3 categoryfield=month valuefield=total label="Total hours"]

# Percentage — subset vs all (count of report 6 ÷ count of report 3):
[digits source=reportbuilder display=percent reports=6,3 aggregation=count label="% enrolled" decimals=1]

# Percentage — a base/total field pair, reacting to a page date filter:
[digits source=reportbuilder display=percent idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget label="Completion" decimals=1 consumes=period pageid=team]
```

> **Comparing two reports.** For "how much of the whole is this part" (a subset of users
> vs all users), use `display=percent reports=<part>,<whole> aggregation=count` — the ratio,
> not the sum. To show each report's raw count, use one `[digits]` per report; for a
> side-by-side bar comparison use `[chart type=bar reports=6,3 aggregation=count]`.

### DOM ids & styling

Each field is wrapped in a `<div>` with a **deterministic, constant** id derived from
its configuration (source + params + display mode), so it survives reloads and can be
targeted from CSS or theme SCSS. The inner value and label carry sub-ids:

```html
<div class="local-dashboard-digits" id="local-dashboard-digits-ab12cd34ef56">
    <div class="local-dashboard-digits-value" id="local-dashboard-digits-ab12cd34ef56-value">42%</div>
    <div class="local-dashboard-digits-label" id="local-dashboard-digits-ab12cd34ef56-label">Completion</div>
</div>
```

- Wrapper class `.local-dashboard-digits`, value class `.local-dashboard-digits-value`
  (percentages also get `.local-dashboard-digits-value--percent`), label class
  `.local-dashboard-digits-label`.
- The id is a `local-dashboard-digits-` prefix plus a short hash. **Two fields with an
  identical configuration on one page share the same id by design** — give them a
  different `label`/config, or rely on the class, if you need to distinguish them.

### Notes
- On invalid input (missing/unknown `source`, unknown `display`) the shortcode returns
  a short error message instead of breaking the page.
- If the source returns no rows it raises an error server-side; the field shows the
  standard notification rather than a value.

---

## 5. `[chartfilter]`

Renders a page-level control. Its value is shared via the URL (`?ldf_<key>=…`) and a
per-user cache, and fans out to every chart on the page that `consumes` the key.

### Flags

| Flag | Values | Default | Description |
|------|--------|---------|-------------|
| `key` | alphanumeric | — (required) | The logical filter key. Charts reference it via `consumes=`, and the source maps it to its own filtering. |
| `type` | `select`, `date`, `text`, `number` | `text` | The control type. |
| `pageid` | alphanumeric | `default` | Must match the charts' `pageid`. |
| `label` | text | the key | Visible label. |
| `default` | text | — | Initial value. |
| `options` | `value:Label,value:Label` | — | **`select` only.** The dropdown options. |
| `operator` | `eq`, `gte`, `lte` | `eq` | **`number` only.** Comparison used when applying the value. |

### How each type applies

Each filter emits a neutral constraint; the source applies it natively. For the
`reportbuilder` source, the `key` must match one of the report's own active filters
(by unique identifier, or the short name after the `:`), and it is applied as that
report filter:

| Filter type | Constraint | Report Builder mapping |
|-------------|-----------|------------------------|
| `select` | equals | select filter, "is equal to" |
| `text` | contains | text filter, "contains" |
| `number` | eq / gte / lte (per `operator`) | number filter, matching operator |
| `date` | on/after the chosen date | date filter, range from the chosen date |

A filter whose `key` a report does not have is simply ignored by that chart.

```
[chartfilter key=status  type=select label="Status" options="1:Open,2:Closed" pageid=demo]
[chartfilter key=period  type=date   label="From"    pageid=demo]
[chartfilter key=minhits type=number label="Min hits" operator=gte default=10 pageid=demo]
```

---

## 6. A full page example

```
[chartfilter key=period type=date label="From" pageid=team]

[digits source=reportbuilder display=count reports=7 label="Members" consumes=period pageid=team]
[digits source=reportbuilder display=percent idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget label="Completion" decimals=1 consumes=period pageid=team]

[chart type=doughnut  source=reportbuilder idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget consumes=period pageid=team]
[chart type=progress  source=reportbuilder idbase=3 fieldbase=minuteslogged idtotal=5 fieldtotal=minutestarget consumes=period pageid=team height=8]
[chart type=stackedbar source=reportbuilder report=7 categoryfield=month valuefield=total stackfield=status consumes=period pageid=team]
```

Changing **From** updates the URL, is remembered per user, and re-queries every field
and chart above — each applying `period` through its own report's date filter.

---

## 7. Per-chart colours (settings gear)

Charts follow the **active palette** by default. To recolour an individual chart,
users with the `local/wb_dashboard:configurecharts` capability (managers by default)
see a small **gear** button on each chart. It opens a modal with one **dropdown per
palette slot**, each listing the whole active palette, so a slot can be repointed at
any other palette colour; slots left on their default keep following the palette.
Saving re-draws that chart immediately — no page reload.

Overrides are:

- **Stored server-side**, in `local_wb_dashboard_chartcfg`, and therefore **shared by
  all viewers** (this is authoring config, not a per-user preference).
- **Sparse** — only the slots you actually override are stored; everything else tracks
  the live palette, so changing the palette still updates the untouched slots.
- **Merged over the palette at query time** (`chart_settings::resolve()`), then applied
  by the builder exactly where `color1`, `color2`, … used to apply.

### Chart identity

Each override is keyed to a **stable chart id** derived automatically from the chart's
context and its identity-defining configuration (`source`, `type` and source params).
Consequences worth knowing:

- Cosmetic edits (changing `title`, `width`, `height`) **keep** a chart's saved colours.
- Changing a **data** parameter (e.g. `report=3` → `report=4`) is a different chart, so
  it starts again from the palette.
- The id is namespaced by **context**, so the same shortcode on two different pages is
  configured independently. If a page/block is **duplicated or restored** to a new
  context, its saved colours do not follow and the chart reverts to the palette until
  re-set.
- Two identical charts in the **same** content field are disambiguated by render order.
