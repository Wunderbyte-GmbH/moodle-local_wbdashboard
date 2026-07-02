# local_wb_dashboard — architecture

Generic, shortcode-driven chart engine. One shortcode renders any supported chart
type from any registered data source; a second shortcode renders page-level filters
that every chart on the page reacts to.

## Patterns

- **Builder** — `chart_director` selects a concrete `chart_builder`
  (`doughnut_chart_builder`, `bar_chart_builder`) that assembles the **complete**,
  Chart.js-ready `chart_config` in PHP. The JS is a thin runtime: it instantiates
  the config and wires JS-only plugins (center-text) by name — it builds no config.
- **Factory** — `filter_factory` creates filter controls; `source_registry` is the
  internal source factory/allowlist.
- **DTO** — `chart_data` (+ `chart_series`) is the normalized shape every source
  produces and the builder consumes. `filter_constraint` is the neutral, source-
  agnostic expression of a filter value.
- **Definitions (drag-and-drop seam)** — `chart_definition` / `filter_definition`
  fully describe a chart/filter. The shortcode is one producer today; a future
  DB-backed drag-and-drop builder is another, feeding the same pipeline.

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
