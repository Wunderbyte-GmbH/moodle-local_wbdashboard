# local_wb_dashboard — Report Builder datasources

Besides consuming existing Report Builder reports, the plugin provides its own
Report Builder **datasources**. Reports built on them are regular custom reports:
they work with the `[chart]` / `[digits]` / `[chartfilter]` shortcodes through the
normal `source=reportbuilder` mechanism, including locked filters and per-report
access control.

## Active users (unique per month)

**Datasource:** *Active users (unique per month)*
(`local_wb_dashboard\reportbuilder\datasource\active_users`)

One row per **user per calendar month with at least one login** — a user logging
in three times in April yields a single "user / April" row. Counting rows per
month therefore counts **unique active users per month**; counting all rows after
a date filter counts *user-months*, not users.

Data comes from login events (`\core\event\user_loggedin`) in the **standard
logstore**, so:

- The **Standard log** store must be enabled (*Site administration → Plugins →
  Logging → Manage log stores*). Months where it was disabled show no data —
  nothing can backfill them.
- History is bounded by the store's retention (*"Keep logs for"* in the Standard
  log settings).
- Month boundaries follow the **database server's timezone**.
- Guest and deleted users are excluded. Supported databases: PostgreSQL,
  MySQL/MariaDB.

### Entities

- **Active month** (`active_month`) — columns: *Month* (displayed e.g. "April
  2026", sorts chronologically), *Logins in month*, *First/Last login in month*.
  Filters/conditions: *Month* (date), *Logins in month* (number), *Last login in
  month* (date).
- **User** — the full core user entity, including custom profile fields, so
  region-style filters work exactly as on a users-source report.

### Recipes

Unique active users per month (bar chart): report with column `active_month:month`
(sorted ascending), then

```
[chart type=bar source=reportbuilder report=<id> categoryfield=month aggregation=count]
```

Unique active users in one month: same report — the month is one bar of the chart
above; or add a date filter and read a single month's value with

```
[chartfilter key=month type=date label="From" pageid=...]
[digits source=reportbuilder display=count report=<id> consumes=month pageid=...]
```

(the digits then count user-months from the chosen date, so they equal unique
users only while a single month is in range).

Regional split: add the user *Region* profile field filter to the report and a
matching `[chartfilter key=region ...]` on the page.
