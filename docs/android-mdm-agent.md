# Android MDM Agent — Implementation Guide

## Overview
The MDM Agent is a **Device Owner** Android app built in Kotlin. Once set as Device Owner, it has full control over the device — it locks it to only the Chat App, disables Play Store, prevents factory reset, and reports status to the admin server.

## Package Name
`com.mdm.agent`

## Project Structure
```
mdm-agent/
├── app/
│   └── src/main/
│       ├── AndroidManifest.xml
│       ├── java/com/mdm/agent/
│       │   ├── DeviceAdminReceiver.kt      ← DPC receiver
│       │   ├── MainActivity.kt             ← Entry point (hidden from user)
│       │   ├── EnrollmentActivity.kt       ← First-run enrollment
│       │   ├── PolicyManager.kt            ← Applies device restrictions
│       │   ├── HeartbeatService.kt         ← Background status reporting
│       │   ├── CommandHandler.kt           ← Processes admin commands
│       │   └── api/
│       │       └── MdmApiClient.kt         ← Retrofit API client
│       └── res/
│           ├── xml/device_admin.xml        ← Required DPC capabilities
│           └── layout/
│               └── activity_enrollment.xml
└── build.gradle
```

## 1. AndroidManifest.xml (key parts)
```xml
<manifest package="com.mdm.agent">
    <uses-permission android:name="android.permission.INTERNET" />
    <uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
    <uses-permission android:name="android.permission.FOREGROUND_SERVICE" />

    <application android:label="MDM Agent" android:icon="@mipmap/ic_launcher">

        <!-- Device Admin Receiver -->
        <receiver android:name=".DeviceAdminReceiver"
            android:permission="android.permission.BIND_DEVICE_ADMIN"
            android:exported="true">
            <meta-data android:name="android.app.device_admin"
                android:resource="@xml/device_admin" />
            <intent-filter>
                <action android:name="android.app.action.DEVICE_ADMIN_ENABLED" />
                <action android:name="android.app.action.PROFILE_PROVISIONING_COMPLETE" />
            </intent-filter>
        </receiver>

        <!-- Enrollment Activity (hidden launcher) -->
        <activity android:name=".EnrollmentActivity"
            android:exported="true"
            android:launchMode="singleTask">
            <intent-filter>
                <action android:name="android.intent.action.MAIN" />
                <category android:name="android.intent.category.LAUNCHER" />
                <category android:name="android.intent.category.HOME" />
                <category android:name="android.intent.category.DEFAULT" />
            </intent-filter>
        </activity>

        <!-- Heartbeat Service -->
        <service android:name=".HeartbeatService"
            android:exported="false"
            android:foregroundServiceType="dataSync" />
    </application>
</manifest>
```

## 2. res/xml/device_admin.xml
```xml
<?xml version="1.0" encoding="utf-8"?>
<device-admin>
    <uses-policies>
        <limit-password />
        <watch-login />
        <reset-password />
        <force-lock />
        <wipe-data />
        <expire-password />
        <encrypted-storage />
        <disable-camera />
        <disable-keyguard-features />
    </uses-policies>
</device-admin>
```

## 3. DeviceAdminReceiver.kt
```kotlin
package com.mdm.agent

import android.app.admin.DeviceAdminReceiver
import android.content.Context
import android.content.Intent

class DeviceAdminReceiver : DeviceAdminReceiver() {
    override fun onProfileProvisioningComplete(context: Context, intent: Intent) {
        // Device Owner setup complete — launch enrollment
        val i = Intent(context, EnrollmentActivity::class.java)
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        context.startActivity(i)
    }
}
```

## 4. PolicyManager.kt — Device Lockdown
```kotlin
package com.mdm.agent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.os.UserManager

class PolicyManager(private val context: Context) {
    private val dpm = context.getSystemService(Context.DEVICE_POLICY_SERVICE) as DevicePolicyManager
    private val admin = ComponentName(context, DeviceAdminReceiver::class.java)

    fun applyPolicy(policy: PolicyConfig) {
        if (!dpm.isDeviceOwnerApp(context.packageName)) return

        // 1. Kiosk mode — lock task whitelist
        if (policy.kioskMode) {
            dpm.setLockTaskPackages(admin, arrayOf(CHAT_APP_PACKAGE))
        }

        // 2. Disable Play Store
        if (policy.disablePlayStore) {
            dpm.setApplicationHidden(admin, "com.android.vending", true)
        }

        // 3. Disable camera
        dpm.setCameraDisabled(admin, policy.disableCamera)

        // 4. Block factory reset
        if (policy.disableFactoryReset) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_FACTORY_RESET)
        }

        // 5. Block USB file transfer
        if (policy.disableUsb) {
            dpm.addUserRestriction(admin, UserManager.DISALLOW_USB_FILE_TRANSFER)
        }

        // 6. Block unknown app installs
        dpm.addUserRestriction(admin, UserManager.DISALLOW_INSTALL_UNKNOWN_SOURCES)

        // 7. Block adding accounts (prevent Google sign-in)
        dpm.addUserRestriction(admin, UserManager.DISALLOW_MODIFY_ACCOUNTS)
    }

    fun launchKioskMode() {
        // Start the chat app in Lock Task Mode
        val intent = context.packageManager.getLaunchIntentForPackage(CHAT_APP_PACKAGE)
        intent?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        context.startActivity(intent)
    }

    companion object {
        const val CHAT_APP_PACKAGE = "com.mdm.chat"
    }
}
```

## 5. HeartbeatService.kt — Background Reporting
```kotlin
package com.mdm.agent

import android.app.Service
import android.content.Intent
import android.os.IBinder
import kotlinx.coroutines.*
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

class HeartbeatService : Service() {
    private val scope = CoroutineScope(Dispatchers.IO)
    private val INTERVAL = 60_000L // 60 seconds

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        scope.launch {
            while (true) {
                try {
                    sendHeartbeat()
                    processCommands()
                } catch (e: Exception) {
                    e.printStackTrace()
                }
                delay(INTERVAL)
            }
        }
        return START_STICKY
    }

    private suspend fun sendHeartbeat() {
        val prefs = getSharedPreferences("mdm", MODE_PRIVATE)
        val imei  = prefs.getString("imei", "") ?: return
        val api   = buildApi()

        val battery = getBatteryLevel()
        val response = api.heartbeat(HeartbeatRequest(
            imei          = imei,
            battery_level = battery,
            ip_address    = getLocalIpAddress(),
            os_version    = android.os.Build.VERSION.RELEASE,
        ))

        // Process returned commands
        response.commands?.forEach { cmd ->
            CommandHandler(this).handle(cmd)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null
}
```

## 6. build.gradle (app) — Dependencies
```gradle
dependencies {
    implementation 'com.squareup.retrofit2:retrofit:2.9.0'
    implementation 'com.squareup.retrofit2:converter-gson:2.9.0'
    implementation 'com.squareup.okhttp3:logging-interceptor:4.11.0'
    implementation 'org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3'
    implementation 'androidx.work:work-runtime-ktx:2.9.0'
}
```

## Enrollment Flow
1. Factory-reset device
2. During Android setup, tap screen **6 times** when it's blank
3. QR code scanner opens → scan the QR from admin panel → `Enrollment` page
4. Android downloads and installs MDM Agent as Device Owner
5. `EnrollmentActivity` opens → calls `/api/enrollment/verify.php` with token + IMEI
6. Server returns policy → `PolicyManager.applyPolicy()` is called
7. Chat App is installed from server → kiosk mode starts
8. HeartbeatService begins running in background every 60s
