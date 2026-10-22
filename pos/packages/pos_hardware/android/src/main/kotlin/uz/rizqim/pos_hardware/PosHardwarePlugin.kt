package uz.rizqim.pos_hardware

import android.content.Context
import android.os.Handler
import android.os.Looper
import com.sunmi.peripheral.printer.InnerPrinterCallback
import com.sunmi.peripheral.printer.InnerPrinterException
import com.sunmi.peripheral.printer.InnerPrinterManager
import com.sunmi.peripheral.printer.InnerResultCallback
import com.sunmi.peripheral.printer.SunmiPrinterService
import io.flutter.embedding.engine.plugins.FlutterPlugin
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Sunmi built-in printer for the Dart side in lib/src/printer/sunmi_transport.dart.
 *
 * Channel `uz.rizqim.pos_hardware/sunmi`: `bind` -> Boolean, `status` -> Int (updatePrinterState), `sendRaw`
 * {bytes: ByteArray} -> null, `openDrawer` -> null, `info` -> Map. Errors: `unavailable` (service not bound or gone),
 * `printer_error` (details = printer state code, e.g. 4 = out of paper), `failed`.
 *
 * ESC/POS bytes go through SunmiPrinterService.sendRAWData, so receipts, kitchen tickets, QR codes and raster images
 * are rendered by the same Dart code as for LAN printers.
 */
class PosHardwarePlugin : FlutterPlugin, MethodChannel.MethodCallHandler {
    private lateinit var channel: MethodChannel
    private lateinit var context: Context
    private val main = Handler(Looper.getMainLooper())

    @Volatile
    private var service: SunmiPrinterService? = null
    private val pendingBinds = mutableListOf<MethodChannel.Result>()
    private var binding = false

    private val printerCallback = object : InnerPrinterCallback() {
        override fun onConnected(service: SunmiPrinterService) {
            this@PosHardwarePlugin.service = service
            main.post { finishBind(true) }
        }

        override fun onDisconnected() {
            service = null
            main.post { finishBind(false) }
        }
    }

    override fun onAttachedToEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        context = binding.applicationContext
        channel = MethodChannel(binding.binaryMessenger, CHANNEL)
        channel.setMethodCallHandler(this)
    }

    override fun onDetachedFromEngine(binding: FlutterPlugin.FlutterPluginBinding) {
        channel.setMethodCallHandler(null)
        if (service != null) {
            try {
                InnerPrinterManager.getInstance().unBindService(context, printerCallback)
            } catch (ignored: InnerPrinterException) {
            }
        }
        service = null
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "bind" -> bind(result)
            "status" -> withService(result) { s -> result.success(s.updatePrinterState()) }
            "sendRaw" -> {
                val bytes = call.argument<ByteArray>("bytes")
                if (bytes == null) {
                    result.error("failed", "Missing 'bytes'", null)
                    return
                }
                withService(result) { s -> s.sendRAWData(bytes, replyOnce(result, checkState = true)) }
            }
            "openDrawer" -> withService(result) { s -> s.openDrawer(replyOnce(result, checkState = false)) }
            "info" -> withService(result) { s ->
                result.success(
                    mapOf(
                        "model" to s.printerModal,
                        "serial" to s.printerSerialNo,
                        "version" to s.printerVersion,
                        // getPrinterPaper(): 1 = 58 mm, 2 = 80 mm
                        "paper" to (if (s.printerPaper == 1) 58 else 80),
                    ),
                )
            }
            else -> result.notImplemented()
        }
    }

    private fun bind(result: MethodChannel.Result) {
        if (service != null) {
            result.success(true)
            return
        }
        pendingBinds.add(result)
        if (binding) return
        binding = true
        val started = try {
            InnerPrinterManager.getInstance().bindService(context, printerCallback)
        } catch (ignored: InnerPrinterException) {
            false
        }
        if (!started) {
            // not a Sunmi device: the printer service does not exist
            finishBind(false)
        } else {
            main.postDelayed({ if (binding) finishBind(service != null) }, BIND_TIMEOUT_MS)
        }
    }

    private fun finishBind(ok: Boolean) {
        binding = false
        val waiting = pendingBinds.toList()
        pendingBinds.clear()
        waiting.forEach { it.success(ok) }
    }

    private inline fun withService(result: MethodChannel.Result, block: (SunmiPrinterService) -> Unit) {
        val s = service
        if (s == null) {
            result.error("unavailable", "Sunmi printer service is not bound", null)
            return
        }
        try {
            block(s)
        } catch (e: Exception) {
            // RemoteException: the service died; Dart binds again on the next call
            service = null
            result.error("unavailable", e.message, null)
        }
    }

    /** A Sunmi callback that answers the Dart call exactly once, on the main thread. */
    private fun replyOnce(result: MethodChannel.Result, checkState: Boolean): InnerResultCallback {
        val replied = AtomicBoolean(false)
        fun reply(block: () -> Unit) {
            if (replied.compareAndSet(false, true)) main.post(block)
        }
        return object : InnerResultCallback() {
            override fun onRunResult(isSuccess: Boolean) {
                if (!isSuccess) {
                    reply { result.error("failed", "Printer refused the command", null) }
                    return
                }
                val state = if (checkState) service?.updatePrinterState() ?: 1 else 1
                if (state in ERROR_STATES) {
                    reply { result.error("printer_error", "Printer state $state", state) }
                } else {
                    reply { result.success(null) }
                }
            }

            override fun onReturnString(value: String?) {}

            override fun onRaiseException(code: Int, msg: String?) {
                reply { result.error("failed", msg ?: "Sunmi error $code", code) }
            }

            override fun onPrintResult(code: Int, msg: String?) {
                if (code == 0) {
                    reply { result.success(null) }
                } else {
                    reply { result.error("failed", msg ?: "Print result $code", code) }
                }
            }
        }
    }

    companion object {
        const val CHANNEL = "uz.rizqim.pos_hardware/sunmi"
        const val BIND_TIMEOUT_MS = 3000L

        // updatePrinterState(): 3 communication error, 4 out of paper, 5 overheated, 6 cover open, 7 cutter error,
        // 505 no printer, 507 firmware update failed
        val ERROR_STATES = setOf(3, 4, 5, 6, 7, 505, 507)
    }
}
