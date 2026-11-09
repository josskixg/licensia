---
version: alpha
name: Licensia Geist Monochrome
description: Vercel Geist Monochrome cryptographic operator console with dark/light surface polarity and Ed25519 verification telemetry.
colors:
  primary: "#000000"
  surface-dark: "#0A0A0A"
  ink-light: "#EDEDED"
  accent-blue: "#0070F3"
  seal-emerald: "#10B981"
  danger-red: "#FF5B4F"
  surface-light: "#FFFFFF"
  subtle-light: "#FAFAFA"
  ink-dark: "#171717"
  muted: "#666666"
typography:
  display:
    fontFamily: Geist
    fontSize: 2.5rem
    fontWeight: 600
    lineHeight: 1.15
    letterSpacing: "-0.05em"
  h2:
    fontFamily: Geist
    fontSize: 1.25rem
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "-0.03em"
  body:
    fontFamily: Geist
    fontSize: 0.875rem
    fontWeight: 400
    lineHeight: 1.5
  mono:
    fontFamily: Geist Mono
    fontSize: 0.8125rem
    fontWeight: 500
    lineHeight: 1.5
    fontFeature: "tnum, liga"
rounded:
  sm: 4px
  md: 6px
  lg: 8px
  pill: 9999px
spacing:
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
components:
  button-primary:
    backgroundColor: "{colors.ink-light}"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    padding: 12px
  badge-valid:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.seal-emerald}"
    rounded: "{rounded.pill}"
    padding: 6px
  badge-tampered:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.danger-red}"
    rounded: "{rounded.pill}"
    padding: 6px
  panel-dark:
    backgroundColor: "{colors.surface-dark}"
    textColor: "{colors.ink-light}"
    rounded: "{rounded.lg}"
    padding: 16px
  panel-light:
    backgroundColor: "{colors.surface-light}"
    textColor: "{colors.ink-dark}"
    rounded: "{rounded.lg}"
    padding: 16px
---

## Overview

Licensia uses the **Vercel Geist Monochrome** design system tailored for a **Command / Inspect & Operate** cryptographic licensing surface. Every pixel serves signature generation, offline Ed25519 payload inspection, or seat telemetry. Dual theme polarity (`#000000` / `#0a0a0a` dark mode and `#ffffff` / `#fafafa` light mode) is persisted in `localStorage` and controlled via CSS custom properties.

## Colors

- **Primary (`#000000`) & Surface Dark (`#0A0A0A`):** True carbon canvas and elevated card surface in dark mode.
- **Ink Light (`#EDEDED`):** High-contrast foreground text on dark surfaces without harsh pure-white halation.
- **Accent Blue (`#0070F3`):** Interactive focus rings, cryptographic parameter links, and active operator states.
- **Seal Emerald (`#10B981`):** Reserved strictly for `VALID_CRYPTOGRAPHIC_SEAL` Ed25519 signature verification states.
- **Danger Red (`#FF5B4F`):** Reserved strictly for `TAMPERED_SIGNATURE`, expired certificates, or unauthorized domain/HWID bindings.
- **Surface Light (`#FFFFFF`) & Subtle Light (`#FAFAFA`):** Gallery-clean light mode surfaces with `#171717` ink.

## Typography

- **Geist Sans (`Inter` fallback):** Compressed negative tracking (`-0.05em` on headings, `-0.03em` on section titles) and three strict weights (`400`, `500`, `600`).
- **Geist Mono (`JetBrains Mono` fallback):** Mandatory `font-variant-numeric: tabular-nums` (`tnum`) across all Ed25519 public keys, `LIC-v1` payloads, seat counters, and SHA-256 domain hashes.

## Layout

- **Archetype:** Command / Inspect & Operate console.
- **Structure:** Sticky top operator bar with live keypair fingerprint and theme toggle, two-column asymmetric workspace (Key Issuer + Offline Tamper Sandbox on top; Active Seat Telemetry Ledger + 1-Click Laravel Verifier Snippet Exporter below).
- **Responsive Grid:** Collapses cleanly below `960px` and `640px` with `44px+` minimum touch targets on all interactive controls.

## Elevation & Depth

- **Shadow-as-Border:** Uses `box-shadow: 0 0 0 1px var(--border-color)` and crisp `1px solid` hairline dividers (`rgba(255,255,255,0.09)` in dark mode, `rgba(0,0,0,0.08)` in light mode). Zero blurry drop-shadows or decorative glassmorphism.

## Shapes

- **Radii:** `6px` for buttons and inputs, `8px` for operator panels, `9999px` strictly for cryptographic verification status pills.

## Components

- **Cryptographic Key Issuer:** Selects product tier (`Starter`, `Pro`, `Agency`), binds domain/HWID, sets expiration, and signs a canonical `LIC-v1.<payload_b64url>.<ed25519_sig_b64url>` certificate.
- **Tamper & Offline Verifier Sandbox:** Live byte-level inspector that parses `LIC-v1` certificates, decodes the JSON claims, and verifies the 64-byte signature in real time—flagging `TAMPERED_SIGNATURE` immediately if a single character is modified.
- **PHP/Laravel Snippet Exporter:** Generates a zero-dependency PHP 8.2 `sodium_crypto_sign_verify_detached()` verifier class with the current public key embedded.

## Do's and Don'ts

- **Do** use `tabular-nums` on every cryptographic hash, timestamp, and seat metric.
- **Do** reserve `#10B981` and `#FF5B4F` exclusively for cryptographic verification outcomes.
- **Don't** add marketing hero banners, 3-column feature grids, or decorative gradients.
