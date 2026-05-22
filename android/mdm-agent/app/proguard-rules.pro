# ProGuard configuration for MDM Agent

# Keep our API requests and response model classes to prevent GSON deserialization issues
-keep class com.mdm.agent.api.** { *; }
-keep class com.mdm.agent.PolicyConfig { *; }

# Keep GSON annotations and serialized fields
-keepattributes Signature, InnerClasses, EnclosingMethod, RuntimeVisibleAnnotations, RuntimeVisibleParameterAnnotations, AnnotationDefault
-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}

# Retrofit keep rules
-dontwarn retrofit2.**
-keep class retrofit2.** { *; }
-keepattributes *Annotation*, Signature, InnerClasses, EnclosingMethod
-keepclassmembers,allowshrinking,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}

# OkHttp keep rules
-dontwarn okhttp3.**
-keep class okhttp3.** { *; }

# Gson keep rules
-keep class com.google.gson.** { *; }

# Kotlin coroutines and suspend functions keep rules
-dontwarn kotlin.coroutines.**
-keep class kotlin.coroutines.Continuation { *; }
-keep class kotlinx.coroutines.** { *; }

# Keep Android classes that are accessed via reflection or manifest
-keep class com.mdm.agent.DeviceAdminReceiver { *; }
-keep class com.mdm.agent.HeartbeatService { *; }
-keep class com.mdm.agent.ChatAppInstallWorker { *; }
-keep class com.mdm.agent.MainActivity { *; }

