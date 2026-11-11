import java.util.Properties

plugins {
    id("com.android.application")
    // Needed because android.builtInKotlin=false (gradle.properties): without it MainActivity.kt is never compiled
    // and the app crashes on launch with ClassNotFoundException.
    id("org.jetbrains.kotlin.android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Firebase config is per-project and supplied by the team (Firebase console → Project settings → google-services.json).
// Without it the app still builds and runs; push notifications are simply off.
// A TEST build (ADINN_TEST_BUILD=1, see tool/build_release.ps1 -Test) installs next to the real app under another id, so it
// skips Firebase (the project file is for the real id): push is off in it.
val testBuild = System.getenv("ADINN_TEST_BUILD") == "1"
if (!testBuild && file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}

// Release signing: key.properties (next to this project's settings.gradle.kts, git-ignored) points at the release keystore.
// See ../RELEASING.md. A release build without it is refused rather than quietly signed with the debug key.
val keyProps = Properties().apply {
    val f = rootProject.file("key.properties")
    if (f.exists()) f.inputStream().use { load(it) }
}

android {
    namespace = "in.co.adinn.adinn_tasks"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "in.co.adinn.adinn_tasks"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        manifestPlaceholders["appLabel"] = if (testBuild) "Adinn Tasks (TEST)" else "Adinn Design Tasks"
        if (testBuild) applicationIdSuffix = ".test"
    }

    signingConfigs {
        create("release") {
            if (keyProps.isNotEmpty()) {
                keyAlias = keyProps.getProperty("keyAlias")
                keyPassword = keyProps.getProperty("keyPassword")
                storeFile = rootProject.file(keyProps.getProperty("storeFile"))
                storePassword = keyProps.getProperty("storePassword")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("release")
        }
    }
}

gradle.taskGraph.whenReady {
    val releasing = allTasks.any { t -> (t.name.startsWith("assemble") || t.name.startsWith("bundle") || t.name.startsWith("package")) && t.name.contains("Release") }
    if (releasing && keyProps.isEmpty) {
        throw GradleException("Release builds must be signed with the release key: create android/key.properties (see RELEASING.md). Refusing to sign with the debug key.")
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
