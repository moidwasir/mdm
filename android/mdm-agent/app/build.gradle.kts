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
        buildConfigField("String", "SERVER_URL", "\"http://10.0.2.2/mdm\"")  // 10.0.2.2 = host Mac from emulator
    }

    // Release signing config using keystore
    signingConfigs {
        create("release") {
            storeFile     = file(keystoreProps["AGENT_STORE_FILE"] ?: "../keystore/mdm-agent-release.keystore")
            storePassword = keystoreProps["AGENT_STORE_PASSWORD"] as String? ?: ""
            keyAlias      = keystoreProps["AGENT_KEY_ALIAS"] as String? ?: "mdm-agent"
            keyPassword   = keystoreProps["AGENT_KEY_PASSWORD"] as String? ?: ""
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
