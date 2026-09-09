---
name: Conversion Checkout System
colors:
  surface: '#faf8ff'
  surface-dim: '#d2d9f4'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f3ff'
  surface-container: '#eaedff'
  surface-container-high: '#e2e7ff'
  surface-container-highest: '#dae2fd'
  on-surface: '#131b2e'
  on-surface-variant: '#3d4a42'
  inverse-surface: '#283044'
  inverse-on-surface: '#eef0ff'
  outline: '#6d7a72'
  outline-variant: '#bccac0'
  surface-tint: '#006c4a'
  primary: '#006948'
  on-primary: '#ffffff'
  primary-container: '#00855d'
  on-primary-container: '#f5fff7'
  inverse-primary: '#68dba9'
  secondary: '#4b41e1'
  on-secondary: '#ffffff'
  secondary-container: '#645efb'
  on-secondary-container: '#fffbff'
  tertiary: '#006194'
  on-tertiary: '#ffffff'
  tertiary-container: '#007bb9'
  on-tertiary-container: '#fdfcff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#85f8c4'
  primary-fixed-dim: '#68dba9'
  on-primary-fixed: '#002114'
  on-primary-fixed-variant: '#005137'
  secondary-fixed: '#e2dfff'
  secondary-fixed-dim: '#c3c0ff'
  on-secondary-fixed: '#0f0069'
  on-secondary-fixed-variant: '#3323cc'
  tertiary-fixed: '#cce5ff'
  tertiary-fixed-dim: '#93ccff'
  on-tertiary-fixed: '#001d31'
  on-tertiary-fixed-variant: '#004b73'
  background: '#faf8ff'
  on-background: '#131b2e'
  surface-variant: '#dae2fd'
typography:
  display-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.015em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '600'
    lineHeight: 24px
    letterSpacing: -0.005em
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Inter
    fontSize: 11px
    fontWeight: '500'
    lineHeight: 14px
    letterSpacing: 0.03em
  numeric-currency:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 34px
    letterSpacing: -0.02em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit-2xs: 0.25rem
  unit-xs: 0.5rem
  unit-sm: 0.75rem
  unit-md: 1rem
  unit-lg: 1.5rem
  unit-xl: 2rem
  unit-2xl: 2.5rem
  unit-3xl: 3rem
  input-height: 3.125rem
  button-height: 3.25rem
  container-max: 68rem
  gutter-desktop: 2rem
  gutter-mobile: 1rem
---

## Brand & Style

This design system delivers a distraction-free, hyper-focused transaction experience tailored specifically for high-ticket and modular Brazilian online educational products. It combines the rigorous visual credibility of modern fintech platforms with the polished precision of developer-first SaaS interfaces.

### Core Philosophy
- **Frictionless Reassurance:** Eliminate checkout anxiety through instant visual clarity, robust structure, and immediate tactile feedback.
- **Transactional Precision:** Every interface pixel is dedicated to guiding the student through customer identification, payment method selection (Pix, multi-card credit processing, boleto bancário), and final confirmation.
- **Zero Friction & Zero Fluff:** Non-essential navigation, secondary marketing banners, social icons, and extraneous footer elements are stripped away completely.

### Aesthetic Direction
- **Style:** Modern SaaS meets High-Trust Fintech.
- **Atmosphere:** Controlled, ultra-reliable, crisp, and executive.
- **Emotional Signature:** Institutional security, immediate comprehension, effortless completion.

## Colors

The palette is engineered for high trust, legibility, and unmistakable visual validation under Brazilian e-commerce dynamics.

### Palette Architecture
- **Primary (`#059669` / `#10b981`):** Represents absolute trust, purchase confirmation, and security. Used for primary conversion triggers, final authorization CTAs, Pix dynamic copy confirmations, and security state badges.
- **Secondary (`#4f46e5`):** Expresses technical precision. Applied to subtle system active states, interactive toggles, active tab selectors, and focused inputs.
- **Neutral Core:**
  - Background Canvas: Slate Gray 50 (`#f8fafc`) to provide an understated structural baseline that isolates white cards.
  - Surface Elevation: Pure Crisp White (`#ffffff`).
  - Dividers & Outlines: Slate Gray 200 (`#e2e8f0`) with subtle sub-borders in Slate Gray 100 (`#f1f5f9`).
  - High-Contrast Typography: Deep Slate Navy 900 (`#0f172a`) for headlines and numerical totals; Slate 700 (`#334155`) for structural labels and descriptive text; Slate 400 (`#94a3b8`) for inactive placeholders.
- **Functional Semantics:**
  - Alert / Urgency: Rose 600 (`#e11d48`) for inline validation failures and card decline state.
  - Warning: Amber 500 (`#f59e0b`) for boleto clearance delays or expiring Pix timer countdowns.

## Typography

The typographical pairing relies on **Plus Jakarta Sans** for expressive, geometric, and authoritative structural headers, while **Inter** executes dense form fields, financial values, and dynamic checkout validation with surgical clarity.

### Implementation Principles
- **Tabular Figures for Financials:** Always configure `font-feature-settings: "tnum" 1` on price breakdown values, installment options, and timer counts to eliminate visual jumping during real-time updates.
- **Optical Hierarchy:** Never use font sizes smaller than 11px. Form input labels default to `label-lg` or `label-md` to maintain effortless scannability on small screens.
- **Brazilian Currency Treatment:** Format currency (`R$`) with the symbol positioned in regular or medium weight alongside a bold tabular integer figure to emphasize value clarity.

## Layout & Spacing

The checkout operates on a strict 2-column asymmetric desktop architecture switching into an optimized single-column flow for mobile screens.

### Grid Anatomy
- **Desktop (min-width: 1024px):** A constrained 68rem (`1088px`) wrapper split 7:5.
  - **Left Primary Column (~58% width):** Checkout stages grouped into distinct sequential blocks (Identification, Address/Billing, Payment selection).
  - **Right Sticky Column (~42% width):** Order summary, itemized inclusions, payment guarantees, and live total calculations.
- **Mobile / Tablet (< 1024px):** Single stack layout.
  - An abbreviated expandable order review bar anchors the top view.
  - Main transaction cards follow standard top-to-bottom cognitive order.
  - Final CTA stays permanently reachable with optional sticky bottom navigation for mobile payment submission.

### Spacing Engine
- Based on an 8px base rhythm with 4px sub-increments for compact inputs.
- Form fields maintain 50px (`3.125rem`) fixed heights to ensure ergonomic tap targets, exceeding standard finger-touch guidelines on mobile screens.
- Card sections use `2rem` (`32px`) internal padding on desktop and `1.25rem` (`20px`) on mobile devices.

## Elevation & Depth

This system avoids heavy, muddy drop shadows in favor of crisp surfaces bounded by light structural borders and whisper-soft ambient diffusion.

### Elevation Hierarchy
- **Canvas Base (Level 0):** `#f8fafc` — Background upon which all interactive surfaces float.
- **Card Surface (Level 1):** Pure `#ffffff` framed with a 1px solid border (`#e2e8f0`) and an ambient shadow:
  - `box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.03)`
- **Selected Surface / Active Method (Level 2):** Elevated interactive state for active payment methods (e.g., Selected Pix or Active Credit Card tab):
  - `box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`
  - Border transitions to `1.5px solid #059669` or `#4f46e5`.
- **Modals / Sticky Bars (Level 3):**
  - `box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.03)`
  - Used for dynamic Pix QR-Code overlay displays and security guarantee details.

## Shapes

The interface balances soft modern ergonomics with structural balance. 

### Geometric Rules
- **Checkout Containers & Main Cards:** `rounded-xl` (14px to 16px). This rounds the outer perimeter enough to feel approachable while anchoring the financial layout firmly.
- **Form Controls & Inputs:** 10px to 12px corner radius. Matches the internal curvature ratio of the parent cards.
- **Primary & Secondary Action Buttons:** 10px to 12px corner radius to harmonize with adjacent inputs.
- **Badges, Tags, and Pill Counters:** Fully rounded (`rounded-full` / 9999px) to contrast against rectilinear data entries and indicate instant status (e.g., "Acesso Imediato", "Pix com 10% OFF").

## Components

### Form Inputs & Selects
- **Height & Sizing:** Fixed 50px (`3.125rem`) vertical height with internal horizontal padding of 16px.
- **Resting State:** White background (`#ffffff`), 1px border in `#e2e8f0`, text in `#0f172a`, placeholder in `#94a3b8`.
- **Focus State:** 1px border in `#4f46e5`, accompanied by a smooth focus ring: `box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12)`.
- **Error State:** 1px border in `#e11d48`, ring: `box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12)`. Helper text is positioned directly below in 12px semibold `#e11d48`.
- **Specialized Brazilian Inputs:** Native formatting masks for CPF/CNPJ (`000.000.000-00`), Phone with DDD (`(11) 90000-0000`), and Credit Card expiry/CVV.

### Primary Conversion Action (CTA)
- **Visual Design:** Background `#059669` (hover: `#047857`, active: `#065f46`), text `#ffffff` (`label-lg`), height 52px, full-width.
- **Security Lock Decorator:** Embedded lock icon at 18px on the left edge with text: "Pagar e Garantir Acesso Imediato".
- **Focus / Keyboard Active:** `box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.2)`.

### Payment Method Selector Cards
- Grid of selectable card blocks: **Pix (Aprovado na Hora)**, **Cartão de Crédito**, and **Boleto Bancário**.
- Selected state features a background tint of `#f0fdf4`, an active border of `1.5px solid #059669`, and an emerald indicator badge. Unselected states sit at 1px `#e2e8f0` with neutral slate icons.

### Order Summary & Inclusions Card
- Anchored in the sticky right column on desktop.
- Displays high-resolution course thumbnail (72px square, rounded-lg), course title, access duration tag (e.g., "Acesso Vitalício"), clear breakdown of subtotal, tax/discounts, and bold final total featuring `numeric-currency`.
- Direct SSL trust badge with 256-bit encryption icon situated directly beneath the total amount.

### Installment (Parcelamento) Picker
- Enhanced dropdown or radio list highlighting installment breakdown (e.g., "12x de R$ 97,00").
- Visual indicator showing tax-free installments ("Sem Juros") in `#059669` bold label tags.

### Micro-Components (Badges & Guarantee Seal)
- **Guarantee Seal:** Crisp 2-line layout paired with an emerald icon: "Garantia incondicional de 7 dias — Devolução integral em 1 clique".
- **Pix Instant Badge:** Pill badge using background `#ecfdf5`, text `#065f46`, text: "⚡ Aprovação Instantânea".