---
name: doc-scaffold
description: Draft documentation for R scripts into a project-docs document, in this Claude Code session (with the user's own Claude plan), from the scripts' source and the user's notes on how they are run, with the document as context. The drafts arrive as suggestions in the editor's AI review panel. Use when the user asks to document, scaffold or draft docs for R scripts, or runs /doc-scaffold.
argument-hint: <document-slug> --source=<file-or-folder> [--entry=main.R] [--block=<topic-id>] [--run: <how the scripts are run>] | --review=<id>
---

# Documentation from R scripts in Claude Code

You draft the documentation yourself; the app only prepares the work and checks your answers. Nothing is applied
to the document: the drafts appear in the editor's **AI review** panel, where an admin accepts or dismisses each
one. **Never run, source, lint or test the R scripts**, and don't judge whether they work: they are only read as
text.

Arguments: `$ARGUMENTS`

## 1. Export

- **From the editor** (`--review=<id>`, the command the AI review panel shows after an upload): the scripts and
  run notes are already stored. Run:

  ```
  php artisan prose-review:scaffold-export --review=<id>
  ```

- **From disk**: `--source` takes `.R` files or folders (only their `.R` files are read, with their paths
  inside the folder), and may be repeated. `--entry` names the script that is opened first.
  - **Run notes.** Everything after `--run:` in the arguments says how the scripts are run (interactively:
    which script to open, what to set, what to run in which order). Never put it in a shell command. Write it,
    word for word, to `storage/app/prose-reviews/run-notes.txt` with your file-writing tool and pass
    `--run-notes-file=storage/app/prose-reviews/run-notes.txt`. If the user described how the scripts are run
    in their message instead, that is the run notes too. With no run notes, ask the user how the scripts are
    run before exporting; continue without only if they say so.
  - `--block` drafts into one primary or secondary topic instead of the document's top level.

  ```
  php artisan prose-review:scaffold-export <document-slug> --source=<path> [--source=<path>] [--entry=main.R] [--block=<id>] [--run-notes-file=<path>]
  ```

- No slug given, or it isn't found: list documents with
  `php artisan tinker --execute="dump(App\Models\Document::pluck('title', 'slug')->all())"` and ask which one.

It prints the review id, the folder, the instructions file and each call file with the answer file to write, and
warns about any secret it redacted. Tell the user about redactions.

## 2. Draft

1. Read `instructions.md` completely before anything else. It holds the style guide, the documentation
   template, the block types and the answer format. Follow it exactly; it is the same brief the API path gets.
2. For each `call-N.md`, read it and write your answer to the path it names (`call-N.json`): one JSON object
   with `sections`, `expand` and `script_topics` lists and nothing else.
   - Work only from the call files: the scripts are in them, numbered by line. Don't open the original script
     files, the codebase or the web; the `sources` and `citations` must be truthful.
   - Copy R code into a CodeBlock exactly as it is in the script, and cite those lines.
   - Suggest only what the document is missing; add to existing sections instead of repeating them.
   - `blocks_json` is a string containing JSON.
3. With several calls, you may hand each call to a subagent with the instructions file, as long as every answer
   file gets written.

Write only the answer files (and the run notes file). Don't edit the document, the database, or any other file.

## 3. Import

```
php artisan prose-review:import <review-id>
```

- It refuses until every answer file exists and is a JSON object, and lists what's wrong; fix those files and
  run it again.
- It reports how many suggestions were saved and how many were discarded and why (for example R code that isn't
  in the scripts). Discarded ones are gone; there's no need to redo them unless the user asks.

Finish by telling the user how many suggestions are waiting and that they can go through them (or **Accept all**)
in the document's **AI review** panel; new sections land in the template's order.
