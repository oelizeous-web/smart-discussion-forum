package com.sdf.config;

import java.io.File;

/**
 * Central configuration for the embedded SQLite database.
 */
public class DatabaseConfig {

    private static final String APP_DATA_DIR =
            System.getProperty("user.home") + File.separator + ".smart_discussion_forum";

    private static final String DB_FILE_NAME = "smart-forum.db";

    private DatabaseConfig() {
    }

    public static String getDatabaseFilePath() {
        File dir = new File(APP_DATA_DIR);

        if (!dir.exists()) {
            dir.mkdirs();
        }

        return APP_DATA_DIR + File.separator + DB_FILE_NAME;
    }

    public static String getJdbcUrl() {
        return "jdbc:sqlite:" + getDatabaseFilePath();
    }
}