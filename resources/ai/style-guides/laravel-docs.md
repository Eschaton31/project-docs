# Prose style guide (based on the Laravel documentation)

Write the way laravel.com/docs is written: friendly, direct and practical. The reader is a developer or a client's
technical staff who wants to get something done, so every sentence should help them understand or act.

## Voice

- Address the reader as "you". Describe what the system does in the present tense: "The export runs on the queue",
  not "The export will be run on the queue".
- Prefer the active voice. Passive is fine when the actor doesn't matter.
- Use plain words and short sentences. One idea per sentence; split sentences joined by several "and"s.
- Be confident. Cut hedges ("basically", "simply", "just", "actually", "in order to" → "to").
- Don't claim something is easy or obvious.
- Keep a warm, conversational tone without jokes or filler. "Of course," and "For example," are fine as transitions;
  don't start several sentences in a row with them.

## Structure

- A section opens with one or two sentences saying what it covers and why the reader cares, before any detail,
  list, table or code. The first section of a large topic is often an introduction.
- Prose leads into an example and ends with a colon when the example follows directly: "For example, the following
  query returns every active client:"
- Prose explains why or when; it doesn't restate a code block or table line by line.
- Put parallel items in a bullet list. Use a numbered list only for steps that must happen in order.
- Pairs of a term and its explanation belong in a definition-style list, not a paragraph.
- Reference facts (settings, keys and their values, fields and their meaning) belong in a table.
- Notes and warnings go in callouts, one idea each. A note is a helpful aside; a warning is something that will break
  or lose data if ignored.
- Keep sections focused. When a section covers several separate sub-subjects, give each its own heading.

## Headings

- Headings name the task or the concept: "Exporting Reports", "Configuring the Database Connection", "Data Retention".
- Use title case for headings below the top level. Top-level topic titles in these documents are written in ALL CAPS;
  keep them that way.
- Gerunds for tasks ("Creating", "Running"), nouns for concepts. No trailing punctuation.
- Keep headings short and don't repeat the parent heading's words.

## Lists

- Items in a list share the same grammatical form (all start with a verb, or all are noun phrases).
- Capitalize the first word. Use a full stop only when the item is a full sentence; be consistent within a list.
- Don't introduce a list with a sentence fragment that the items finish awkwardly.

## Technical text

- Identifiers, commands, file paths, table and column names, config keys, URLs and literal values are code.
  Never change their spelling, case or content, and keep them in inline code.
- Keep numbers, versions, dates, names of people, clients and products exactly as written.
- Don't add facts the text doesn't state. If something is unclear, leave it rather than guess.
- Spell out an abbreviation the first time it appears in a section unless the audience uses it every day (SQL, API, PDF).

## Mapping to this app's blocks

| Laravel docs | Block |
|---|---|
| Section heading levels | PrimaryTopic → SecondaryTopic → TertiaryTopic → QuaternaryTopic |
| Paragraph | Paragraph containing RichText |
| `> [!NOTE]` | Alert with type `info`, title "Note" |
| `> [!WARNING]` | Alert with type `warn`, title "Warning" |
| Bullet list | BulletList |
| Ordered steps | NumberedList |
| Term and definition list | ConceptList |
| Option or setting reference | KeyValueTable or ItemDescriptionTable |
| Code example | CodeBlock (never invented or changed by the reviewer) |

## Examples

These show the kind of change to make. They're written for this guide.

**Hedging and passive voice**

> Before: In order to generate the report, the export button should basically just be clicked by the user.
>
> After: To generate the report, click **Export**.

**Missing introduction**

> Before: (a section that starts straight with a table of settings)
>
> After: "Each environment reads its settings from the `.env` file. The table below lists the settings this
> deployment uses." followed by the table.

**Inline list**

> Before: The service needs PHP 8.3, a MariaDB database, a Redis server for the queue and Chrome for PDF exports.
>
> After: "The service needs:" followed by a bullet list of PHP 8.3, a MariaDB database, a Redis server for the queue,
> and Chrome for PDF exports.

**Buried warning**

> Before: The job deletes old exports, and note that this can't be undone, every night at 02:00.
>
> After: "The job deletes old exports every night at 02:00." followed by a warning callout: "Deleted exports can't be
> recovered."

**Heading**

> Before: How the data gets imported
>
> After: Importing Data

**Term and definition**

> Before: bullets reading "Draft: left out of the final version", "Hidden: not shown in the editor or the PDF"
>
> After: a ConceptList with the terms Draft and Hidden and their definitions.
