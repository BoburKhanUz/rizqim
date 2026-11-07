package uz.rizqim.pos_hardware

import android.content.Context
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.embedding.engine.plugins.activity.ActivityAware
import io.flutter.embedding.engine.plugins.activity.ActivityPluginBinding
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodChannel

/**
 * The POS hardware plugin: every native device the Dart package `pos_hardware` talks to.
 *
 * | Channel | Class | Dart side |
 * |---|---|---|
 * | `uz.rizqim.pos_hardware/sunmi` | [SunmiPrinter] | lib/src/printer/sunmi_transport.dart |
 * | `uz.rizqim.pos_hardware/bluetooth` | [BluetoothPrinters] | lib/src/printer/native_transport.dart |
 * | `uz.rizqim.pos_hardware/usb` | [UsbPrinters] | lib/src/printer/native_transport.dart |
 * | `uz.rizqim.pos_hardware/scanner` (events) | [ScannerStream] | lib/src/scanner/barcode_scanner.dart |
 * | `uz.rizqim.pos_hardware/customer_screen` | [CustomerScreen] | lib/src/display/customer_screen.dart |
 * | `uz.rizqim.pos_hardware/multicast` | [WifiMulticast] | lib/src/network/wifi_multicast_lock.dart |
 *
 * The activity is needed for the Bluetooth permission dialog and the second-screen window; without one (a background
 * engine) those answer `false` and everything else works.
 */
class PosHardwarePlugin : FlutterPlugin, ActivityAware {
    private val channels = mutableListOf<MethodChannel>()
    private var scannerChannel: EventChannel? = null
    private var sunmi: SunmiPrinter? = null
    private var bluetooth: BluetoothPrinters? = null
    private var usb: UsbPrinters? = null
    private var scanner: ScannerStream? = null
    private var customerScreen: CustomerScreen? = null
    private var multicast: WifiMulticast? = null
    private var permissions: Permissions? = null
    private var activityBinding: ActivityPluginBinding? = null

    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        val context: Context = binding.applicationContext
        val messenger = binding.binaryMessenger
        val perms = Permissions(context)
        permissions = perms
        val s = SunmiPrinter(context).also { sunmi = it }
        val bt = BluetoothPrinters(context, perms).also { bluetooth = it }
        val u = UsbPrinters(context).also { usb = it }
        val screen = CustomerScreen(context).also { customerScreen = it }
        val wifi = WifiMulticast(context).also { multicast = it }
        for ((name, handler) in listOf(
            SunmiPrinter.CHANNEL to s,
            BLUETOOTH_CHANNEL to bt,
            USB_CHANNEL to u,
            CustomerScreen.CHANNEL to screen,
            WifiMulticast.CHANNEL to wifi,
        )) {
            channels.add(MethodChannel(messenger, name).also { it.setMethodCallHandler(handler) })
        }
        val sc = ScannerStream(context).also { scanner = it }
        scannerChannel = EventChannel(messenger, SCANNER_CHANNEL).also { it.setStreamHandler(sc) }
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channels.forEach { it.setMethodCallHandler(null) }
        channels.clear()
        scannerChannel?.setStreamHandler(null)
        scannerChannel = null
        scanner?.stop()
        sunmi?.dispose()
        bluetooth?.dispose()
        usb?.dispose()
        customerScreen?.dispose()
        multicast?.dispose()
        multicast = null
        scanner = null
        sunmi = null
        bluetooth = null
        usb = null
        customerScreen = null
        permissions = null
    }

    override fun onAttachedToActivity(binding: ActivityPluginBinding) = attach(binding)

    override fun onReattachedToActivityForConfigChanges(binding: ActivityPluginBinding) = attach(binding)

    override fun onDetachedFromActivityForConfigChanges() = detach()

    override fun onDetachedFromActivity() = detach()

    private fun attach(binding: ActivityPluginBinding) {
        activityBinding = binding
        permissions?.let {
            it.activity = binding.activity
            binding.addRequestPermissionsResultListener(it)
        }
        customerScreen?.activity = binding.activity
    }

    private fun detach() {
        permissions?.let {
            activityBinding?.removeRequestPermissionsResultListener(it)
            it.activity = null
            it.cancelAll()
        }
        activityBinding = null
        customerScreen?.activity = null
    }

    companion object {
        const val BLUETOOTH_CHANNEL = "uz.rizqim.pos_hardware/bluetooth"
        const val USB_CHANNEL = "uz.rizqim.pos_hardware/usb"
        const val SCANNER_CHANNEL = "uz.rizqim.pos_hardware/scanner"
    }
}
