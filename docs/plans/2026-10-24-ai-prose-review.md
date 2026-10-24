# AI Prose Review (Laravel docs style): Implementation Plan

Admins ask Claude to review a document, or one topic in it, against a style guide based on the Laravel
documentation (laravel.com/docs). Claude returns two kinds of suggestions, which the admin accepts or dismisses one by one:

- **text**: a wording change at one exact place in a block (`field` + `path`), applied with `DocumentEditor::updateBlock()`.
- **restructure**: replace one or more neighbouring blocks with new blocks (e.g. a paragraph → BulletList,
  "Term: definition" bullets → ConceptList, a caveat → Alert, a long section → TertiaryTopics), applied as one
  `block.replace` revision so a single undo restores it.

Decisions: only admins can see or use reviews. Sonnet (`claude-sonnet-5-5`) is the default; admins can choose Opus
(`claude-opus-5-5`) per review. Restructure suggestions are part of v1.

## 0. Scaffolding

- Runner: host (no Sail). Commands: `php artisan …`, `composer …`, `npm …`.
- Branch: `feat/ai-prose-review` from `main` (or stack on `feat/document-builder` if it hasn't merged yet).
- `composer require laravel/ai`. Publish its config and read `vendor/laravel/ai` to confirm the exact names for agents,
  structured output and fakes before writing Task 3. The names below follow the SDK's documented shape.
- `.env.example`: `ANTHROPIC_API_KEY=`, `AI_REVIEW_MODEL=claude-sonnet-5-5`, `AI_REVIEW_OPUS_MODEL=claude-opus-5-5`.
- `config/ai-review.php`:
  - `models` => `['sonnet' => env(...), 'opus' => env(...)]`, `default` => `'sonnet'`
  - `max_chars_per_chunk` (~24k), `max_chars_per_review` (~200k), `rate_limit_per_hour` (10)
  - `style_guide` => `resource_path('ai/style-guides/laravel-docs.md')`

Commit: `chore(ai): install laravel/ai and add review config`

## 1. Style guide (content, no code)

`resources/ai/style-guides/laravel-docs.md`, written by hand from laravel.com/docs:

- Voice: second person ("you"), present tense, active voice, plain words, short sentences.
- Sections open with a one- or two-sentence intro that says what the reader can do; first section is often "Introduction".
- Headings: task-oriented, title case, gerund for how-tos ("Creating Controllers", "Defining Routes").
- Callouts: `Note` for helpful asides, `Warning` for things that break; one idea each.
- Lists for parallel items; numbered lists only for ordered steps; term/definition pairs as definition-style lists.
- Inline code for identifiers, commands, paths, config keys; never rephrase anything inside code.
- Examples follow the prose that introduces them; prose doesn't restate code line by line.
- A short "Mapping to this app's blocks" section: Note → Alert `info`, Warning → Alert `warn`, definition list → ConceptList,
  key/value reference → KeyValueTable, sub-sections → the next level down the topic ladder.
- 4–6 short before/after examples (written here, not copied from laravel.com).

Commit: `docs(ai): add Laravel docs style guide for prose review`

## 2. Data model

Migration `create_prose_reviews_tables`:

`prose_reviews`
- `id`, `document_id` (FK, cascade), `scope_block_id` (uuid, nullable = whole document)
- `requested_by` (FK users), `model` (string), `kinds` (json: `["text","restructure"]`)
- `status` (`queued|running|done|failed`), `error` (text, nullable)
- `document_seq` (unsigned int, the doc's seq when the review started)
- `input_tokens`, `output_tokens` (unsigned int, default 0), timestamps

`prose_suggestions`
- `id`, `review_id` (FK, cascade)
- `kind` (`text|restructure`), `severity` (`minor|major`), `rule` (string), `rationale` (text)
- `block_ids` (json: one id for text; the contiguous sibling ids for restructure)
- `block_versions` (json: id → version when suggested)
- text: `field` (string, nullable), `path` (string, nullable, dot path like `data.3.description`), `original` (text), `suggested` (text)
- restructure: `replacement` (json nodes, nullable), `parent_id` (uuid, nullable)
- `status` (`pending|accepted|dismissed|stale`), `resolved_by` (FK users, nullable), `resolved_at`, timestamps

Models `ProseReview` (status consts like `Export`, `toSummary()`), `ProseSuggestion` (`toArray` shape for the panel),
relations on `Document::proseReviews()`. Factories for both.

Commit: `feat(ai): add prose review and suggestion tables`

## 3. Extraction (pure logic, TDD)

`app/Ai/ProseExtractor.php`: given a document (and optional scope block), returns:

- `segments`: `[{n, block_id, type, field, path, text, version}]`, one per reviewable string, using this field map
  (keep it in one constant, `ProseExtractor::FIELDS`):

| Type | Paths |
|---|---|
| Primary/Secondary/Tertiary/QuaternaryTopic | `title` |
| TertiaryTitle, Placeholder | `text` |
| RichText | `html` |
| Alert | `title` |
| BulletList, NumberedList | `list.*` (recursive, sub-lists are nested arrays; skip the bold marker) |
| ConceptList | `list.*.value` (recursive via `children`) |
| ItemDescriptionTable | `itemName`, `descriptionName`, `data.*.description`, plus `data.*.item` when `mono` is false |
| KeyValueTable | `table.*.value` |
| ConversionDictionary | `data.*.description` |
| DataDictionary | `data.*.description` |
| TestScenarios | `data.*.details`, `data.*.remarks` |
| Image, SqlTable, FileTree | `description`; FileTree also `list.*.note` (recursive) |
| CodeBlock | `label` |

  Cells of kind `lines` can be a string or an array of strings: one segment per string, path ends with the index.
  HTML is turned into text with inline tags kept as short markers (`<code>`, `<strong>`, `<em>`, `<a href>`), so
  suggestions can keep them.
- `outline`: the compact tree for restructure context: `[{block_id, type, depth, parent_id, summary}]`, where summary is
  the first ~120 chars of its text (or the props for tables/lists, abbreviated).

`app/Ai/PropPath.php`: `get(array $props, string $path)` / `set(array $props, string $path, string $value)` for dot paths
into nested props (used by extraction and by applying text suggestions).

Tests (`tests/Unit/ProseExtractorTest.php`, `tests/Unit/PropPathTest.php`):
- every type in the table yields the expected paths; CodeBlock code, Mermaid, SqlTable data, TechStack, Breadcrumbs yield none
- nested list / concept / tree paths round-trip through `PropPath::set`
- `lines` cells as string vs array
- scope limits extraction to the block's subtree
- inline markup survives html → text → html

Commit: `feat(ai): extract reviewable prose and outline from documents`

## 4. Agent

`app/Ai/Agents/ProseReviewer.php` (an SDK agent with structured output):

- Instructions = the style guide + fixed rules: never change technical meaning, identifiers, numbers, or anything in
  inline code; keep inline markers; quote `original` verbatim from the segment; propose restructures only where they
  clearly help; only use block types and nesting from the supplied block catalogue.
- Input per chunk: block catalogue (types, their props shape, `block-types.json` nesting), the outline, and numbered segments.
- Output schema:
  - `text[]`: `{segment, original, suggested, rule, rationale, severity}`
  - `restructure[]`: `{block_ids[], replacement[] (nodes: type, props, children), rule, rationale, severity}`
- Model picked from `config('ai-review.models')[$review->model]`. Mark the instructions block for prompt caching so
  chunks after the first reuse it.

Chunking: split segments by topic subtree so each chunk ≤ `max_chars_per_chunk`; always send the full outline.

## 5. Validation of AI output (TDD)

`app/Ai/SuggestionValidator.php`. Bad suggestions are dropped and logged with the reason, never saved:

text
- segment exists; `original` appears verbatim in its text; `suggested` ≠ `original`
- `suggested` sanitized to allowed inline tags only
- inline-code spans in `original` appear unchanged in `suggested`

restructure
- every `block_id` exists in scope, they're contiguous siblings under one parent, none already in another pending restructure
- `replacement` passes `DocumentTree::assertValidNodes($nodes, $parentType)`
- each node's props only use keys from that type's editing fields (`app/Ai/BlockCatalogue.php`, generated from
  `editing.js` field names into `resources/js/builder/block-fields.json`, the same way `block-types.json` is shared)
- RichText html sanitized; no CodeBlock/Mermaid/SqlTable content invented or changed. If the originals contain one,
  it must be carried over byte for byte.

Tests (`tests/Unit/SuggestionValidatorTest.php`): each rule has a pass and a fail case.

Commit: `feat(ai): validate prose suggestions before saving`

## 6. Job

`app/Jobs/ReviewProse.php`, shaped like `GeneratePdf` (`$tries = 1`, `failed()` marks the review failed):

1. status `running`, store `document_seq`
2. extract → chunk → for each chunk, prompt `ProseReviewer` → validate → insert suggestions with `block_versions`
3. add up token usage; status `done`
4. broadcast `ProseReviewUpdated` on the document's channel (admins only, see Task 8) after commit

Refuse (validation error at request time) when the scope's text exceeds `max_chars_per_review`.

Tests (`tests/Feature/ProseReviewJobTest.php`, SDK faked):
- valid fake output → suggestions saved, invalid ones dropped
- the prompt sent includes the style guide, outline and only the scope's segments
- multi-chunk document → one prompt per chunk, usage summed
- SDK throws → review `failed` with message

Commit: `feat(ai): queue prose reviews`

## 7. Applying suggestions

`DocumentEditor::replaceBlocks(Document, User, array $blockIds, array $versions, array $nodes): Revision`: one transaction:
lock doc, check every block's version (`StaleVersionException`), `locks->assertFree` on all subtrees, snapshot subtrees
for `before`, soft-delete them, `insertNodes` between the first's previous sibling and the last's next sibling, record op
`block.replace` with `before: {blocks: [...]}`, `after: {blocks: [...]}`.

Undo support: the frontend's history for `block.replace` = delete the inserted blocks + restore the removed ones. Add
`BlockController` handling (or a `POST /revisions/{revision}/undo` if that's simpler with the existing history code;
check `builder/editor/history.js` first and match how `block.insert`/`block.delete` undo today).

`app/Services/ProseReviews.php`:
- `accept(ProseSuggestion, User)`:
  - text: reload block; if version changed, re-read `PropPath::get` and accept only if `original` is still in it,
    else mark `stale` + 409. Replace the first occurrence of `original` with `suggested` → `updateBlock()` with the
    block's current version.
  - restructure: any version changed → `stale` + 409; else `replaceBlocks()`. Mark other pending suggestions on
    those blocks `stale`.
- `dismiss(ProseSuggestion, User)`
- `acceptAll(ProseReview, User, string $rule)`: text suggestions only, stops at first conflict, reports counts.

Tests (`tests/Feature/ProseSuggestionTest.php`):
- accept text → props changed at exactly that path, `block.update` revision, others untouched
- accept on a block edited elsewhere but `original` still present → applied
- accept with `original` gone → 409, `stale`
- accept restructure → old blocks soft-deleted, new ones in the same place, one `block.replace` revision
- restructure with a changed block → 409, `stale`
- locked block → refused like other writes
- undo of `block.replace` restores the original blocks in order

Commit: `feat(ai): apply text and restructure suggestions`

## 8. API, authorization, rate limit

Routes (`routes/api.php`, in the `auth:sanctum` group, all `->can('admin')`):

```
POST   /documents/{document}/reviews          ReviewController@store   (throttle:ai-review)
GET    /documents/{document}/reviews          ReviewController@index   (latest few)
GET    /reviews/{review}                      ReviewController@show
POST   /suggestions/{suggestion}/accept       SuggestionController@accept
POST   /suggestions/{suggestion}/dismiss      SuggestionController@dismiss
POST   /reviews/{review}/accept-rule          SuggestionController@acceptRule
```

- `StoreProseReviewRequest`: `blockId` (nullable, in this document), `kinds` (array, subset of text/restructure, default both),
  `model` (`in:sonnet,opus`, default from config).
- `RateLimiter::for('ai-review', …)` per user in `AppServiceProvider`.
- `ProseReviewUpdated` broadcasts on a separate private channel `documents.{id}.reviews` authorised for admins in
  `routes/channels.php`, so non-admin editors never receive suggestion data.

Tests (`tests/Feature/ProseReviewApiTest.php`): non-admin gets 403 on every route; store queues and returns 202; invalid
`blockId` from another document → 422; Opus choice stored; rate limit → 429; channel auth rejects non-admins.

Commit: `feat(ai): admin API for prose reviews`

## 9. Editor UI

- `builder/api.js`: review/suggestion calls.
- `builder/editor/useProseReview.js`: start review, poll `GET /reviews/{id}` (same pattern as `useExport.js`) and/or
  listen on the reviews channel; keep suggestions in state, mark stale on 409.
- Toolbar **Review prose** button + topic block "Review this section" (in the outline/properties menu), shown only when
  `auth.user.role === 'admin'`. Opens a small dialog: scope, kinds (wording / structure), model (Sonnet / Opus).
- `builder/editor/ProseReviewPanel.vue` (drawer like `HistoryDrawer.vue`):
  - grouped by block in document order; filter by kind, severity, rule
  - text: inline word diff of original → suggested, rule, rationale; Accept / Dismiss; "Accept all for this rule"
  - restructure: before/after preview rendering the real block components (`DocumentRenderer` on the replacement nodes)
  - clicking a suggestion → `scrollToBlock` + outline in `BlockCanvas.vue`
  - stale suggestions greyed out with "Text changed since review"
- After accepting, the existing realtime update refreshes the canvas; no local patching needed.

Tests (Vitest): diff rendering, grouping/ordering, stale state on 409, admin-only button, preview renders replacement nodes.

Commit: `feat(ai): prose review panel in the editor`

## 10. Quality gates

- `vendor/bin/pint`, `php artisan test`, `npm run lint`, `npm run test` all clean.
- Manual run with a real key on a sample document: one Sonnet review, one Opus section review; accept text, accept
  restructure, undo it.

## 11. Rollout and observability

- Log each review: document, scope, model, chunks, tokens, dropped-suggestion counts by reason.
- Tokens stored per review, so cost can be read off the table.
- `AI_REVIEW_ENABLED` env toggle: hides the button and returns 404 from the routes when off.
- Queue: reviews run on the existing worker (`composer prod` runs `queue:work`); set the job `$timeout` from config
  (chunks × per-call timeout).

## Implementation notes (where the build differs from the plan)

- **Text is reviewed as stored.** Segments send the block's inline HTML (`<b>`, `<codetext>`, links) unchanged instead
  of converting to plain text, so `original` matches the stored string exactly and nothing is lost on apply.
- **Short refs.** The model cites `B12` / `S40` instead of UUIDs; `ProseSnapshot` maps them back.
- **Replacements come back as a JSON string** (`replacement_json`): nodes nest, and the schema builder can't recurse.
  `{"keep": "B7"}` carries an existing block (code, images, query results) over unchanged; non-prose blocks must be
  kept this way or the restructure is dropped.
- **Block catalogue** lives in `resources/js/builder/ai-blocks.json`; a Vitest test keeps its prop lists equal to the
  editing forms in `editing.js`.
- **Undo of a restructure** swaps the result back for copies of the original blocks through
  `POST /documents/{document}/blocks/replace` (redo swaps again). The copies get new ids; props, options and anchors
  are kept, but per-block history starts again on them.
- **Own queue.** Reviews run on the `ai-review` queue connection (`retry_after` 1900s, job timeout 1680s) because one
  job makes several model calls; `composer dev` and `composer prod` start a worker for it.
- **Channel name** is `document.{id}.reviews`, alongside the existing `document.{id}` presence channel.
- **No factories** for the review models, matching the rest of the app (tests build documents through the API); a
  `UserFactory::admin()` state was added.
- **The SPA learns whether to show the feature** from `abilities.aiReview` on the user payload (admin and
  `AI_REVIEW_ENABLED`).

## Running reviews in Claude Code (added after the plan)

`AI_REVIEW_RUNNER=claude-code` (the default) runs reviews in Claude Code with the user's own Claude plan instead
of from the queue with an API key; a Pro/Max subscription can't back a multi-user server.

1. `/prose-review <slug> [--block=<id>] [--kinds=...]` (`.claude/skills/prose-review`) runs
   `php artisan prose-review:export`, which saves the block tree and its chunking on the review (`snapshot`,
   status `awaiting`) and writes `instructions.md` (the same brief `ProseReviewer` sends) and `chunk-N.md` to
   `storage/app/prose-reviews/<id>/`.
2. Claude Code reviews each chunk and writes `chunk-N.json` in the structured-output shape.
3. `php artisan prose-review:import <id>` validates every answer with `SuggestionValidator` against the saved
   snapshot (all or nothing), saves the suggestions and marks the review done.

The editor panel shows the command to run for the chosen scope instead of a Review button, and picks up the
review when it is exported and imported. `ReviewRun` holds the steps both runners share.

## Open items to confirm during Task 0

- Exact laravel/ai agent, structured output and fake APIs (read the installed package).
- How undo is wired in `history.js` today, to pick the simplest way to support `block.replace`.
