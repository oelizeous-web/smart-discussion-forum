# Smart Discussion Forum — JavaFX Desktop App

A pure-Java (no Scene Builder / no hand-written FXML) JavaFX recreation of the
"Smart Discussion Forum" HTML prototype. Every screen is built programmatically
in Java and styled entirely through the external stylesheet
`src/main/resources/css/app.css`, matching the prototype's colors, layout, and
typography (brand green `#2F7A54`, accent green `#5CC98B`, cream background
`#F7F5EE`, the green sidebar shell, cards, tables, badges, chat bubbles, quiz
UI, etc).

## Project structure (Maven layout — open the folder directly in IntelliJ)

```
smart-discussion-forum/
├── pom.xml
└── src/main/
    ├── java/com/sdf/
    │   ├── MainApp.java              # Application entry point
    │   ├── Router.java               # Screen-switching controller
    │   ├── components/
    │   │   ├── Sidebar.java          # Shared green sidebar (brand+nav+profile)
    │   │   └── UiKit.java            # Buttons/cards/badges/stat-card helpers
    │   └── screens/
    │       ├── LandingScreen.java    # Public welcome page
    │       ├── LoginScreen.java
    │       ├── RegisterScreen.java
    │       ├── student/
    │       │   ├── StudentDashboardScreen.java
    │       │   ├── StudentDiscussionsScreen.java   (chat interface)
    │       │   ├── StudentQuizzesScreen.java        (quiz center)
    │       │   └── QuizAttemptScreen.java
    │       ├── lecturer/
    │       │   ├── LecturerDashboardScreen.java     (moderation control center)
    │       │   ├── LecturerStudentsScreen.java
    │       │   ├── LecturerGradesScreen.java
    │       │   ├── LecturerQuizBuilderScreen.java   (assessment builder)
    │       │   └── LecturerReportsScreen.java
    │       └── admin/
    │           ├── AdminDashboardScreen.java
    │           ├── AdminMembersScreen.java          (tabs: members/lecturers/moderation)
    │           └── AdminSettingsScreen.java
    └── resources/css/app.css         # All visual styling
```

## How to open & run in IntelliJ IDEA

1. **File → Open...** and select the `smart-discussion-forum` folder (the one
   containing `pom.xml`). IntelliJ will detect it as a Maven project and
   download the JavaFX dependencies automatically (requires internet on first
   import, for Maven to fetch `org.openjfx:javafx-controls` /
   `javafx-fxml` version 21.0.2).
2. Wait for Maven to finish indexing (bottom-right progress bar).
3. Open `src/main/java/com/sdf/MainApp.java`.
4. Click the green ▶ run arrow next to the `main` method, **or** run the Maven
   goal `javafx:run` from the Maven tool window (`smart-discussion-forum →
   Plugins → javafx → javafx:run`).
5. The app window opens on the **Landing** screen. Use the on-screen buttons
   (Log in / Register / Get Started / sidebar links) to navigate — there is no
   separate "prototype navigator"; navigation happens the same way a real user
   would move through the app (matching the real screens in the original
   prototype, minus its dev-only top nav bar).

### Running from the command line instead
```
mvn clean javafx:run
```

### Requirements
- JDK 17 or newer
- Maven 3.8+
- Internet connection the first time you build (to fetch the JavaFX
  dependencies) — after that, they're cached locally by Maven.

## Notes on fidelity
This is a hand-built JavaFX translation of a Tailwind/HTML mockup. Colors,
typography weights, spacing, card/table/badge styles, the green sidebar shell,
the chat UI, and the quiz-attempt UI all mirror the prototype closely. Some
things that don't have a direct desktop equivalent (raw inline SVG icons,
CSS hover-only micro-interactions, browser modals) were re-implemented with
the closest native JavaFX/CSS equivalent (Unicode glyphs for icons, JavaFX
`:hover` pseudo-classes in the stylesheet, etc).
