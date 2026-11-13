package uz.rizqim.pos_hardware

import android.os.Handler
import android.os.Looper
import io.flutter.plugin.common.MethodChannel

/**
 * A failure with one of the channel error codes the Dart side maps (lib/src/printer/native_transport.dart):
 * `unsupported`, `permission_denied`, `bluetooth_off`, `not_found`, `connect_failed`, `write_failed` (nothing reached
 * the printer), `write_partial` (part of the job did: never resent automatically, review P6 C7), `timeout`, `failed`.
 */
internal class PrinterIoException(val code: String, message: String?) : Exception(message)

/** Answers a Dart call exactly once, on the main thread (results may come from an IO thread). */
internal class MainReply(private val result: MethodChannel.Result) {
    private var done = false

    fun success(value: Any?) = post { result.success(value) }

    fun error(code: String, message: String?, details: Any? = null) = post { result.error(code, message, details) }

    private fun post(block: () -> Unit) {
        MAIN.post {
            if (!done) {
                done = true
                block()
            }
        }
    }

    companion object {
        private val MAIN = Handler(Looper.getMainLooper())
    }
}
