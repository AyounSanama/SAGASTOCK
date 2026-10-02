import java.util.Base64

// S-06 : le HTTP en clair n'est autorisé que dans les versions de test.
// Flutter transmet les --dart-define encodés en base64 (propriété « dart-defines ») ;
// une version APP_ENV=production n'autorise que le HTTPS.
val dartDefines: Map<String, String> = (project.findProperty("dart-defines") as String?)
    ?.split(",")
    ?.mapNotNull { encoded ->
        val pair = String(Base64.getDecoder().decode(encoded)).split("=", limit = 2)
        if (pair.size == 2) pair[0] to pair[1] else null
    }
    ?.toMap() ?: emptyMap()
val isProductionBuild = dartDefines["APP_ENV"] == "production"

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "org.sagastock.sagastock_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "org.sagastock.sagastock_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        manifestPlaceholders["usesCleartextTraffic"] = (!isProductionBuild).toString()
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

flutter {
    source = "../.."
}
