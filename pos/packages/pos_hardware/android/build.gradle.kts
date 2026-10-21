// P3-15: Sunmi built-in printer (MethodChannel `uz.rizqim.pos_hardware/sunmi`), see lib/src/printer/sunmi_transport.dart.
// Generated from the Flutter 3.47 plugin template; the only addition is the Sunmi printer library.
group = "uz.rizqim.pos_hardware"
version = "1.0-SNAPSHOT"

buildscript {
    val kotlinVersion = "2.4.0"
    repositories {
        google()
        mavenCentral()
    }

    dependencies {
        classpath("com.android.tools.build:gradle:9.1.0")
        classpath("org.jetbrains.kotlin:kotlin-gradle-plugin:$kotlinVersion")
    }
}

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

plugins {
    id("com.android.library")
}

android {
    namespace = "uz.rizqim.pos_hardware"

    compileSdk = 36

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    sourceSets {
        getByName("main") {
            java.srcDirs("src/main/kotlin")
        }
    }

    defaultConfig {
        minSdk = 24
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

dependencies {
    // Sunmi printer service binding (InnerPrinterManager, SunmiPrinterService.sendRAWData)
    implementation("com.sunmi:printerlibrary:1.0.24")
}
