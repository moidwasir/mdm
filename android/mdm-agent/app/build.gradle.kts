import java.util.Properties

// Load keystore signing properties
val keystoreProps = Properties().also { props ->
    val propsFile = rootProject.file("../keystore.properties")
    if (propsFile.exists()) props.load(propsFile.inputStream())
}

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
}

android {
    namespace = "com.mdm.agent"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.mdm.agent"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
        buildConfigField("String", "SERVER_URL", "\"http://187.77.118.52\"")
    }

    // Release signing config using keystore
    signingConfigs {
        create("release") {
            storeFile     = rootProject.file(keystoreProps.getProperty("AGENT_STORE_FILE") ?: "../keystore/mdm-agent-release.keystore")
            storePassword = keystoreProps.getProperty("AGENT_STORE_PASSWORD") ?: ""
            keyAlias      = keystoreProps.getProperty("AGENT_KEY_ALIAS") ?: "mdm-agent"
            keyPassword   = keystoreProps.getProperty("AGENT_KEY_PASSWORD") ?: ""
        }
    }

    buildTypes {
        debug {
            applicationIdSuffix = ".debug"
        }
        release {
            isMinifyEnabled  = true
            isShrinkResources = true
            signingConfig    = signingConfigs.getByName("release")
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }

    buildFeatures { buildConfig = true }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.appcompat)
    implementation(libs.retrofit)
    implementation(libs.retrofit.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.coroutines.android)
    implementation(libs.work.runtime)
}
