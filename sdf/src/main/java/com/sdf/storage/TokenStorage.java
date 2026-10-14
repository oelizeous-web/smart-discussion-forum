package com.sdf.storage;

import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;
import javax.crypto.spec.SecretKeySpec;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.nio.file.attribute.PosixFilePermission;
import java.security.GeneralSecurityException;
import java.security.SecureRandom;
import java.util.Base64;
import java.util.EnumSet;
import java.util.Properties;
import java.util.Set;
import java.util.prefs.BackingStoreException;
import java.util.prefs.Preferences;

/**
 * Secure local storage for the desktop client's authentication session.
 *
 * The token and email are encrypted using AES-GCM before being written
 * to disk. The encryption key is stored in the user's preference store,
 * while the encrypted session file is stored in an application-specific
 * directory with restrictive permissions.
 */
public final class TokenStorage {

    private static final Path STORAGE_DIRECTORY =
            Path.of(
                    System.getProperty("user.home"),
                    ".smart-discussion-forum"
            );

    private static final Path SESSION_FILE =
            STORAGE_DIRECTORY.resolve("session.properties");

    private static final String TOKEN_PROPERTY = "token";
    private static final String EMAIL_PROPERTY = "email";
    private static final String PREFERENCES_NODE = "/com/sdf/storage/token";
    private static final String KEY_PREFERENCE = "session_key";

    private static final String ENCRYPTION_ALGORITHM = "AES/GCM/NoPadding";
    private static final int AES_KEY_SIZE = 256;
    private static final int IV_LENGTH = 12;
    private static final int GCM_TAG_LENGTH = 128;

    private static final SecureRandom SECURE_RANDOM =
            new SecureRandom();

    private TokenStorage() {
    }

    public static synchronized void saveToken(String token) {
        saveProperty(TOKEN_PROPERTY, token);
    }

    public static synchronized String getToken() {
        return readProperty(TOKEN_PROPERTY);
    }

    public static synchronized void saveLoggedInEmail(String email) {
        saveProperty(EMAIL_PROPERTY, email);
    }

    public static synchronized String getLoggedInEmail() {
        return readProperty(EMAIL_PROPERTY);
    }

    /**
     * Removes the stored token and email.
     *
     * The encryption key is retained so the same installation can
     * continue using its secure storage after another login.
     */
    public static synchronized void clearToken() {
        try {
            Files.deleteIfExists(SESSION_FILE);
        } catch (IOException exception) {
            System.err.println(
                    "Could not clear the locally stored authentication session."
            );
        }
    }

    private static void saveProperty(
            String propertyName,
            String value
    ) {

        try {
            createStorageDirectory();

            Properties properties = loadProperties();

            if (value == null || value.isBlank()) {
                properties.remove(propertyName);
            } else {
                SecretKey key = loadOrCreateKey();
                properties.setProperty(
                        propertyName,
                        encrypt(value, key)
                );
            }

            saveProperties(properties);

        } catch (IOException | GeneralSecurityException exception) {
            System.err.println(
                    "Could not securely save authentication information: "
                            + exception.getClass().getSimpleName()
            );
        }
    }

    private static String readProperty(String propertyName) {

        if (!Files.exists(SESSION_FILE)) {
            return null;
        }

        try {
            Properties properties = loadProperties();
            String encryptedValue =
                    properties.getProperty(propertyName);

            if (encryptedValue == null || encryptedValue.isBlank()) {
                return null;
            }

            SecretKey key = loadOrCreateKey();
            return decrypt(encryptedValue, key);

        } catch (IOException | GeneralSecurityException exception) {
            System.err.println(
                    "Could not securely read authentication information: "
                            + exception.getClass().getSimpleName()
            );

            return null;
        }
    }

    private static Properties loadProperties() throws IOException {

        Properties properties = new Properties();

        if (!Files.exists(SESSION_FILE)) {
            return properties;
        }

        try (InputStream inputStream =
                     Files.newInputStream(SESSION_FILE)) {

            properties.load(inputStream);
        }

        return properties;
    }

    private static void saveProperties(
            Properties properties
    ) throws IOException {

        createStorageDirectory();

        Path temporaryFile =
                STORAGE_DIRECTORY.resolve("session.properties.tmp");

        try (OutputStream outputStream =
                     Files.newOutputStream(temporaryFile)) {

            properties.store(
                    outputStream,
                    "Smart Discussion Forum secure session"
            );
        }

        restrictFilePermissions(temporaryFile);

        try {
            Files.move(
                    temporaryFile,
                    SESSION_FILE,
                    StandardCopyOption.REPLACE_EXISTING,
                    StandardCopyOption.ATOMIC_MOVE
            );
        } catch (IOException exception) {
            Files.move(
                    temporaryFile,
                    SESSION_FILE,
                    StandardCopyOption.REPLACE_EXISTING
            );
        }

        restrictFilePermissions(SESSION_FILE);
    }

    private static SecretKey loadOrCreateKey()
            throws IOException, GeneralSecurityException {

        Preferences preferences =
                Preferences.userRoot().node(PREFERENCES_NODE);

        String encodedKey =
                preferences.get(KEY_PREFERENCE, null);

        if (encodedKey != null && !encodedKey.isBlank()) {
        byte[] keyBytes =
                Base64.getDecoder().decode(
                        encodedKey.trim()
                );

        return new SecretKeySpec(keyBytes, "AES");
        }

        KeyGenerator keyGenerator =
                KeyGenerator.getInstance("AES");

        keyGenerator.init(AES_KEY_SIZE);
        SecretKey secretKey =
                keyGenerator.generateKey();

        String encodedKeyToStore =
                Base64.getEncoder().encodeToString(
                        secretKey.getEncoded()
                );

        preferences.put(
                KEY_PREFERENCE,
                encodedKeyToStore
        );

        try {
            preferences.flush();
        } catch (BackingStoreException exception) {
            throw new IOException(
                    "Could not persist encryption key.",
                    exception
            );
        }

        return secretKey;
    }

    private static String encrypt(
            String plainText,
            SecretKey key
    ) throws GeneralSecurityException {

        byte[] iv = new byte[IV_LENGTH];
        SECURE_RANDOM.nextBytes(iv);

        Cipher cipher =
                Cipher.getInstance(ENCRYPTION_ALGORITHM);

        cipher.init(
                Cipher.ENCRYPT_MODE,
                key,
                new GCMParameterSpec(GCM_TAG_LENGTH, iv)
        );

        byte[] encryptedBytes =
                cipher.doFinal(
                        plainText.getBytes(StandardCharsets.UTF_8)
                );

        byte[] combined =
                new byte[iv.length + encryptedBytes.length];

        System.arraycopy(
                iv,
                0,
                combined,
                0,
                iv.length
        );

        System.arraycopy(
                encryptedBytes,
                0,
                combined,
                iv.length,
                encryptedBytes.length
        );

        return Base64.getEncoder().encodeToString(combined);
    }

    private static String decrypt(
            String encryptedText,
            SecretKey key
    ) throws GeneralSecurityException {

        byte[] combined =
                Base64.getDecoder().decode(encryptedText);

        if (combined.length <= IV_LENGTH) {
            throw new GeneralSecurityException(
                    "Invalid encrypted session data."
            );
        }

        byte[] iv = new byte[IV_LENGTH];
        byte[] encryptedBytes =
                new byte[combined.length - IV_LENGTH];

        System.arraycopy(
                combined,
                0,
                iv,
                0,
                IV_LENGTH
        );

        System.arraycopy(
                combined,
                IV_LENGTH,
                encryptedBytes,
                0,
                encryptedBytes.length
        );

        Cipher cipher =
                Cipher.getInstance(ENCRYPTION_ALGORITHM);

        cipher.init(
                Cipher.DECRYPT_MODE,
                key,
                new GCMParameterSpec(GCM_TAG_LENGTH, iv)
        );

        byte[] decryptedBytes =
                cipher.doFinal(encryptedBytes);

        return new String(
                decryptedBytes,
                StandardCharsets.UTF_8
        );
    }

    private static void createStorageDirectory()
            throws IOException {

        Files.createDirectories(STORAGE_DIRECTORY);
        restrictDirectoryPermissions(STORAGE_DIRECTORY);
    }

    private static void restrictDirectoryPermissions(Path directory) {

        try {
            Set<PosixFilePermission> permissions =
                    EnumSet.of(
                            PosixFilePermission.OWNER_READ,
                            PosixFilePermission.OWNER_WRITE,
                            PosixFilePermission.OWNER_EXECUTE
                    );

            Files.setPosixFilePermissions(
                    directory,
                    permissions
            );

        } catch (UnsupportedOperationException | IOException ignored) {
            directory.toFile().setReadable(false, false);
            directory.toFile().setWritable(false, false);
            directory.toFile().setExecutable(false, false);

            directory.toFile().setReadable(true, true);
            directory.toFile().setWritable(true, true);
            directory.toFile().setExecutable(true, true);
        }
    }

    private static void restrictFilePermissions(Path file) {

        try {
            Set<PosixFilePermission> permissions =
                    EnumSet.of(
                            PosixFilePermission.OWNER_READ,
                            PosixFilePermission.OWNER_WRITE
                    );

            Files.setPosixFilePermissions(
                    file,
                    permissions
            );

        } catch (UnsupportedOperationException | IOException ignored) {
            file.toFile().setReadable(false, false);
            file.toFile().setWritable(false, false);
            file.toFile().setExecutable(false, false);

            file.toFile().setReadable(true, true);
            file.toFile().setWritable(true, true);
        }
    }
}