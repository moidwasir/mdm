import java.util.Properties

val keystoreProps = Properties().also { props ->
    val propsFile = rootProject.file("../keystore.properties")
    if (propsFile.exists()) props.load(propsFile.inputStream())
}

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.ksp)
    id("com.google.gms.google-services")   // Firebase
}

android {
    namespace = "com.mdm.chat"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.mdm.chat"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
        buildConfigField("String", "SERVER_URL", "\"http://187.77.118.52\"")
        buildConfigField("String", "WS_URL",     "\"ws://187.77.118.52:8080\"")
    }

    signingConfigs {
        create("release") {
            storeFile     = rootProject.file(keystoreProps.getProperty("CHAT_STORE_FILE") ?: "../keystore/chat-app-release.keystore")
            storePassword = keystoreProps.getProperty("CHAT_STORE_PASSWORD") ?: ""
            keyAlias      = keystoreProps.getProperty("CHAT_KEY_ALIAS") ?: "chat-app"
            keyPassword   = keystoreProps.getProperty("CHAT_KEY_PASSWORD") ?: ""
        }
    }

    buildTypes {
        debug {
            // Keep production package name to match google-services.json
        }
        release {
            isMinifyEnabled   = true
            isShrinkResources = true
            signingConfig     = signingConfigs.getByName("release")
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }

    buildFeatures { compose = true; buildConfig = true }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(platform(libs.compose.bom))
    implementation(libs.compose.ui)
    implementation(libs.compose.ui.tooling)
    implementation(libs.compose.material3)
    implementation(libs.compose.material.icons)
    implementation(libs.activity.compose)
    implementation(libs.navigation.compose)
    implementation(libs.lifecycle.viewmodel)
    implementation(libs.room.runtime)
    implementation(libs.room.ktx)
    ksp(libs.room.compiler)
    implementation(libs.retrofit)
    implementation(libs.retrofit.gson)
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    implementation(libs.coroutines.android)
    implementation(libs.coil.compose)
    implementation(libs.datastore)

    // Firebase / FCM
    implementation(platform("com.google.firebase:firebase-bom:33.7.0"))
    implementation("com.google.firebase:firebase-messaging-ktx")
}
