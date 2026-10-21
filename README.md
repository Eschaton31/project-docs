# Project Docs

A web app for writing technical documentation and user manuals that all share one consistent look.

Instead of styling each document by hand, you build it from a fixed set of components: title page,
numbered topics, paragraphs, alerts, code blocks, diagrams, lists and tables. Every document comes out
with the same typography, numbering, table of contents, running title and page layout. It is paginated
for print with [Paged.js](https://pagedjs.org/) and exported as a PDF that is ready to submit.

## What it does

- **Consistent by construction.** Documents are trees of blocks (topics, text, code, tables, lists,
  images…), each rendered by the same component everywhere. Section numbering, the table of contents and
  page breaks are handled for you.
- **Editor.**
    - An outline with drag and drop, a live view of the page, and a properties panel for each block.
    - Rich text with inline code and links, a code editor with syntax highlighting, spreadsheet-style table
      grids (paste from Excel, Google Sheets or a SQL client), and an image picker with uploads into folders.
- **Edit in place.** Click a block on the page again to edit its text, table cells or list items right
  where they are.
- **Outline mode.** Paste an indented list of headings to sketch a document's structure in one go.
- **Collaboration.** Several people can edit the same document at once. You see who is here and which
  block each person is editing, and everyone's changes appear live. A block being edited by someone else
  is read-only for everyone else.
- **History.**
    - Per-block history with restore.
    - A document activity feed, from which deleted blocks can be brought back.
    - Undo and redo.
- **Published versions.** Freeze a document under a label (e.g. "v1.0 submitted") together with its PDF.
  Later edits don't change it.
- **Print and PDF.**
    - A print preview with draft and final modes (final leaves out draft blocks and empty topics).
    - One-click PDF export, rendered on the server in headless Chrome.
    - A browser print option.
- **Import and export.** Any document, or a published version, can be exported as JSON and imported again
  as a new document.

## Tech stack

| Part      | Technology                                                                   |
|-----------|------------------------------------------------------------------------------|
| Backend   | Laravel 13 (PHP 8.3), MariaDB, Sanctum (session auth), Reverb (WebSockets)   |
| Frontend  | Vue 3, Vue Router, Pinia, Tailwind CSS 3, Vite 7                             |
| Editing   | TipTap (rich text), CodeMirror 6 (code)                                      |
| Print/PDF | Paged.js, Prism, Mermaid; Browsershot + Puppeteer (headless Chrome) for PDFs |
| Tests     | PHPUnit (backend), Vitest + Vue Test Utils + happy-dom (frontend)            |

## Requirements

- PHP 8.3+ and Composer
- Node.js `^20.19` or `>=22.12` and npm
- MariaDB (or MySQL)
- The Font Awesome Pro packages need the registry token in `.npmrc`

`npm install` also downloads the Chrome build that Puppeteer uses for PDF export.

## Getting started

```sh
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan reverb:install      # writes the REVERB_* keys to .env
```

Set the database connection in `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), then:

```sh
php artisan migrate
php artisan app:create-user     # once per person; there is no self-registration
composer dev                    # web server, queue worker, Reverb and Vite together
```

Open <http://localhost:8000> and sign in.

## Using it

- **Documents** (`/`): create, open, view, export or import (JSON) documents.
- **Editor** (`/edit/<slug>`):
    - **Outline (left):** select, drag to reorder or nest, and add blocks.
    - **Page (centre):** shows the current topic. Click a selected block again (or double-click it, or press
      Enter) to edit it in place.
    - **Properties (right):** the selected block's fields, options and history. With nothing selected, it
      shows the document settings: title page, running title, PDF file name and table of contents.
    - **Header:** undo/redo, History (activity and published versions), Print preview and **Download PDF**.
- **Print view** (`/render/<slug>`): the paginated document. Add `?mode=final` for the final version.

## Development

| Task                 | Command                          |
|----------------------|----------------------------------|
| Run everything       | `composer dev`                   |
| Backend tests        | `php artisan test`               |
| Frontend tests       | `npx vitest run`                 |
| Build assets         | `npm run build`                  |
| Lint / format JS/Vue | `npm run lint`, `npm run format` |
| PHP code style       | `vendor/bin/pint`                |

### Where things are

```
app/
  Services/DocumentEditor.php   the single write path: versions, locks, revisions, broadcasts
  Services/DocumentTree.php     block rows <-> JSON tree (render, import, export)
  Jobs/GeneratePdf.php          server-side PDF export
resources/js/
  components/                   the document components (topics, lists, tables, …)
  builder/registry.js           block type -> component, and the props it reads
  builder/block-types.json      which blocks may contain which (shared with the API)
  builder/editing.js            editor fields and templates for each block type
  builder/editor/               editor UI: outline, canvas, panels, fields, in-place editing
  stores/editor.js              editor state: saves, undo/redo, locks, live sync
  views/                        pages: documents, editor, print view, login
```

**Adding a block type:**

1. Write the component in `resources/js/components`.
2. Register it in `builder/registry.js` and `builder/block-types.json`.
3. Describe its fields in `builder/editing.js`.

## Deploying to a shared server

- **Long-running processes.** Run the queue worker (`php artisan queue:work`) and Reverb (`php artisan reverb:start`) as
  services next to the web server. PDF export and live collaboration
  depend on them.
- **Reverb address.** Set `REVERB_HOST` (and with it `VITE_REVERB_HOST`) to an address the team's browsers
  can reach, then rebuild with `npm run build`.
- **PDF export.**
    - `PDF_BASE_URL` (defaults to `APP_URL`) must be reachable from the machine running the queue worker.
    - Set `PDF_CHROME_PATH` to use an installed Chrome instead of Puppeteer's.
- **Uploads.** Images are uploaded into `public/img`. Only `public/img/uploads` is git-ignored; uploads
  into other folders appear as new files in the repository.
