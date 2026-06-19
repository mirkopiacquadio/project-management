# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A project-management app (fork of dewakoding-project-management) built on **Laravel 12 + Filament 4**, with Kanban board, sprints, epics, timeline, and a token-based external client portal. UI text is largely Italian (`lang/it`, `lang/it.json`).

## Dev environment: Docker only

The app requires **PHP 8.3+** but the local PHP is 8.2 — **always run artisan inside the container**:

```bash
docker compose up -d                          # start the stack
docker exec laravel_app php artisan <cmd>     # any artisan command
```

Services: app at http://localhost:8000/admin (root `/` redirects to login), phpMyAdmin at :8080, MySQL on host port **3307** (root/secret, db `dewakoding_project_management`). A `laravel_queue` container runs `queue:work` (database queue — email notifications won't send without it).

A separate **demo stack** exists: `docker compose -f docker-compose.demo.yml -p pmdemo up -d` (project name `pmdemo`, containers suffixed `_demo`). See `docs/DEPLOY.md`, `docs/RESET.md`.

## Commands

```bash
# Tests (Pest)
docker exec laravel_app php artisan test
docker exec laravel_app php artisan test --filter=SomeTest   # single test

# Code style
./vendor/bin/pint

# Frontend (Vite + Tailwind 4; Node runs on the host, not in Docker)
npm run dev
npm run build

# Full reset to initial state (migrate:fresh --seed + Shield permissions + super admin)
docker exec laravel_app php artisan app:reset --force

# Regenerate Shield permissions/policies after adding resources/pages/widgets
docker exec laravel_app php artisan shield:generate --all --option=policies
```

Known issue: `php artisan view:cache` fails on a vendor file (`tab-layout-plugin`) — skip it.

## Architecture

Everything user-facing lives in the **Filament admin panel** (`app/Providers/Filament/AdminPanelProvider.php`), which auto-discovers `app/Filament/Resources`, `Pages`, and `Widgets`. The panel runs in **SPA mode** with `databaseTransactions()` enabled; `/admin/project-board*` is excluded from SPA navigation because the Kanban drag&drop JS breaks on SPA loads (see comment in the provider).

### Domain model

- **Project** is the aggregate root: has per-project **TicketStatus** rows (custom Kanban columns with colors), **Tickets**, **Epics**, **ProjectNotes**, and members via a pivot with roles. `Project::generateExternalAccess()` creates a token for the client portal.
- **Ticket** belongs to project/status/epic/priority/sprint, has **multiple assignees** (BelongsToMany), comments, and **TicketHistory**. Unique ticket IDs (project prefix + number) and history entries are generated in model `booted()` hooks — not in resources — so they apply everywhere.
- **Sprint / SprintStatus** parallel the project board: a ticket has both a project `status` and a `sprintStatus`.
- Custom Filament pages (`app/Filament/Pages`): ProjectBoard, SprintBoard, TicketTimeline, ProjectTimeline, EpicsOverview, Leaderboard, UserContributions, SystemSettings.

### Authorization (Filament Shield)

RBAC via bezhansalleh/filament-shield + spatie/permission; policies in `app/Policies` map to Shield permissions. The `super_admin` role uses `define_via_gate => false` in `config/filament-shield.php`, meaning it must literally hold every permission — after adding new entities, regenerate permissions or super admin loses access. `SystemResetService` handles this during resets.

### Other entry points

- **External client portal**: non-Filament Livewire components (`app/Livewire/ExternalLogin`, `ExternalDashboard`) routed in `routes/web.php` under `/external/{token}`, authenticated by the project's `ExternalAccess` token, not by user login.
- **Google OAuth** via Socialite: `app/Http/Controllers/Auth/GoogleController.php`, linked by email to existing users.
- **System reset**: `app/Services/SystemResetService.php` is shared by the SystemSettings page button and the `app:reset` command (`app/Console/Commands/AppReset.php`) — keep both paths in sync by editing the service.
- **Notifications**: `app/Services/NotificationService.php` + queued mail (project assignment, comments, status changes).
