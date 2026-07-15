---
name: warmcache
description: "Pings this session periodically to keep its large prompt cache warm, avoiding an expensive cache rewrite when you come back later. Invoke manually with /warmcache when the current session has a large (>200K token) context you plan to resume within the next few hours and are about to leave idle. Never invoke this automatically or proactively — it must be an explicit user request."
---

# Warm Cache

## Why this exists

Anthropic's prompt cache expires roughly one hour after the last time it was
read or written. Reading a cached prefix (a cache hit) costs on the order of
10-20x less than rewriting it, and a hit also resets the TTL. This skill
exploits that: it schedules a trivial "ping" message back into this same
session every 55 minutes — just under the 1-hour TTL — so the cache never
goes cold, for up to ~4 hours by default. When the user actually returns,
they get a cheap cache hit instead of paying full price to rebuild a large
context from scratch.

## When to use it

Only run this when all of the following hold:

- The current session's context is large (roughly >200K tokens) — check the
  context/usage indicator before invoking.
- The user expects to come back to this exact session within the next few
  hours.
- The user is about to go idle (not actively sending more messages right
  now).

Do not invoke this speculatively, automatically, or "just in case." Every
ping has a small real cost, and that cost is wasted if the session is never
resumed. This skill only ever runs because the user explicitly typed
`/warmcache`.

## How to run it

This skill is invoked two ways: a fresh start (typed by the user) and a
"continue" re-invocation (delivered later by the scheduler). Branch on
`args`.

### Start — `args` is empty

1. Default to interval=55 (minutes) and max_pings=4 (~3h40m total, matching
   the "up to 4 hours" budget). If the user passed
   `/warmcache <interval_minutes> <max_pings>`, use those instead.
2. Reply with one short line: how often it will ping, how many pings, the
   total window, and that it can be cancelled (see "Stopping early").
3. Call `send_later` (claude-code-remote MCP server) with
   `delay_minutes=<interval>` and
   `message="/warmcache continue 1 <interval> <max_pings>"`.
4. Stop. Don't do anything else — just wait for the scheduled message.

### Continue — `args` starts with `continue`

Args are `continue <n> <interval> <max_pings>`.

1. Reply with exactly `ok` and nothing else. The only job of this turn is to
   touch the cached context so the hit resets the TTL — keep output to an
   absolute minimum to keep the ping cheap.
2. If `n < max_pings`: call `send_later` with `delay_minutes=<interval>` and
   `message="/warmcache continue <n+1> <interval> <max_pings>"`.
3. If `n >= max_pings`: add one short line noting the keep-warm window has
   ended and no more pings are scheduled. Do not call `send_later` again.

## Stopping early

If the user asks to cancel the keep-warm loop before it finishes: call
`list_triggers`, find the pending one-shot Routine whose prompt starts with
`/warmcache continue`, and `delete_trigger` it. Confirm the
cancellation to the user.

## Caveats

- Requires the claude-code-remote MCP tools (`send_later`, `list_triggers`,
  `delete_trigger`) — available in Claude Code on the web / remote sessions,
  not in a local CLI session without that connector.
- Each ping is a real, small API cost — this is insurance, not free. It only
  pays off if the session is actually resumed later.
- This skill does not measure context size itself; the invoking user decides
  whether the session is big enough to be worth it.
