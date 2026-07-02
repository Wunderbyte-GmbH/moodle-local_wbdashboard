# local_wb_dashboard — architecture

Generic, shortcode-driven dashboard engine. One shortcode renders any supported
chart type from any registered data source; a second renders a single value
(number/count/percentage) from the same sources; a third renders page-level filters
that every chart and value on the page reacts to.

## Patterns

- **Builder** — `chart_director` selects a concrete `chart_builder`
  (`doughnut_chart_builder`, `bar_chart_builder`) that assembles the **complete**,
  Chart.js-ready `chart_config` in PHP. The JS is a thin runtime: it instantiates
  the config and wires JS-only plugins (center-text) by name — it builds no config.
- **Shared data pipeline** — `local\source\pipeline::fetch()` is the single
  server-side path from a definition to data: resolve the source, allowlist its
  params, enforce object-level access, translate page filters into neutral
  constraints and return the `chart_data` DTO. Every display web service
  (`get_chart_data`, `get_digits_data`) runs this identical pipeline; only what it
  does with the DTO differs.
- **Reducer** — for single-value fields, `local\digits\digits_reducer` collapses the
  same `chart_data` DTO to one `digits_result` (a number = sum of the series, or a
  percentage = base ÷ total from the two-report delta's `axismax`). The digits JS is
  a thin runtime like the chart one, but writes DOM text (via `textContent`) instead
  of drawing a canvas.
- **Factory** — `filter_factory` creates filter controls; `source_registry` is the
  internal source factory/allowlist.
- **DTO** — `chart_data` (+ `chart_series`) is the normalized shape every source
  produces; the chart builder and the digits reducer both consume it.
  `filter_constraint` is the neutral, source-agnostic expression of a filter value.
- **Definitions (drag-and-drop seam)** — `chart_definition` / `digits_definition` /
  `filter_definition` fully describe a chart/value/filter. The shortcode is one
  producer today; a future DB-backed drag-and-drop builder is another, feeding the
  same pipeline. `digits_definition` also derives a **deterministic, constant DOM id**
  from its configuration so the rendered value can be targeted from CSS.

## Filters

Filters are page-scoped and **source-native**: a filter emits a neutral
`filter_constraint`; each source applies the ones it recognises in its own way
(the Report Builder source maps the key to the report's own filter). Shared state
lives in the URL (canonical) with a per-user MUC cache (`page_filter_state`) as the
persistence fallback; the `filterbus` JS singleton owns it and fans changes out to
every subscribed chart.

## Component / data flow — first render

```mermaid
flowchart TD
  SC["[chart] / [chartfilter] shortcode"] --> DEF["chart_definition / filter_definition<br/>(DnD seam: shortcode is one producer)"]
  DEF --> TPL["chart.mustache + chartfilter.mustache<br/>canvas + data-wsargs + filter controls"]
  TPL --> AMD["amd/src/chart.js init()"]
  AMD --> BUS["filterbus.js (page singleton)<br/>state = URL &rarr; MUC cache"]
  BUS --> WS["WS local_wb_dashboard_get_chart_data<br/>(definition + filtervalues)"]
  WS --> REG["source_registry::get(source)  (Factory)"]
  REG --> ACC["source-&gt;require_access()  (per-object authz)"]
  ACC --> APPLY["source-&gt;fetch(params, constraints)<br/>applies filters NATIVELY"]
  APPLY --> RB["reportbuilder: report's own filters"]
  APPLY --> WB["wb_table: wb_table filter API (future)"]
  APPLY --> SQL["sql: parameterized WHERE (future)"]
  RB --> DTO["chart_data DTO (normalized)"]
  WB --> DTO
  SQL --> DTO
  DTO --> BUILD["chart_director-&gt;build(type, dto, opts)  (Builder, PHP)<br/>concrete builder assembles FULL chart_config (sanitized JSON)<br/>doughnut | bar | horizontalbar | stackedbar | progress"]
  BUILD --> DRAW["chart.js (thin): new Chart(canvas, config)<br/>+ wire JS-only plugins by name (destroy prior first)"]
```

## Page-level filter change (fan-out to all charts)

```mermaid
sequenceDiagram
  participant U as User
  participant F as chartfilter control
  participant B as filterbus (page singleton)
  participant URL as URL + MUC cache
  participant C1 as chart A
  participant C2 as chart B
  participant WS as get_chart_data WS
  U->>F: change filter (e.g. period)
  F->>B: notify(key=period, value)
  B->>URL: update URL (replaceState) + set_filter_state (cache)
  B->>C1: re-query(mergedValues intersect consumes)
  B->>C2: re-query(mergedValues intersect consumes)
  C1->>WS: definition + filtervalues
  C2->>WS: definition + filtervalues
  WS-->>C1: payload JSON
  WS-->>C2: payload JSON
  Note over C1,C2: each: destroy prior chart, redraw (stale-response token guards races)
```

## Supported chart types (v1)

| Semantic type   | Concrete builder + configuration                                      |
|-----------------|-----------------------------------------------------------------------|
| `doughnut`      | `doughnut_chart_builder` — cutout + center-text plugin                |
| `bar`           | `bar_chart_builder` (vertical)                                        |
| `horizontalbar` | `bar_chart_builder` + indexAxis 'y'                                   |
| `stackedbar`    | `bar_chart_builder` + stacked scales, per-dataset stack groups        |
| `progress`      | `bar_chart_builder` horizontal + stacked + fixed axis max             |

## Single-value fields (digits)

The `[digits]` shortcode is the non-canvas display component. It shares the source
layer and filter behaviour with charts but renders one value as DOM text.

```mermaid
flowchart TD
  SC["[digits] shortcode"] --> DEF["digits_definition<br/>(reserved keys + deterministic DOM id)"]
  DEF --> TPL["digits.mustache<br/>&lt;div id=constant&gt; value + label + data-wsargs"]
  TPL --> AMD["amd/src/digits.js init()"]
  AMD --> BUS["filterbus.js (page singleton)"]
  BUS --> WS["WS local_wb_dashboard_get_digits_data"]
  WS --> PIPE["source\\pipeline::fetch()  (shared)<br/>resolve + authz + filters &rarr; chart_data DTO"]
  PIPE --> RED["digits\\digits_reducer::reduce(dto, mode)<br/>number/count = sum · percent = base/total"]
  RED --> FMT["format_float + unit/%  &rarr; {value, formatted, ispercent, label}"]
  FMT --> DOM["digits.js (thin): textContent into the constant-id nodes"]
```

| `display` mode | Reduction of the DTO |
|----------------|----------------------|
| `number` / `count` | Sum of the first series' data points (parts of one whole). |
| `percent` | `base ÷ total × 100`. base = first data point; total = `axismax` meta (delta), else the second data point (two-report part/whole ratio), else base. Divide-by-zero &rarr; 0. |
