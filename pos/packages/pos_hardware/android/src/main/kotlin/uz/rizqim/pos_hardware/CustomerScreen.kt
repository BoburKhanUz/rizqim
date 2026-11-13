package uz.rizqim.pos_hardware

import android.app.Activity
import android.app.Presentation
import android.content.Context
import android.hardware.display.DisplayManager
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.Display
import android.view.WindowManager
import io.flutter.FlutterInjector
import io.flutter.embedding.android.FlutterView
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.engine.dart.DartExecutor
import io.flutter.plugin.common.BasicMessageChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import io.flutter.plugin.common.StringCodec
import org.json.JSONArray
import org.json.JSONException
import org.json.JSONObject

/**
 * The till's customer-facing second screen (Sunmi D2/T2 and other dual-screen Android tills): an Android
 * [Presentation] on the secondary display that hosts a second Flutter engine running the app's
 * `@pragma('vm:entry-point') customerScreenMain` (lib/src/display/customer_screen.dart on the Dart side).
 *
 * Channel `uz.rizqim.pos_hardware/customer_screen` (main engine): `displays`, `show {entrypoint}`, `hide`,
 * `send {message}`. Messages are JSON strings with a `type`; the last one of each type is kept and handed to the
 * second engine when its Dart side says `hello` on `uz.rizqim.pos_hardware/customer_screen/feed`, so a late start
 * still shows the store name and the current cart.
 *
 * The second engine registers no plugins (it only draws what the till sends). The presentation needs the activity
 * window: it is dismissed when the activity goes away and shown again when it (or the display) comes back.
 */
internal class CustomerScreen(private val context: Context) : MethodChannel.MethodCallHandler {
    private val main = Handler(Looper.getMainLooper())
    private val displayManager: DisplayManager?
        get() = context.getSystemService(Context.DISPLAY_SERVICE) as? DisplayManager

    var activity: Activity? = null
        set(value) {
            field = value
            if (value == null) dismiss() else if (wanted) show()
        }

    private var wanted = false
    private var entrypoint = DEFAULT_ENTRYPOINT
    private var engine: FlutterEngine? = null
    private var feed: BasicMessageChannel<String>? = null
    private var presentation: CustomerPresentation? = null
    private var listening = false
    private val last = LinkedHashMap<String, String>()

    private val displayListener = object : DisplayManager.DisplayListener {
        override fun onDisplayAdded(displayId: Int) {
            if (wanted && presentation == null) show()
        }

        override fun onDisplayRemoved(displayId: Int) {
            if (presentation?.display?.displayId == displayId) dismiss()
        }

        override fun onDisplayChanged(displayId: Int) {}
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "displays" -> result.success(
                presentationDisplays().map { d ->
                    mapOf(
                        "id" to d.displayId,
                        "name" to d.name,
                        "width" to d.mode.physicalWidth,
                        "height" to d.mode.physicalHeight,
                    )
                },
            )
            "show" -> {
                entrypoint = call.argument<String>("entrypoint") ?: DEFAULT_ENTRYPOINT
                wanted = true
                listen(true)
                result.success(show())
            }
            "hide" -> {
                wanted = false
                listen(false)
                dismiss()
                destroyEngine()
                result.success(null)
            }
            "send" -> {
                val message = call.argument<String>("message")
                if (message == null) {
                    result.error("failed", "Missing 'message'", null)
                    return
                }
                val type = try {
                    JSONObject(message).optString("type", "")
                } catch (e: JSONException) {
                    ""
                }
                if (type.isNotEmpty()) {
                    last.remove(type)
                    last[type] = message
                }
                feed?.send(message)
                result.success(null)
            }
            else -> result.notImplemented()
        }
    }

    fun dispose() {
        wanted = false
        listen(false)
        dismiss()
        destroyEngine()
    }

    private fun presentationDisplays(): List<Display> =
        displayManager?.getDisplays(DisplayManager.DISPLAY_CATEGORY_PRESENTATION)
            ?.filter { it.displayId != Display.DEFAULT_DISPLAY }
            .orEmpty()

    private fun show(): Boolean {
        presentation?.let { if (it.isShowing) return true }
        val a = activity ?: return false
        if (a.isFinishing) return false
        val display = presentationDisplays().firstOrNull() ?: return false
        val e = engine ?: startEngine()
        val p = CustomerPresentation(a, display, e)
        try {
            p.show()
        } catch (ex: WindowManager.InvalidDisplayException) {
            return false
        } catch (ex: WindowManager.BadTokenException) {
            return false
        }
        presentation = p
        e.lifecycleChannel.appIsResumed()
        return true
    }

    private fun dismiss() {
        val p = presentation ?: return
        presentation = null
        try {
            p.dismiss()
        } catch (ignored: IllegalArgumentException) {
            // The window was already gone with the activity
        }
        engine?.lifecycleChannel?.appIsPaused()
    }

    private fun startEngine(): FlutterEngine {
        val loader = FlutterInjector.instance().flutterLoader()
        if (!loader.initialized()) {
            loader.startInitialization(context.applicationContext)
            loader.ensureInitializationComplete(context.applicationContext, null)
        }
        // No plugins in the second engine: it only draws what the till sends
        val e = FlutterEngine(context.applicationContext, null, false)
        val channel = BasicMessageChannel(e.dartExecutor.binaryMessenger, FEED_CHANNEL, StringCodec.INSTANCE)
        channel.setMessageHandler { message, reply ->
            if (message == HELLO) reply.reply(JSONArray(last.values.toList()).toString()) else reply.reply(null)
        }
        e.dartExecutor.executeDartEntrypoint(
            DartExecutor.DartEntrypoint(loader.findAppBundlePath(), entrypoint),
        )
        feed = channel
        engine = e
        return e
    }

    private fun destroyEngine() {
        feed?.setMessageHandler(null)
        feed = null
        engine?.destroy()
        engine = null
    }

    private fun listen(on: Boolean) {
        val dm = displayManager ?: return
        if (on && !listening) {
            dm.registerDisplayListener(displayListener, main)
            listening = true
        } else if (!on && listening) {
            dm.unregisterDisplayListener(displayListener)
            listening = false
        }
    }

    /** The second screen's window: one FlutterView attached to the customer screen engine. */
    private class CustomerPresentation(
        outer: Context,
        display: Display,
        private val engine: FlutterEngine,
    ) : Presentation(outer, display) {
        private var view: FlutterView? = null

        override fun onCreate(savedInstanceState: Bundle?) {
            super.onCreate(savedInstanceState)
            window?.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
            val v = FlutterView(context)
            v.attachToFlutterEngine(engine)
            setContentView(v)
            view = v
        }

        override fun onStop() {
            view?.detachFromFlutterEngine()
            view = null
            super.onStop()
        }
    }

    companion object {
        const val CHANNEL = "uz.rizqim.pos_hardware/customer_screen"
        const val FEED_CHANNEL = "uz.rizqim.pos_hardware/customer_screen/feed"
        const val DEFAULT_ENTRYPOINT = "customerScreenMain"
        private const val HELLO = "hello"
    }
}
