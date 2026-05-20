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
        buildConfigField("String", "SERVER_URL", "\"http://10.0.2.2/mdm\"")  // 10.0.2.2 = host Mac from emulator
        buildConfigField("String", "WS_URL",     "\"ws://10.0.2.2:8080\"")   // WebSocket on host Mac
    }

    signingConfigs {
        create("release") {
            storeFile     = file(keystoreProps["CHAT_STORE_FILE"] ?: "../keystore/chat-app-release.keystore")
            storePassword = keystoreProps["CHAT_STORE_PASSWORD"] as String? ?: ""
            keyAlias      = keystoreProps["CHAT_KEY_ALIAS"] as String? ?: "chat-app"
            keyPassword   = keystoreProps["CHAT_KEY_PASSWORD"] as String? ?: ""
        }
    }

    buildTypes {
        debug {
            applicationIdSuffix = ".debug"
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
    implementation(libs.okhttp)
    implementation(libs.coroutines.android)
    implementation(libs.coil.compose)
    implementation(libs.datastore)

    // Firebase / FCM
    implementation(platform("com.google.firebase:firebase-bom:33.7.0"))
    implementation("com.google.firebase:firebase-messaging-ktx")
}
