# Apex Lifter — Backend

Laravel JSON API for **Apex Lifter**, a gym social app where lifters log machine workouts, check in at gyms, climb leaderboards, and settle head-to-head lifting challenges judged by their gym community.

Companion app: [apex_lifter_mobile](https://github.com/dchristo978/apex_lifter_mobile) (Flutter).

## Tech stack

- **PHP 8.3 / Laravel 13** (started from the React starter kit; the product API lives entirely under `routes/api.php`)
- **Laravel Sanctum** — bearer-token auth for the mobile app
- **Laravel Fortify** — password auth, 2FA and passkey plumbing
- **SQLite** by default (`DB_CONNECTION=sqlite`), portable to MySQL
- Proof videos and avatars on the `public` storage disk

## Domain model

| Model | Purpose |
|---|---|
| `User` | Lifter profile: gender, age bracket, weight class, body weight (stale after 90 days), avatar, featured machines, week streak, medal count |
| `Gym` | Physical gym with coordinates; lifters check in by GPS proximity |
| `Checkin` | A visit; the most-frequent gym becomes the lifter's "home gym" |
| `Machine` | Catalogue of gym machines (brand, category, muscle group) |
| `WorkoutSet` | One logged set: weight × reps + estimated 1RM, tied to machine/gym |
| `Challenge` | Head-to-head lift-off (see lifecycle below) |
| `ChallengeVote` | An arena judgement: winner choice + criteria + rejection reason |
| `RankNotification` | In-app notifications (rank alerts and `challenge_*` deep links) |

### Challenge lifecycle

```
pending ──(both proof videos uploaded)──► active ──(arena resolves)──► completed
   │                                                                      │
   ├── declined (opponent)                                     winner gets a medal
   └── cancelled (challenger)
```

- A challenger picks an opponent, machine, and target lift (weight × reps × sets).
- Both participants upload a proof video; once both are in, the **Arena** opens for **48 hours** and lifters from the challenge's gym vote on who performed it validly (or reject the lift with a reason code).
- `challenges:resolve` (scheduled every 30 min, see `routes/console.php`) completes a challenge only when approvers outnumber rejecters **and** one lifter has strictly more votes; otherwise the window rolls forward another 48h.
- Winning a completed challenge mints a **medal**. Medals are listed publicly via `GET /users/{id}/medals`; the owner can attach a free-text story to each medal (**max 100 words**) via `PATCH /challenges/{id}/medal-note`.

## API overview

All routes are under `/api`. Everything except register/login and public gym browsing requires `Authorization: Bearer <token>`.

| Area | Endpoints |
|---|---|
| Auth | `POST auth/register`, `POST auth/login`, `POST auth/logout`, `GET auth/me`, `DELETE auth/account` |
| Password reset | `POST auth/forgot-password` (emails a 6-digit code), `POST auth/reset-password` (code → new password, signs in) |
| Profile | `PATCH profile`, `POST profile/avatar` |
| Gyms | `GET gyms` (public), `GET gyms/{gym}/leaderboard` (public), `POST gyms/checkin`, `GET gyms/checkin/latest`, `GET gyms/{gym}/active-checkins` |
| Machines & progress | `GET machines`, `GET machines/{machine}/progress` |
| Workouts | `GET/POST workout-sets` |
| Leaderboard | `GET leaderboard` (per machine, filterable by gender/age bracket/weight class) |
| Public profiles | `GET users/{user}`, `GET users/{user}/sessions` (paginated), `GET users/{user}/medals` |
| Challenges | `GET challenges`, `GET challenges/arena`, `GET challenges/history`, `POST challenges`, `GET challenges/{id}`, `POST challenges/{id}/video`, `POST challenges/{id}/decline`, `POST challenges/{id}/cancel`, `POST challenges/{id}/vote`, `PATCH challenges/{id}/medal-note` |
| Notifications | `GET notifications`, `POST notifications/read-all` |

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # if using the default sqlite connection
php artisan migrate
php artisan storage:link         # avatars + proof videos are served from the public disk
php artisan serve                # http://127.0.0.1:8000
```

Password-reset codes are emailed via the configured mailer. The default `MAIL_MAILER=log` writes them to `storage/logs/laravel.log` (fine for local dev); configure a real mailer before release so lifters actually receive their codes.

Challenge resolution needs the scheduler in local dev:

```bash
php artisan schedule:work        # or run `php artisan challenges:resolve` manually
```

### Demo data

```bash
php artisan db:seed --class=TestDataSeeder
```

Seeds gyms, the machine catalogue, and a fixed roster of lifters with realistic set history, check-ins, and challenge history. Stable logins — every account uses password `password`; start with `demo@apex.test` (see `database/seeders/TestDataSeeder.php` for the full roster).

## Development

```bash
php artisan test        # PHPUnit feature tests (streaks, medals, auth, ...)
vendor/bin/pint         # code style
vendor/bin/phpstan analyse --memory-limit=1G   # static analysis (larastan)
```

## Project layout

```
app/
  Http/Controllers/Api/   # all mobile-facing controllers
  Models/                 # User, Gym, Machine, WorkoutSet, Challenge, ...
  Services/               # ChallengeService (tally/resolution), LeaderboardService
  Console/Commands/       # challenges:resolve
database/
  migrations/             # schema, in chronological order
  seeders/                # GymSeeder, MachineSeeder, TestDataSeeder
routes/
  api.php                 # the entire mobile API surface
  console.php             # scheduler (challenge resolution every 30 min)
tests/Feature/            # WeekStreakTest, MedalTest, ...
```

## Conventions

- Domain rules live in model methods (`User::weekStreak()`, `User::medalsCount()`, `User::homeGym()`) or services — controllers validate, authorize, and serialize.
- Responses are hand-serialized arrays in controllers (no API resources); timestamps are ISO-8601.
- `now()` returns `CarbonImmutable` app-wide (see `AppServiceProvider`).
