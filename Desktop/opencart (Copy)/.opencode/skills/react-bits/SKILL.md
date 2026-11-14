---
name: React Bits
description: Use React Bits (https://reactbits.dev) animated React components and backgrounds - find components, choose JS/TS and CSS/Tailwind variants, and install via shadcn CLI or by copying source.
---

# React Bits

React Bits (https://reactbits.dev) is a library of animated React components and backgrounds installed as source you own. Source: https://github.com/DavidHDev/react-bits

## When to use

- User wants animated React UI components/backgrounds/micro-interactions/text effects from React Bits.
- User asks to add/install a specific React Bits component (e.g. Split Text, Aurora, Dock).
- User needs help choosing a component or variant (JS/TS, plain CSS/Tailwind).

## Installation

### CLI (recommended)

React Bits uses the shadcn registry. Install a component with:

```sh
npx shadcn@latest add @react-bits/<ComponentName>-<Lang>-<Style>
```

- `<ComponentName>`: PascalCase component id, e.g. `SplitText`, `BlurText`, `Aurora`, `LiquidEther`.
- `<Lang>`: `JS` or `TS`
- `<Style>`: `CSS` or `TW` (Tailwind)

Examples:

```sh
npx shadcn@latest add @react-bits/SplitText-TS-TW
npx shadcn@latest add @react-bits/Aurora-JS-CSS
```

After install, import the component from the installed path in your React app and render it. Check the component's props on its docs page.

### Manual copy

Open the component page on reactbits.dev, use the **Code** tab, pick language and styling, copy the files into the project, and install any dependencies listed there.

## Browsing components

- Index: https://reactbits.dev/get-started/index
- Install guide: https://reactbits.dev/get-started/installation
- Categories: Text Animations (`/c/text-animations`), Animations (`/c/animations`), Components (`/c/components`), Backgrounds (`/c/backgrounds`), Micro Interactions (`/c/micro`)
- Component pages follow patterns like `/text-animations/split-text`, `/backgrounds/aurora`, `/components/dock`, `/micro/squish-switch`, `/animations/click-spark` (check the index/docs for the exact slug).

When the user asks for a specific effect, search the index or fetch the relevant component page to get usage, props, dependencies, and code variants.

## Notes

- Components are source-owned: tune values freely after installing.
- Match the project's stack (TypeScript/JavaScript, Tailwind/plain CSS) when choosing a variant.
- If adding to this OpenCart repo (mostly PHP), confirm the user has/needs a React frontend before installing React components.
