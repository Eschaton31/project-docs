---
name: prose-review
description: Run an AI prose review of a project-docs document in this Claude Code session (with the user's own Claude plan), in the style of the Laravel docs, and import the suggestions for admins to accept in the editor. Use when the user asks to review, proofread, restructure, expand or flesh out a document's prose or sections, or runs /prose-review <document-slug>.
argument-hint: <document-slug> [--block=<topic-block-id>] [--kinds=text,restructure,expand] [--brief: <what to cover>]
---

# Prose review in Claude Code

You review the document yourself; the app only prepares the work and checks your answers. Suggestions are not
applied to the document: they appear in the editor's **AI review** panel, where an admin accepts or dismisses
each one.

Arguments: `$ARGUMENTS`

## 1. Export

Run, passing the arguments through:

```
php artisan prose-review:export <document-slug> [--block=<id>] [--kinds=...] [--brief-file=<path>]
```

- `--kinds` is any of `text` (wording), `restructure` (block structure, including converting prose into tables,
  diagrams, file trees and the like, and editing those structures in place) and `expand` (add missing content,
  any block type), comma separated; without it, text and restructure.
- **Brief.** Everything after `--brief:` in the arguments is the admin's brief: what to cover when expanding, and
  facts the reviewer may use. Never put it in a shell command. Write it, word for word, to
  `storage/app/prose-reviews/brief.txt` with your file-writing tool and pass `--brief-file=storage/app/prose-reviews/brief.txt`.
  If the user asks to expand or flesh out sections and mentions what to add, that is the brief too. Add `expand`
  to `--kinds` when there is a brief.
- No slug given, or it isn't found: list documents with
  `php artisan tinker --execute="dump(App\Models\Document::pluck('title', 'slug')->all())"` and ask which one.
- `--block` reviews one topic (with everything inside it). The editor's AI review panel shows the exact command
  for the selected section.

It prints the review id, the folder, the instructions file and each chunk file with the answer file to write.

## 2. Review

1. Read `instructions.md` completely before anything else. It holds the style guide, the rules for text and
   restructure and expand suggestions, and the answer format. Follow it exactly; it is the same brief the API
   reviewer gets.
2. For each `chunk-N.md`, read it and write your answer to the path it names (`chunk-N.json`): one JSON object
   with `text`, `restructure` and `expand` lists and nothing else.
   - Copy every `original` from the segment character for character, HTML included.
   - `replacement_json` and `blocks_json` are strings containing JSON.
   - Expansions draw only on the chunk files, the brief and general knowledge, as the instructions say. Don't
     read the codebase or the web for them: the instructions' `sources` must be truthful, and the admin
     fact-checks anything marked `general`.
   - Fewer, well-founded suggestions beat many marginal ones. Empty lists are a fine answer.
3. With several chunks, you may hand each chunk to a subagent with the instructions file, as long as every
   answer file gets written.

Write only the answer files. Don't edit the document, the database, or any other file.

## 3. Import

```
php artisan prose-review:import <review-id>
```

- It refuses until every answer file exists and is a JSON object, and lists what's wrong; fix those files and
  run it again.
- It reports how many suggestions were saved and how many were discarded and why (for example an `original` that
  isn't in the text). Discarded ones are gone; there's no need to redo them unless the user asks.

Finish by telling the user how many suggestions are waiting and that they can go through them in the document's
**AI review** panel.
