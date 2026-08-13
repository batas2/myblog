/**
 * The two diagrams in the post, as one model.
 *
 * Both pictures show the SAME five participants, the SAME interactions and, critically, the SAME
 * topology: Directory is the orchestrator, it blocks on Resolver, it blocks on Graph, and it emits
 * the event at the end. The first draft of this file got that wrong. The lane version had become a
 * fire-and-forget chain, which is a different architecture, and the comparison was then measuring a
 * redesign rather than a notation. Directory appears twice in the lane projection and carries an
 * activation band across the flow so the orchestration is impossible to miss.
 *
 * Palette is the site's. Every colour is checked against both themes (#fbfbfd and #0f1014) at
 * >= 4.16:1, so one transparent PNG serves light and dark.
 */

export const FONT = "Inter, -apple-system, system-ui, sans-serif";

/**
 * Two palettes, not one ink.
 *
 * The earlier version used a single colour set tuned to survive both a light and a dark page. That
 * is the right answer when you ship one file, and it costs you every colour: no hue clears 4.5:1
 * against both ends of that range, so the whole diagram sits near 4.2 and stays grey. The site
 * already renders `<name>-light.png` with a `<name>-dark.png` sibling behind a `<picture>`, so
 * there is no reason to pay that tax. Rendering twice buys saturated, semantic colour and every
 * value below clears AA against its own ground.
 *
 * The values are Stemma's own light/dark token pairs, lifted from its standalone report viewer
 * (src/report/viewer.css): indigo for the thing being added, rose for risk, amber for a person,
 * zinc for everything that already exists. Fills follow the same recipe the app uses, the hue at
 * 10-16% over the ground, so a box reads as a tinted card rather than a slab.
 */
const P = {
  light: {
    ground: '#fbfbfd',
    strong: '#18181b',       // --vr-text
    neutral: '#52525b',      // --vr-text-2, 7.48:1
    faint: '#a1a1aa',        // --vr-faint
    stroke: '#71717a',       // zinc-500. Existing geometry recedes so colour leads the eye.
    accent: '#4f46e5',       // --vr-accent, 6.08:1
    warn: '#be123c',         // --vr-rose, 6.08:1
    person: '#b45309',       // --vr-amber, 4.86:1
    fillNeutral: '#ffffff',  // --vr-node-bg
    fillAccent: 'rgba(99,102,241,0.12)',   // --vr-accent-soft
    fillWarn: 'rgba(225,29,72,0.10)',
    fillPerson: 'rgba(245,158,11,0.12)',
    rule: '#e4e4e7',         // --vr-border
    zebra: 'rgba(113,113,122,0.06)',
    band: 'rgba(82,82,91,0.45)',
  },
  dark: {
    ground: '#0f1014',
    strong: '#f4f4f5',       // --vr-text
    neutral: '#d4d4d8',      // --vr-text-2, 12.86:1
    faint: '#71717a',        // --vr-faint
    stroke: '#a1a1aa',       // zinc-400. Existing geometry recedes so colour leads the eye.
    accent: '#818cf8',       // --vr-accent, 6.37:1
    warn: '#fb7185',         // --vr-rose, 7.06:1
    person: '#fbbf24',       // --vr-amber, 11.39:1
    fillNeutral: '#18181b',  // --vr-node-bg
    fillAccent: 'rgba(129,140,248,0.16)',  // --vr-accent-soft
    fillWarn: 'rgba(251,113,133,0.14)',
    fillPerson: 'rgba(251,191,36,0.12)',
    rule: '#3f3f46',         // --vr-border
    zebra: 'rgba(161,161,170,0.05)',
    band: 'rgba(161,161,170,0.40)',
  },
};

/**
 * The hero is one file, not a pair: there is no <picture> around a post's hero image, so it has to
 * survive both grounds on its own. That is exactly the case the single dual-theme ink was designed
 * for, and it is why the hero is grey where the body figures are not.
 */
P.dual = {
  ground: null,
  strong: '#5F6170', neutral: '#767884', faint: '#8B8D99', stroke: '#767884',
  accent: '#6366e8',   // 4.42 light / 4.16 dark
  warn: '#B85C4A',     // 4.35 / 4.23
  person: '#AA7038',   // 4.00 / 4.60
  fillNeutral: 'rgba(118,120,132,0.10)',
  fillAccent: 'rgba(99,102,232,0.14)',
  fillWarn: 'rgba(184,92,74,0.12)',
  fillPerson: 'rgba(170,112,56,0.12)',
  rule: 'rgba(118,120,132,0.45)',
  zebra: 'rgba(118,120,132,0.05)',
  band: 'rgba(118,120,132,0.45)',
};

export const THEMES = ['light', 'dark'];

/** Resolves a tone name to its stroke, fill and text for one theme. */
export function tone(theme, name) {
  const p = P[theme];
  const map = {
    neutral: { stroke: p.stroke, fill: p.fillNeutral, text: p.strong },
    accent: { stroke: p.accent, fill: p.fillAccent, text: p.accent },
    warn: { stroke: p.warn, fill: p.fillWarn, text: p.warn },
    person: { stroke: p.person, fill: p.fillPerson, text: p.person },
  };
  return map[name] ?? map.neutral;
}

export const palette = theme => P[theme];

/**
 * The five participants, in call order. Team names only exist in the lane projection.
 *
 * The example is a relationship graph being built at scale: somebody names another organisation,
 * the platform has to decide whether that organisation is already a node, and only then may it
 * write the edge and recompute everything hanging off it. Chosen over the usual shop checkout
 * because the interesting failure here is not a timeout, it is identity: get it wrong and the graph
 * grows a second copy of a company that will never merge back.
 */
export const PARTICIPANTS = [
  { id: 'member', name: 'Member', team: null, actor: true },
  { id: 'directory', name: 'Directory', team: 'Discovery squad' },
  { id: 'resolver', name: 'Resolver', team: 'Identity squad' },
  { id: 'graph', name: 'Graph', team: 'Network squad' },
  { id: 'rollup', name: 'Aggregation', team: 'Insights squad' },
];

/**
 * The lane projection. `at` is the lane, `col` is the time column. Checkout owns columns 1 and 3
 * because it gets control back between the two calls, exactly as the activation bar shows in the
 * sequence projection.
 *
 * The bodies are the point of the notation: what a reviewer would otherwise have to ask about.
 */
export const STEPS = [
  {
    id: 's1', at: 'member', col: 0, tone: 'person',
    title: '1  Names a partner',
    detail: ['free text, a country,', 'sometimes a tax id'],
  },
  {
    id: 's2', at: 'directory', col: 1, tone: 'neutral',
    title: '2  Ask who this is',
    detail: ['resolve(candidate).', 'Directory blocks here.', 'No id in 40% of them'],
  },
  {
    id: 's3', at: 'resolver', col: 2, tone: 'warn',
    title: '3  Match, or mint',
    detail: ['Under the threshold we', 'mint a second company', 'that never merges back.'],
  },
  {
    id: 's4', at: 'directory', col: 3, tone: 'neutral',
    title: '4  Link the two',
    detail: ['linkOrgs(), once', 'identity came back'],
  },
  {
    id: 's5', at: 'graph', col: 4, tone: 'neutral',
    title: '5  Edge written',
    detail: ['1 edge into 40M nodes.', 'Fan-out is the cost.'],
  },
  {
    id: 's6', at: 'rollup', col: 5, tone: 'accent',
    title: '★ 6  OrgLinked',
    detail: ['every rollup above it', 'recomputed. New build.'],
  },
];

/**
 * Explicit edges rather than "consecutive boxes", so the returns to Checkout are real. `via`
 * routes an edge through the Checkout activation band, which is how the event at the end leaves
 * the orchestrator rather than appearing to leave Inventory.
 */
export const EDGES = [
  { from: 's1', to: 's2', kind: 'command' },
  { from: 's2', to: 's3', kind: 'command' },
  { from: 's3', to: 's4', kind: 'return' },
  { from: 's4', to: 's5', kind: 'command' },
  { from: 's5', to: 's6', kind: 'event', via: 'directory', tone: 'accent', label: '★ new leg' },
];

/** Directory holds the request open from its first call to the event it emits at the end. */
export const BAND = { at: 'directory', fromCol: 1, toCol: 5 };

/** The rationale a sequence diagram exiles to prose. In the lane diagram it stays on the page. */
export const NOTE = {
  title: 'Where this flow needs a conversation',
  body: [
    'Steps 3 and 4 cross a squad boundary with a confidence threshold in between. If Identity',
    'mints a new company for one the graph already holds, the duplicate is permanent and every',
    'rollup above it is wrong. Whose ticket that is gets agreed here, or it gets agreed in an incident.',
  ],
};

/** Printed on the picture, because the count is the reason the notation exists. */
export const CROSSINGS = '4 squads  ·  3 team handovers';

/**
 * The scorecard. Grouped rather than listed, because a flat table of ticks reads as a sales sheet
 * and this comparison has to survive a reader looking for the rigging. The last group is the point.
 *
 * `uml` / `lane` are 'full' | 'half' | 'none'.
 */
export const COMPARISON = [
  {
    group: 'Both notations carry it', rows: [
      { label: 'Order in time', uml: 'full', lane: 'full' },
      { label: 'Which participant acts', uml: 'full', lane: 'full' },
      { label: 'Whether anyone is waiting', uml: 'full', lane: 'full' },
      { label: 'What happens inside the step', uml: 'half', lane: 'full' },
    ],
  },
  {
    group: 'Only bands carry it, without a legend to learn', rows: [
      { label: 'Which team owns the participant', uml: 'none', lane: 'full' },
      { label: 'Where the flow can hurt you', uml: 'half', lane: 'full' },
      { label: 'Why it was designed this way', uml: 'half', lane: 'full' },
      { label: 'How many team handovers it needs', uml: 'none', lane: 'full' },
    ],
  },
  {
    group: 'Only lifelines carry it', rows: [
      { label: 'Exactly when control returns', uml: 'full', lane: 'half' },
      { label: 'Branching and concurrency (alt / loop / par)', uml: 'full', lane: 'none' },
      { label: 'Generated and diffed as text (PlantUML, Mermaid)', uml: 'full', lane: 'none' },
    ],
  },
];

/**
 * The sequence projection. Returns, activations and asynchronous arrowheads are all present,
 * because a strawman would not survive review: this is UML doing what UML is genuinely good at.
 */
export const MESSAGES = [
  { from: 'member', to: 'directory', label: 'addPartner(name)', style: 'call' },
  { from: 'directory', to: 'resolver', label: 'resolve(candidate)', style: 'call' },
  { from: 'resolver', to: 'directory', label: 'match | mint', style: 'return' },
  { from: 'directory', to: 'graph', label: 'linkOrgs(a, b)', style: 'call' },
  { from: 'graph', to: 'directory', label: 'edgeId', style: 'return' },
  { from: 'directory', to: 'rollup', label: 'OrgLinked', style: 'async' },
  { from: 'directory', to: 'member', label: 'pending', style: 'return' },
  { from: 'rollup', to: 'member', label: 'reach updated', style: 'async' },
];

export const SD_NAME = 'sd  add a partner organisation';
