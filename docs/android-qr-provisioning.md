# Android Enterprise QR Provisioning Payload

To enroll corporate Android devices into your MDM System as **Device Owner (DPC)**, follow the QR code provisioning protocol.

## Step 1: Factory Reset the Device
1. Power on a brand new or factory-reset Android device.
2. At the welcome screen ("Hi there" / Language Selection), **tap the blank space on the screen rapidly 6 times**.
3. The device will search for a Wi-Fi connection, configure itself, and launch the built-in **Android Enterprise QR Code Scanner**.

## Step 2: Scan the QR Code
Generate a QR code using the JSON payload below. You can use the QR generator on the `admin/enrollment.php` page in the MDM Control Center.

### JSON Payload Schema
```json
{
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME": "com.mdm.agent/com.mdm.agent.DeviceAdminReceiver",
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION": "http://192.168.18.59/mdm/apk/mdm-agent.apk",
  "android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM": "",
  "android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED": false,
  "android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE": {
    "enrollment_token": "YOUR_GENERATED_QR_TOKEN"
  }
}
```

---

## JSON Keys Breakdown

| Parameter | Type | Purpose |
|-----------|------|---------|
| `PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME` | String | Tells Android which receiver component inside the APK is the Device Policy Controller (DPC). Needs to point exactly to `com.mdm.agent/com.mdm.agent.DeviceAdminReceiver`. |
| `PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION` | String | The direct URL where Android will download the DPC agent APK during enrollment. Points to your local server IP. |
| `PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM` | String | SHA-256 hash of your signing certificate to verify integrity (can be left blank for local development testing). |
| `PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED` | Boolean | Set to `false` to disable Play Store, Google Search, system settings, and other apps. Set to `true` to keep default device apps active. |
| `PROVISIONING_ADMIN_EXTRAS_BUNDLE` | Object | Arbitrary configuration parameters passed straight to the DPC agent once it is installed and launched. We use it to automatically pass the `enrollment_token` so the device registers silently! |
