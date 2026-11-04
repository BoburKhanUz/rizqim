package uz.rizqim.pos_hardware

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.os.Build
import io.flutter.plugin.common.EventChannel

/**
 * Built-in scanners that broadcast what they read (lib/src/scanner/barcode_scanner.dart, BroadcastScanner).
 *
 * EventChannel `uz.rizqim.pos_hardware/scanner`; the listen argument is `{actions: [{action, extra}]}` (Sunmi:
 * `com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED`, the code in `data`, the raw bytes in `source_byte`). Every event is
 * `{code, action}`. The scanner service is another app, so the receiver is exported on Android 13+.
 */
internal class ScannerStream(private val context: Context) : EventChannel.StreamHandler {
    private var receiver: BroadcastReceiver? = null

    override fun onListen(arguments: Any?, events: EventChannel.EventSink) {
        stop()
        val extras = parseActions(arguments)
        if (extras.isEmpty()) return
        val filter = IntentFilter()
        extras.keys.forEach { filter.addAction(it) }
        val r = object : BroadcastReceiver() {
            override fun onReceive(c: Context, intent: Intent) {
                val action = intent.action ?: return
                val extra = extras[action] ?: return
                val code = intent.getStringExtra(extra)
                    ?: intent.getByteArrayExtra("source_byte")?.toString(Charsets.UTF_8)
                    ?: return
                events.success(mapOf("code" to code, "action" to action))
            }
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            context.registerReceiver(r, filter, Context.RECEIVER_EXPORTED)
        } else {
            context.registerReceiver(r, filter)
        }
        receiver = r
    }

    override fun onCancel(arguments: Any?) = stop()

    fun stop() {
        val r = receiver ?: return
        receiver = null
        try {
            context.unregisterReceiver(r)
        } catch (ignored: IllegalArgumentException) {
        }
    }

    private fun parseActions(arguments: Any?): Map<String, String> {
        val list = (arguments as? Map<*, *>)?.get("actions") as? List<*> ?: return mapOf(SUNMI_ACTION to "data")
        val out = LinkedHashMap<String, String>()
        for (item in list) {
            val m = item as? Map<*, *> ?: continue
            val action = m["action"] as? String ?: continue
            out[action] = m["extra"] as? String ?: "data"
        }
        return out
    }

    companion object {
        const val SUNMI_ACTION = "com.sunmi.scanner.ACTION_DATA_CODE_RECEIVED"
    }
}
