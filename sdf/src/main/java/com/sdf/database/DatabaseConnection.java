package com.sdf.database;

import com.sdf.config.DatabaseConfig;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;

public class DatabaseConnection {

    private DatabaseConnection() {
    }

    public static Connection getConnection() throws SQLException {
        return DriverManager.getConnection(DatabaseConfig.getJdbcUrl());
    }

    public static void initializeDatabase() {

        String usersTable = """
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    account_status TEXT NOT NULL DEFAULT 'PENDING',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                """;
        String cachedPostsTable = """
        CREATE TABLE IF NOT EXISTS cached_posts (
            id INTEGER PRIMARY KEY,
            topic_id INTEGER NOT NULL,
            author_name TEXT,
            content TEXT,
            timestamp TEXT,
            cached_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        """;
        String cachedPostsIndex =
            "CREATE INDEX IF NOT EXISTS idx_cached_posts_topic ON cached_posts(topic_id);";

        String offlineQueueTable = """
                CREATE TABLE IF NOT EXISTS offline_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_email TEXT,
                    endpoint TEXT NOT NULL,
                    request_method TEXT NOT NULL,
                    payload TEXT,
                    status TEXT NOT NULL DEFAULT 'PENDING',
                    retry_count INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                """;

        try (Connection connection = getConnection();
             Statement statement = connection.createStatement()) {

            statement.execute(usersTable);
            statement.execute(cachedPostsTable);
            statement.execute(cachedPostsIndex);
            statement.execute(offlineQueueTable);
            ensureOfflineQueueUserEmailColumn(statement);

            System.out.println("Cached posts table ready.");
            System.out.println("SQLite connected successfully.");
            System.out.println("Users table ready.");
            System.out.println("Offline queue table ready.");

        } catch (SQLException exception) {
            System.err.println(
                    "Could not initialize local SQLite database: "
                            + exception.getMessage()
            );
        }
    }

    private static void ensureOfflineQueueUserEmailColumn(
            Statement statement
    ) throws SQLException {

        try (ResultSet columns =
                     statement.executeQuery("PRAGMA table_info(offline_queue)")) {

            while (columns.next()) {
                if ("user_email".equalsIgnoreCase(columns.getString("name"))) {
                    return;
                }
            }
        }

        statement.execute("ALTER TABLE offline_queue ADD COLUMN user_email TEXT");
    }
}