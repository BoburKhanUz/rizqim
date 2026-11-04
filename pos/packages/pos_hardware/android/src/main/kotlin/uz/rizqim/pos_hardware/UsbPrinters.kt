package uz.rizqim.pos_hardware

import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.hardware.usb.UsbConstants
import android.hardware.usb.UsbDevice
import android.hardware.usb.UsbDeviceConnection
import android.hardware.usb.UsbEndpoint
import android.hardware.usb.UsbInterface
import android.hardware.usb.UsbManager
import android.os.Build
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.util.Locale
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * USB OTG printers (Android as the USB host) for lib/src/printer/native_transport.dart (UsbPrinterTransport).
 *
 * Channel `uz.rizqim.pos_hardware/usb`: `devices`, `requestPermission {device_id}`, `send {device_id, bytes}`,
 * `query {device_id, bytes, expect, timeout_ms}`. A `device_id` is `vvvv:pppp` (hex vendor and product id, stable
 * across replugs), `vvvv:pppp:<serial>`, a system device name (`/dev/bus/usb/001/002`) or empty for the first
 * printer. Printers of the USB printer class (7) come first; vendor-class ESC/POS printers (0xFF with a bulk OUT
 * endpoint, common for cheap 58/80 mm printers) work the same way.
 *
 * Each job opens the device, claims the interface, writes in bulk chunks and closes again, so a replugged printer
 * needs nothing but its permission (asked once per plug; the cashier's manifest also lets the user tick "always").
 */
internal class UsbPrinters(private val context: Context) : MethodChannel.MethodCallHandler {
    private val io: ExecutorService = Executors.newSingleThreadExecutor { r ->
        Thread(r, "pos-hardware-usb").apply { isDaemon = true }
    }

    // device name -> callbacks waiting for the permission dialog (main thread)
    private val pending = HashMap<String, MutableList<(Boolean) -> Unit>>()
    private var receiverRegistered = false

    private val manager: UsbManager?
        get() = context.getSystemService(Context.USB_SERVICE) as? UsbManager

    private val permissionReceiver = object : BroadcastReceiver() {
        override fun onReceive(c: Context, intent: Intent) {
            if (intent.action != ACTION_USB_PERMISSION) return
            val m = manager ?: return
            // Answer every waiting device by its current permission (the extra device is not always filled in)
            val devices = m.deviceList
            for (name in pending.keys.toList()) {
                val device = devices[name]
                val granted = device != null && m.hasPermission(device)
                pending.remove(name)?.forEach { it(granted) }
            }
        }
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        val m = manager
        if (m == null) {
            result.error("unsupported", "This device is not a USB host", null)
            return
        }
        when (call.method) {
            "devices" -> result.success(m.deviceList.values.map { describe(m, it) })
            "requestPermission" -> {
                val device = resolve(m, call.argument<String>("device_id") ?: "")
                if (device == null) {
                    result.error("not_found", "No such USB device", null)
                    return
                }
                requestPermission(m, device) { ok -> result.success(ok) }
            }
            "send", "query" -> {
                val id = call.argument<String>("device_id") ?: ""
                val bytes = call.argument<ByteArray>("bytes")
                if (bytes == null) {
                    result.error("failed", "Missing 'bytes'", null)
                    return
                }
                val device = resolve(m, id)
                if (device == null) {
                    result.error("not_found", "USB printer '$id' is not connected", null)
                    return
                }
                if (!m.hasPermission(device)) {
                    result.error("permission_denied", "No USB permission for ${device.deviceName}", null)
                    return
                }
                val reply = MainReply(result)
                val expect = call.argument<Int>("expect") ?: 1
                val timeoutMs = call.argument<Int>("timeout_ms") ?: 600
                val isQuery = call.method == "query"
                io.execute {
                    try {
                        reply.success(transfer(m, device, bytes, if (isQuery) expect else 0, timeoutMs))
                    } catch (e: PrinterIoException) {
                        reply.error(e.code, e.message)
                    } catch (e: SecurityException) {
                        reply.error("permission_denied", e.message)
                    }
                }
            }
            else -> result.notImplemented()
        }
    }

    fun dispose() {
        if (receiverRegistered) {
            try {
                context.unregisterReceiver(permissionReceiver)
            } catch (ignored: IllegalArgumentException) {
            }
            receiverRegistered = false
        }
        val waiting = pending.values.flatten()
        pending.clear()
        waiting.forEach { it(false) }
        io.shutdown()
    }

    private fun describe(m: UsbManager, d: UsbDevice): Map<String, Any?> {
        val permitted = m.hasPermission(d)
        return mapOf(
            "device_id" to keyOf(d),
            "device_name" to d.deviceName,
            "vendor_id" to d.vendorId,
            "product_id" to d.productId,
            "manufacturer" to d.manufacturerName,
            "product" to d.productName,
            // Reading the serial number needs the permission on Android 10+
            "serial" to (if (permitted) safeSerial(d) else null),
            "printer_class" to isPrinterClass(d),
            "bulk_out" to (bulkOut(d) != null),
            "has_permission" to permitted,
        )
    }

    private fun requestPermission(m: UsbManager, device: UsbDevice, done: (Boolean) -> Unit) {
        if (m.hasPermission(device)) {
            done(true)
            return
        }
        if (!receiverRegistered) {
            val filter = IntentFilter(ACTION_USB_PERMISSION)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                context.registerReceiver(permissionReceiver, filter, Context.RECEIVER_NOT_EXPORTED)
            } else {
                context.registerReceiver(permissionReceiver, filter)
            }
            receiverRegistered = true
        }
        val waiting = pending.getOrPut(device.deviceName) { mutableListOf() }
        waiting.add(done)
        if (waiting.size > 1) return // the dialog is already up
        // The system fills in the device and the answer, so the intent must be mutable (Android 12+) and, being
        // mutable, explicit (Android 14+)
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0
        val intent = Intent(ACTION_USB_PERMISSION).setPackage(context.packageName)
        val pi = PendingIntent.getBroadcast(context, device.deviceId, intent, flags)
        m.requestPermission(device, pi)
    }

    /** Writes [bytes]; with [expect] > 0 then reads up to that many answer bytes (null when the printer stays quiet). */
    private fun transfer(m: UsbManager, device: UsbDevice, bytes: ByteArray, expect: Int, timeoutMs: Int): ByteArray? {
        val (intf, out) = bulkOut(device) ?: throw PrinterIoException("not_found", "No bulk OUT endpoint")
        val connection: UsbDeviceConnection = m.openDevice(device)
            ?: throw PrinterIoException("connect_failed", "openDevice failed")
        try {
            if (!connection.claimInterface(intf, true)) {
                throw PrinterIoException("connect_failed", "claimInterface failed")
            }
            try {
                var offset = 0
                while (offset < bytes.size) {
                    val length = minOf(CHUNK, bytes.size - offset)
                    val n = connection.bulkTransfer(out, bytes, offset, length, WRITE_TIMEOUT_MS)
                    if (n < 0) throw PrinterIoException("write_failed", "bulkTransfer returned $n")
                    offset += n
                }
                if (expect <= 0) return null
                val input = bulkIn(intf) ?: return null
                val answer = java.io.ByteArrayOutputStream()
                val buf = ByteArray(maxOf(64, input.maxPacketSize))
                val deadline = System.currentTimeMillis() + timeoutMs
                while (answer.size() < expect) {
                    val left = (deadline - System.currentTimeMillis()).toInt()
                    if (left <= 0) break
                    val n = connection.bulkTransfer(input, buf, buf.size, left)
                    if (n > 0) answer.write(buf, 0, n)
                }
                return if (answer.size() == 0) null else answer.toByteArray()
            } finally {
                connection.releaseInterface(intf)
            }
        } finally {
            connection.close()
        }
    }

    companion object {
        private const val ACTION_USB_PERMISSION = "uz.rizqim.pos_hardware.USB_PERMISSION"
        private const val CHUNK = 16 * 1024
        private const val WRITE_TIMEOUT_MS = 5000

        fun keyOf(d: UsbDevice): String = String.format(Locale.US, "%04x:%04x", d.vendorId, d.productId)

        private fun safeSerial(d: UsbDevice): String? = try {
            d.serialNumber
        } catch (e: SecurityException) {
            null
        }

        private fun isPrinterClass(d: UsbDevice): Boolean {
            if (d.deviceClass == UsbConstants.USB_CLASS_PRINTER) return true
            for (i in 0 until d.interfaceCount) {
                if (d.getInterface(i).interfaceClass == UsbConstants.USB_CLASS_PRINTER) return true
            }
            return false
        }

        /** The interface and bulk OUT endpoint to print through: a printer-class interface first. */
        private fun bulkOut(d: UsbDevice): Pair<UsbInterface, UsbEndpoint>? {
            val interfaces = (0 until d.interfaceCount).map { d.getInterface(it) }
                .sortedBy { if (it.interfaceClass == UsbConstants.USB_CLASS_PRINTER) 0 else 1 }
            for (intf in interfaces) {
                for (e in 0 until intf.endpointCount) {
                    val ep = intf.getEndpoint(e)
                    if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_OUT) {
                        return intf to ep
                    }
                }
            }
            return null
        }

        private fun bulkIn(intf: UsbInterface): UsbEndpoint? {
            for (e in 0 until intf.endpointCount) {
                val ep = intf.getEndpoint(e)
                if (ep.type == UsbConstants.USB_ENDPOINT_XFER_BULK && ep.direction == UsbConstants.USB_DIR_IN) {
                    return ep
                }
            }
            return null
        }

        /** Finds the device a `device_id` names (see the class comment). */
        fun resolve(m: UsbManager, id: String): UsbDevice? {
            val devices = m.deviceList.values.toList()
            if (id.isEmpty()) {
                return devices.firstOrNull { isPrinterClass(it) } ?: devices.firstOrNull { bulkOut(it) != null }
            }
            if (id.startsWith("/")) return m.deviceList[id]
            val parts = id.lowercase(Locale.US).split(':')
            if (parts.size < 2) return null
            val vendor = parts[0].toIntOrNull(16) ?: return null
            val product = parts[1].toIntOrNull(16) ?: return null
            val serial = parts.getOrNull(2)
            val same = devices.filter { it.vendorId == vendor && it.productId == product }
            if (serial != null) {
                same.firstOrNull { m.hasPermission(it) && safeSerial(it)?.lowercase(Locale.US) == serial }
                    ?.let { return it }
            }
            return same.firstOrNull()
        }
    }
}
