# Smart Discussion Forum

An academic discussion forum application for students, lecturers, and admins — implemented as **Laravel API/website back-end** coupled with a **native JavaFX desktop front-end client**. Students join course groups, post in discussion threads, and participate in quizzes built by the lecturers; lecturers manage their classes, assign quizzes and check reports; admins manage the members and monitor the platform.

This project comes in two flavors for working with the application:

|  | Description | Framework/Stack |
|---|---|---|
| **`backend/`** | Server-side API and rendered web application (both API and pages) | Laravel 12, PHP 8.2, Sanctum, Livewire/Volt, Tailwind |
| **`sdf/`** | Native JavaFX desktop client that consumes the API | JavaFX 26 (pure Java, no FXML), Maven |

---

## Features

- **User role-based access** to functionality with Student, Lecturer and Admin roles — controlled via `spatie/laravel-permission` and route middelwares
- **Course groups and discussion topics** — students join groups and participate in discussion topics
- **Moderation** of discussion posts with flagging, warnings and suspensions
- **Quizzes** — lecturers can create and administer quizzes, students participate and receive grades for them
- **Platform administrator control panel** — member management, lecturer registration, moderation, blacklists, etc.
- **Token-based authentication** of the JavaFX client (with Laravel Sanctum) which makes it independent from web session
- **Realtime-ready** implementation with Laravel Reverb/Echo support included (broadcast event handling)

---

## Project structure

```
Smart-Discussion-Forum-master/
├── backend/            # Laravel application (API + Livewire web UI)
│   ├── app/
│   │   ├── Http/Controllers/Api/   # AuthController, GroupController, TopicController,
│   │   │                           # PostController, QuizController, QuizAttemptController,
│   │   │                           # AdminController, ApiDocsController
│   │   ├── Models/                 # User, Group, Topic, Post, Quiz, QuizAttempt,
│   │   │                           # Question, Mark, Report, Blacklist, Setting
│   │   └── Livewire/               # Volt components for rendering server pages
│   ├── routes/
│   │   ├── api.php                 # JSON API used by the JavaFX client
│   │   ├── web.php                 # Blade/Livewire routes (dashboard, forums, quizzes, admin)
│   │   └── auth.php
│   ├── database/                   # Migrations, factories, seeders
│   └── Dockerfile                  # Container build script for deployment
│
├── sdf/                # JavaFX desktop client
│   ├── pom.xml
│   └── src/main/
│       ├── java/com/sdf/
│       │   ├── MainApp.java        # Application entry point
│       │   ├── Router.java         # Screen changing controller
│       │   ├── components/         # Shared sidebar UI along with UI kit (buttons, cards, badges, etc)
│       │   └── screens/            # Landing, Login, Register, Student/Lecturer/Admin screens
│       └── resources/css/app.css   # Visual style of the entire client
│
└── render.yaml         # Render.com deployment configuration (Postgres and Docker web service)
```

---

## Getting started

### 1. Back-end (Laravel API + web app)

**Requirements:** PHP 8.2+, Composer, Node.js + npm, and a database (SQLite will work out-of-the-box for local development).

```bash
cd backend
cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate --seed      # creates database/database.sqlite by default

# Run the API and web server, queue worker, and Vite dev server together
composer run dev
```

The app will be available at `http://localhost:8000` (Blade/Livewire UI), with JSON API at `/api` (see `routes/api.php`). API documentation is provided at the API root using `ApiDocsController`.

**Key API endpoints** (under `/api`, Sanctum-protected unless otherwise stated):

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/login`, `/register` | Public authentication |
| `GET/POST` | `/groups`, `/groups/join` | Course groups (create/join, etc) |
| `GET/POST` | `/groups/{group}/topics`, `/topics/{id}/posts` | Discussions (topics and posts) |
| `POST` | `/posts/{post}/flag` | Moderate a discussion post |
| `GET/POST` | `/quizzes`, `/quizzes/{quiz}`, `/attempts/{attempt}/submit` | Participate in quizzes |
| `POST` | `/groups/{group}/quizzes` | Create a quiz (lecturer/admin) |
| `GET` | `/quizzes/{quiz}/report`, `/groups/{group}/grades` | Generate report for a quiz (lecturer/admin) |
| `GET/POST` | `/admin/*` | Admin dashboard, users, lecturers, flags |

#### Running with Docker

```bash
cd backend
docker build -t smart-discussion-forum-backend .
docker run -p 10000:10000 --env-file .env smart-discussion-forum-backend
```

The included `render.yaml` sets up the back-end as a Docker web service on [Render](https://render.com) along with a Postgres database.

### 2. Desktop client (JavaFX)

A native recreation of the web application forum implemented fully in code, with styling through `sdf/src/main/resources/css/app.css` matching the brand green (`#2F7A54`), accent green (`#5CC98B`) and cream (`#F7F5EE`) palette, including sidebar UI, cards, tables, badges, chat bubble and quiz UI.

**Requirements:** JDK 17+ (project targets Java 26 through the Maven toolchain), Maven 3.8+, internet connection for downloading JavaFX and SQLite libraries for the first time.

```bash
cd sdf
mvn clean javafx:run
```

Alternatively, the project folder may be opened directly in IntelliJ IDEA (IDEA will recognize it as a Maven project) and executed from `MainApp.java` or `javafx:run` Maven goal. The application opens on **Landing** screen and the user navigates to different views using the on-screen Log in / Register / Get Started / sidebar buttons.

Set the client to communicate with running back-end by configuring API base URL (see the client's configuration/service layer in `sdf/src/main/java/com/sdf/` to match where `backend/` is running).

---

## Tech stack summary

- **Back-end:** Laravel 12, PHP 8.2, Sanctum (token-based authentication for the API), Livewire 3 + Volt, Laravel Reverb (broadcasting), spatie/laravel-permission (permissions), Tailwind CSS, Vite
- **Client:** JavaFX 26, Maven, SQLite (for local data caching)
- **Deployment:** Docker (back-end), Render.com blueprint (`render.yaml`) with Postgres

## Tests

```bash
cd backend
php artisan test        # Pest test suite
```

## Contributing

1. Fork the repository and create a feature branch
2. Implement your feature in `backend/` and/or `sdf/`
3. Run tests (`php artisan test` for the back-end)
4. Create a pull request with an explanation

## License

See the individual package manifests (`backend/composer.json` is MIT-licensed through Laravel starter kit). There's no explicit top-level license file present yet. Add one if you plan to distribute this project.
