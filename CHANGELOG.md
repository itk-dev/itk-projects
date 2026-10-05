# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-XX-XX

* [PR-40](https://github.com/itk-dev/itk-projects/pull/40)
  Let a project belong to several areas, picked with a searchable multiselect.
* [PR-43](https://github.com/itk-dev/itk-projects/pull/43)
  Overhaul "Økonomi og tid": add amount applied for, ITK budget, co-financing,
  funding rate and what the rest consists of, and let each relevant link carry a note.
* [PR-44](https://github.com/itk-dev/itk-projects/pull/44)
  Let the project list be filtered by several departments at once, picked with
  the same searchable multiselect as the project form.
* [PR-45](https://github.com/itk-dev/itk-projects/pull/45)
  Remove the "Strategies and plans" field. Update Summary help text to now ask
  for strategy and plans.
* [PR-42](https://github.com/itk-dev/itk-projects/pull/42)
  Describe what ITK counts as a project: a callout on the create page, a
  "What counts as a project?" dialog on the project list, and a tour step.
* [PR-41](https://github.com/itk-dev/itk-projects/pull/41)
  Let a project have several types, picked from an admin-managed pool
  instead of a fixed list.
* [PR-33](https://github.com/itk-dev/itk-projects/pull/33)
  Let a project be anchored in several departments, picked with a searchable
  multiselect.
* [PR-36](https://github.com/itk-dev/itk-projects/pull/36)
  Remove the "Stakeholders and partners" field, which duplicated Partners.
  Removed the stakeholder terms.
* [PR-34](https://github.com/itk-dev/itk-projects/pull/34)
  Trim the dashboard to the stats row, "Your work", the activity feed and the
  status and budget-by-department charts.
* [PR-38](https://github.com/itk-dev/itk-projects/pull/38)
  Let two contacts share a name: the contact picker keys on id and shows the
  email to tell namesakes apart.
* [PR-37](https://github.com/itk-dev/itk-projects/pull/37)
  Rename "Short description" to "Summary" and add a separate, longer
  "Description" field.
* [PR-35](https://github.com/itk-dev/itk-projects/pull/35)
  Remove file upload (project images and attachments).
* [PR-32](https://github.com/itk-dev/itk-projects/pull/32)
  Replace the project statuses with a set that follows a funding application,
  from Idé to Afsluttet.
* [PR-30](https://github.com/itk-dev/itk-projects/pull/30)
  Rename "initiative" to "project" throughout.
* [PR-29](https://github.com/itk-dev/itk-projects/pull/29)
  Made the department field on the contact entity relate to the department
  entity.

## [0.3.0] - 2026-08-24

* [PR-27](https://github.com/itk-dev/itk-projects/pull/27)
  Add a Topic field to initiatives, naming the wider programme an initiative is
  part of. Shown under the title on the form and on the initiative page, covered
  by the free-text filter, and included in the CSV export.
* [PR-26](https://github.com/itk-dev/itk-projects/pull/26)
  Reflect the initiative list's filters in the address bar so they can be
  deeplinked.
* [PR-25](https://github.com/itk-dev/itk-projects/pull/25)
  Add a Partner entity (name, description, website) with an admin CRUD, and
  require every initiative to have at least one partner — attached through a
  searchable multiselect that can create new partners on the fly.

## [0.2.0] - 2026-06-30

* [PR-23](https://github.com/itk-dev/itk-projects/pull/23)
  Introduce reusable Twig components (page header, card header, empty state, KPI)
  to replace repeated markup.
* [PR-22](https://github.com/itk-dev/itk-projects/pull/22)
  Use the ITK logo in the nav and login, tidy the dashboard header, and refresh
  the login screen.
* [PR-21](https://github.com/itk-dev/itk-projects/pull/21)
  Add a first-login guided tour where Glimt walks new users through the platform,
  dashboard, creating initiatives and the admin panel.
* [PR-20](https://github.com/itk-dev/itk-projects/pull/20)
  Minor improvements to the user menu styling.
* [PR-19](https://github.com/itk-dev/itk-projects/pull/19)
  Auto-upload files with a progress bar and image preview, view images in an
  in-page lightbox, and refresh the media field styling.
* [PR-18](https://github.com/itk-dev/itk-projects/pull/18)
  Rework the dashboard with an outstanding-work panel and a redesigned activity
  feed, add help text to every graph, and fix the mascot's finish nudges.
* [PR-17](https://github.com/itk-dev/itk-projects/pull/17)
  Fix the mascot nudges that never appeared, nudge users to finish incomplete
  contacts with a link to their edit page.
* [PR-16](https://github.com/itk-dev/itk-projects/pull/16)
  Turn the strategies and tags fields into a searchable,
  shared tag pool where new entries are capitalised and reused as suggestions.
* [PR-15](https://github.com/itk-dev/itk-projects/pull/15)
  Make Kategori a user-defined Area entity with an admin CRUD, replacing the
  fixed Category enum.

## [0.1.0] - 2026-06-26

* [PR-9](https://github.com/itk-dev/itk-projects/pull/9)
  Make department a managed entity and move it and contacts to the admin section
* [PR-7](https://github.com/itk-dev/itk-projects/pull/7)
  Add a mascot motivating users to create and complete initiatives
* [PR-6](https://github.com/itk-dev/itk-projects/pull/6)
  Mark udfyldningsgrad fields with a star that flies into a progress trophy
* [PR-5](https://github.com/itk-dev/itk-projects/pull/5)
  Improve initiative list, search and filters using partial page rendering
* [PR-8](https://github.com/itk-dev/itk-projects/pull/8)
  Adopt itk-dev/entity-bundle: ULID identifiers + shared blamable/timestampable
* [PR-10](https://github.com/itk-dev/itk-projects/pull/10)
  Correct docker compose setup.
* [PR-4](https://github.com/itk-dev/itk-projects/pull/4)
  Add Chart.js graphs to the dashboard
* [PR-3](https://github.com/itk-dev/itk-projects/pull/3)
  Add real-time activity feed with autosave
* [PR-2](https://github.com/itk-dev/itk-projects/pull/2)
  Add Symfony UX Turbo and stimulus.
* [PR-1](https://github.com/itk-dev/itk-projects/pull/1)
  Initial Symfony 8 rebuild of the project database.

[Unreleased]: https://github.com/itk-dev/itk-projects/compare/0.3.0...HEAD
[0.3.0]: https://github.com/itk-dev/itk-projects/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/itk-dev/itk-projects/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/itk-dev/itk-projects/releases/tag/0.1.0
