# Documentation template: R scripts

A document about a set of R scripts that a person runs interactively (in RStudio or an R console) has the
sections below, in this order. Use the template as a checklist against the document you are given: suggest only
the sections, or the parts of sections, that it is missing. Each section has a key; give it with every new
section you suggest.

Titles: a `PrimaryTopic` title is UPPERCASE; every other topic title is Title Case. Inside a topic, open with a
one- or two-sentence introduction that says what the reader can do with the section.

## overview: OVERVIEW

What the scripts do and who uses what they produce, in a short RichText introduction. A ConceptList for the
key terms a reader meets in the rest of the document (data sets, measures, codes the scripts use), when there
are any. The business purpose usually isn't in the code: if neither the run notes nor the document say it, add
a Placeholder asking the author for it.

## before-you-start: BEFORE YOU START

What must be in place before a run, as a BulletList: R itself (a version only if the code or run notes give
one), the packages from the Dependencies section, access to every file, folder, share and database the scripts
read. An Alert `warn` for anything that has to be set or changed before running (a path, a date, credentials
read with `Sys.getenv`).

## running-the-scripts: RUNNING THE SCRIPTS

The steps a person takes, as a NumberedList, taken from the run notes: which script to open, what to set at the
top, which parts to run and in what order, what to check between parts. Show the lines the person edits or runs
as a CodeBlock `r` copied from the script. A KeyValueTable of the values they set (variable → what it means and
an example), when there are several. These scripts are run interactively: don't describe a command line,
scheduler or service unless the run notes do.

## script-flow: SCRIPT FLOW

A Mermaid `flowchart LR` (or `TD` for long chains) of the order the scripts run in or `source()` each other,
with the main files and tables each one reads and writes. Nodes are scripts and data, labelled with their file
or table names. Keep it to what fits on one screen.

## project-structure: PROJECT STRUCTURE

A FileTree of the scripts, as uploaded (folders implied by their paths), with a short note on each file. Add a
one-sentence description.

## dependencies: DEPENDENCIES

An ItemDescriptionTable (itemName "Package", descriptionName "Used for", `mono` true) of every package the
scripts load or call with `pkg::`, and what the scripts use it for.

## inputs-and-outputs: INPUTS AND OUTPUTS

An ItemDescriptionTable of every file, folder or database table read, and one of everything written, with what
each holds. A DataDictionary for a data set whose columns the code names (selects, renames, creates or
filters), with only the columns the code shows; describe what the code does with each.

## script-reference: SCRIPT REFERENCE

One SecondaryTopic per script, in run order, titled with the file name. Inside it: what the script does and
when it runs; then a TertiaryTopic per significant function (one the other scripts call, or a long one), with
its signature as a CodeBlock `r`, its parameters as a ConceptList, and what it returns. Skip trivial helpers.

## configuration: CONFIGURATION

A KeyValueTable of the constants, options and paths the scripts set near the top (`options(...)`, file paths,
dates, thresholds), with what each controls.

## troubleshooting: TROUBLESHOOTING

The messages the scripts raise with `stop()` or `warning()`, as a ConceptList (message → what it means and what
to do). When the code raises none, a Placeholder asking the author for the common problems.

# Rules

- Describe only what the scripts, the run notes and the document show. Never invent column names, paths,
  packages, steps, values or versions. When a section needs something none of them give, add a Placeholder
  saying exactly what the author must supply.
- Don't judge, fix or improve the code. Copy R into a CodeBlock exactly as it is in the script, and cite the lines.
- Use the style guide's voice: second person, present tense, active voice, short sentences.
