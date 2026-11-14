---
name: Lenis
description: Use Lenis (https://lenis.dev) smooth scroll library - install, configure, integrate with frameworks/GSAP, and troubleshoot smooth scrolling, anchors, and snap.
---

# Lenis

Lenis (https://lenis.dev) is a lightweight, dependency-free smooth scroll library by darkroom.engineering that runs on native scroll.
- Docs/README: https://github.com/darkroomengineering/lenis/blob/main/README.md
- Demo: https://lenis.darkroom.engineering/ | Showcase: https://lenis.dev/showcase
- Packages: `lenis` (core), `lenis/react`, `lenis/vue`, `lenis/snap`, Framer plugin: https://lenis.framer.website/

## Installation

```sh
npm i lenis
# or pnpm add lenis / yarn add lenis
```

```js
import Lenis from 'lenis'
```

Or via script (pin version as needed): `https://unpkg.com/lenis@<version>/dist/lenis.min.js`

## Basic setup

```js
import Lenis from 'lenis'
import 'lenis/dist/lenis.css' // recommended

const lenis = new Lenis({ autoRaf: true })

lenis.on('scroll', (e) => {
  // e includes scroll/progress/velocity/etc.
})
```

### Custom RAF loop

```js
const lenis = new Lenis()
function raf(time) {
  lenis.raf(time)
  requestAnimationFrame(raf)
}
requestAnimationFrame(raf)
```

### GSAP ScrollTrigger sync

```js
import gsap from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

const lenis = new Lenis()
lenis.on('scroll', ScrollTrigger.update)
gsap.ticker.add((time) => { lenis.raf(time * 1000) })
gsap.ticker.lagSmoothing(0)
```

## Common options

- `autoRaf` (bool): auto `requestAnimationFrame` loop.
- `duration`/`easing` or `lerp`: animation feel (`lerp` takes precedence).
- `orientation`: `vertical` | `horizontal`; `gestureOrientation`: `vertical` | `horizontal` | `both`.
- `anchors`: `true` or `ScrollToOptions` to enable smooth anchor links.
- `wrapper` / `content` / `eventsTarget`: custom scroll container/targets.
- `infinite`: infinite scrolling (needs `syncTouch: true` on touch).
- `autoResize`: auto size via ResizeObserver (else call `resize()`).
- `autoToggle`: start/stop based on wrapper overflow (requires recommended CSS).
- `prevent`: `(node) => boolean` to opt elements out of smoothing.
- `allowNestedScroll`: auto native scroll in nested scrollables (simpler but can cost perf; prefer `prevent`/data attrs if needed).
- `smoothWheel`, `syncTouch`, multipliers, `virtualScroll`, `stopInertiaOnNavigate`, etc. See README for full table.

## Key API

- `scrollTo(target, options)`: target can be number, selector/keyword (`top|bottom|left|right|start|end`), or element. Options: `offset`, `lerp`, `duration`, `easing`, `immediate`, `lock`, `force`, `onComplete`, `userData`.
- `start()` / `stop()`, `resize()`, `destroy()`, `on('scroll'|...)`.
- Props: `scroll/animatedScroll/actualScroll`, `progress`, `velocity`, `direction`, `isScrolling`, `isStopped`, `limit`, etc.

## Nested scroll & anchors

- Nested: use `allowNestedScroll: true` or attributes like `data-lenis-prevent`, `data-lenis-prevent-wheel|touch|vertical|horizontal` on scrollable elements (or `prevent` fn).
- Anchors: set `anchors: true` (or options) so hash links work with smooth scroll.

## Notes

- Lenis has no built-in CSS scroll-snap; use `lenis/snap` package if needed.
- Include recommended CSS (`lenis/dist/lenis.css`) to avoid layout/behavior issues.
- Respects `prefers-reduced-motion` by default (`respectReducedMotion`).
- Limitations: iframes don't forward wheel events; Safari fps caps; fixed elements can lag on older Safari; `syncTouch` quirks on iOS < 16.
- Troubleshoot: update Lenis, include CSS, ensure `autoRaf` or manual `raf`, verify element is scrollable without Lenis, and check GSAP integration.

When helping in a project, match framework/package choice (React/Vue/core/snap), confirm scroll container, and prefer minimal config changes.
