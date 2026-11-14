---
name: GSAP
description: Use GSAP (https://gsap.com) animation library - install/configure core tweens and timelines, register and use plugins (ScrollTrigger, ScrollSmoother, SplitText, Flip, Draggable, etc.), easing, and integrate with React/frameworks.
---

# GSAP (GreenSock Animation Platform)

GSAP (https://gsap.com) is a robust JavaScript animation library for animating CSS, SVG, canvas, and anything JS can touch.
- Docs: https://gsap.com/docs/v3/ | Install: https://gsap.com/docs/v3/Installation/
- Learning/resources: https://gsap.com/resources/ | Demos: https://demos.gsap.com/ | Cheatsheet: https://gsap.com/cheatsheet
- React guide: https://gsap.com/resources/React | AI skills repo: https://github.com/greensock/gsap-skills

## Installation

```sh
npm install gsap
# or yarn add gsap / pnpm add gsap
```

```js
import gsap from "gsap";
```

CDN/build options (UMD/ESM) and downloadable zip are available via the docs install helper. GSAP and plugins are now freely available on npm (v3.13+); migrate away from legacy private npm repo config if present.

## Core concepts

- `gsap.to(targets, vars)` / `gsap.from()` / `gsap.fromTo()`: tweens for animating properties over time.
- `gsap.timeline(vars?)`: sequence/choreograph tweens with precise timing, controls, labels, nesting.
- Targets: selectors, elements, arrays/NodeLists; animates CSS properties, attributes, numeric values, etc.
- Controls/callbacks: play/pause/reverse/seek, `onStart/onUpdate/onComplete`, repeat/yoyo, etc.
- Eases: `"none"`, `"power1-4"`, `"back"`, `"bounce"`, `"circ"`, `"elastic"`, `"expo"`, `"sine"`, `steps(n)`, and custom eases.
- Utilities: staggers, keyframes, `gsap.utils.*` (clamp, mapRange, random, wrap, toArray, etc.), `gsap.context()`/`revert()` for cleanup, `gsap.matchMedia()` for responsive/accessible animations, ticker.

## Plugins

Register plugins before use: `gsap.registerPlugin(PluginA, PluginB)` (safe to call multiple times).

Common plugins:
- **Scroll:** ScrollTrigger (scroll-linked animations/pinning/scrub), ScrollToPlugin, ScrollSmoother (requires ScrollTrigger).
- **Text:** SplitText, ScrambleTextPlugin, TextPlugin.
- **SVG:** DrawSVGPlugin, MorphSVGPlugin, MotionPathPlugin, MotionPathHelper.
- **UI:** Flip, Draggable, InertiaPlugin, Observer.
- **Other/Eases:** GSDevTools, Physics2D/PhysicsProps, Pixi/Easel, CustomEase, RoughEase/SlowMo/ExpoScaleEase, CustomWiggle/CustomBounce.

Import from package paths as documented (e.g. `import { ScrollTrigger } from "gsap/ScrollTrigger"`), then register.

## React / frameworks

- Use `useGSAP()` hook from `@gsap/react` (or per current docs) for scoped setup/cleanup in React.
- Use `gsap.context()` to scope selectors and `revert()` on unmount/cleanup to avoid leaks and duplicate animations (important in SPA/strict mode).
- GSAP is framework-agnostic; in SSR ensure animations run client-side after mount.

## Tips

- Prefer transforms/opacity for performance; batch/stagger where appropriate.
- For scroll effects, wire ScrollTrigger after layout is ready and refresh on dynamic content/resize as needed.
- Check exact plugin availability, import path, and API in https://gsap.com/docs/v3/Plugins/ for the specific plugin/version used.
- When integrating with smooth scrolling (e.g. Lenis), follow GSAP/ScrollerProxy or library-specific sync guidance so ScrollTrigger measurements stay correct.
