package uz.rizqim.pos_hardware

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.bluetooth.BluetoothSocket
import android.content.Context
import android.os.Build
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.IOException
import java.util.UUID
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Classic Bluetooth (SPP / RFCOMM) printers for lib/src/printer/native_transport.dart (BluetoothPrinterTransport) and
 * the discovery screen.
 *
 * Channel `uz.rizqim.pos_hardware/bluetooth`: `state`, `requestPermission`, `bondedDevices`, `send {address, bytes}`,
 * `query {address, bytes, expect, timeout_ms}`, `disconnect {address}`. Only paired (bonded) devices are used: pairing
 * happens in the system Bluetooth settings, so no discovery scan and no location permission are needed.
 *
 * One socket per printer stays open between jobs (an RFCOMM connect takes 1-3 s); a write on a dead socket reconnects
 * once. All socket work runs on one IO thread, so jobs to the same printer never interleave.
 */
internal class BluetoothPrinters(
    private val context: Context,
    private val permissions: Permissions,
) : MethodChannel.MethodCallHandler {
    private val io: ExecutorService = Executors.newSingleThreadExecutor { r ->
        Thread(r, "pos-hardware-bluetooth").apply { isDaemon = true }
    }

    // Touched only on the IO thread
    private val sockets = HashMap<String, BluetoothSocket>()

    private val adapter: BluetoothAdapter?
        get() = (context.getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager)?.adapter

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "state" -> {
                val a = adapter
                result.success(
                    mapOf(
                        "supported" to (a != null),
                        "enabled" to (a != null && safeEnabled(a)),
                        "permission" to hasPermission(),
                    ),
                )
            }
            "requestPermission" -> {
                if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) {
                    result.success(true)
                } else {
                    permissions.request(REQUESTED) { ok -> result.success(ok) }
                }
            }
            "bondedDevices" -> bondedDevices(result)
            "send" -> {
                val address = call.argument<String>("address")
                val bytes = call.argument<ByteArray>("bytes")
                if (address == null || bytes == null) {
                    result.error("failed", "Missing 'address' or 'bytes'", null)
                    return
                }
                val reply = MainReply(result)
                if (!precheck(reply)) return
                io.execute {
                    try {
                        writeWithReconnect(address, bytes)
                        reply.success(null)
                    } catch (e: PrinterIoException) {
                        reply.error(e.code, e.message)
                    } catch (e: SecurityException) {
                        reply.error("permission_denied", e.message)
                    }
                }
            }
            "query" -> {
                val address = call.argument<String>("address")
                val bytes = call.argument<ByteArray>("bytes")
                val expect = call.argument<Int>("expect") ?: 1
                val timeoutMs = (call.argument<Int>("timeout_ms") ?: 600).toLong()
                if (address == null || bytes == null) {
                    result.error("failed", "Missing 'address' or 'bytes'", null)
                    return
                }
                val reply = MainReply(result)
                if (!precheck(reply)) return
                io.execute {
                    try {
                        reply.success(query(address, bytes, expect, timeoutMs))
                    } catch (e: PrinterIoException) {
                        reply.error(e.code, e.message)
                    } catch (e: SecurityException) {
                        reply.error("permission_denied", e.message)
                    }
                }
            }
            "disconnect" -> {
                val address = call.argument<String>("address")
                io.execute { if (address != null) closeSocket(address) }
                result.success(null)
            }
            else -> result.notImplemented()
        }
    }

    fun dispose() {
        io.execute {
            sockets.keys.toList().forEach { closeSocket(it) }
        }
        io.shutdown()
    }

    private fun hasPermission(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.S || permissions.has(CONNECT)

    private fun safeEnabled(a: BluetoothAdapter): Boolean = try {
        a.isEnabled
    } catch (e: SecurityException) {
        false
    }

    /** Adapter, permission and power checks shared by every call; answers the error itself. */
    private fun precheck(reply: MainReply): Boolean {
        val a = adapter
        when {
            a == null -> reply.error("unsupported", "This device has no Bluetooth")
            !hasPermission() -> reply.error("permission_denied", "BLUETOOTH_CONNECT is not granted")
            !safeEnabled(a) -> reply.error("bluetooth_off", "Bluetooth is off")
            else -> return true
        }
        return false
    }

    private fun bondedDevices(result: MethodChannel.Result) {
        val reply = MainReply(result)
        if (!precheck(reply)) return
        try {
            val list = adapter!!.bondedDevices.orEmpty().map { d ->
                mapOf(
                    "name" to d.name,
                    "address" to d.address,
                    "device_class" to d.bluetoothClass?.deviceClass,
                    "major_class" to d.bluetoothClass?.majorDeviceClass,
                    "type" to d.type,
                )
            }
            reply.success(list)
        } catch (e: SecurityException) {
            reply.error("permission_denied", e.message)
        }
    }

    private fun writeWithReconnect(address: String, bytes: ByteArray) {
        val socket = socketFor(address)
        val sent = Written()
        try {
            write(socket, bytes, sent)
        } catch (first: IOException) {
            closeSocket(address)
            // Review P6 C7: part of the job reached the printer: sending it all again prints a second, partial copy.
            // `write_partial` is not retried by the queue; the cashier sees the warning and retries by hand.
            if (sent.bytes > 0) throw PrinterIoException("write_partial", first.message)
            // Nothing went out (the printer was switched off and on, or went out of range): one fresh connection
            val again = socketFor(address)
            val resent = Written()
            try {
                write(again, bytes, resent)
            } catch (e: IOException) {
                closeSocket(address)
                throw PrinterIoException(if (resent.bytes > 0) "write_partial" else "write_failed", e.message)
            }
        }
    }

    /** How many bytes of a job went out in whole chunks before a failure. */
    private class Written {
        var bytes = 0
    }

    private fun write(socket: BluetoothSocket, bytes: ByteArray, sent: Written = Written()) {
        val out = socket.outputStream
        var offset = 0
        while (offset < bytes.size) {
            val n = minOf(CHUNK, bytes.size - offset)
            out.write(bytes, offset, n)
            offset += n
            sent.bytes = offset
        }
        out.flush()
    }

    /** Writes a status request and collects up to [expect] answer bytes within [timeoutMs]; null when none came. */
    private fun query(address: String, request: ByteArray, expect: Int, timeoutMs: Long): ByteArray? {
        val socket = socketFor(address)
        try {
            val input = socket.inputStream
            // Drop whatever the printer sent before (an earlier late answer)
            while (input.available() > 0) input.read(ByteArray(input.available()))
            write(socket, request)
            val answer = java.io.ByteArrayOutputStream()
            val deadline = System.currentTimeMillis() + timeoutMs
            while (answer.size() < expect && System.currentTimeMillis() < deadline) {
                val available = input.available()
                if (available > 0) {
                    val buf = ByteArray(available)
                    val n = input.read(buf)
                    if (n > 0) answer.write(buf, 0, n)
                } else {
                    Thread.sleep(POLL_MS)
                }
            }
            return if (answer.size() == 0) null else answer.toByteArray()
        } catch (e: IOException) {
            closeSocket(address)
            throw PrinterIoException("connect_failed", e.message)
        }
    }

    private fun socketFor(address: String): BluetoothSocket {
        val open = sockets[address]
        if (open != null && open.isConnected) return open
        if (open != null) closeSocket(address)
        return connect(address)
    }

    private fun connect(address: String): BluetoothSocket {
        val a = adapter ?: throw PrinterIoException("unsupported", "This device has no Bluetooth")
        if (!BluetoothAdapter.checkBluetoothAddress(address)) {
            throw PrinterIoException("not_found", "Not a Bluetooth address: $address")
        }
        val device = a.getRemoteDevice(address)
        if (device.bondState != BluetoothDevice.BOND_BONDED) {
            throw PrinterIoException("not_found", "$address is not paired")
        }
        try {
            // A running discovery slows RFCOMM connects down a lot
            a.cancelDiscovery()
        } catch (ignored: SecurityException) {
        }
        var last: Exception? = null
        for (open in SOCKET_FACTORIES) {
            val socket = try {
                open(device)
            } catch (e: Exception) {
                last = e
                continue
            }
            try {
                socket.connect()
                sockets[address] = socket
                return socket
            } catch (e: IOException) {
                last = e
                try {
                    socket.close()
                } catch (ignored: IOException) {
                }
            }
        }
        throw PrinterIoException("connect_failed", last?.message)
    }

    private fun closeSocket(address: String) {
        val s = sockets.remove(address) ?: return
        try {
            s.close()
        } catch (ignored: IOException) {
        }
    }

    companion object {
        /** Serial Port Profile: what Bluetooth receipt printers speak. */
        private val SPP: UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB")
        private const val CHUNK = 1024
        private const val POLL_MS = 15L

        private val CONNECT = arrayOf(Manifest.permission.BLUETOOTH_CONNECT)

        // BLUETOOTH_SCAN only lets cancelDiscovery() work; the "Nearby devices" dialog grants both together
        private val REQUESTED = arrayOf(Manifest.permission.BLUETOOTH_CONNECT, Manifest.permission.BLUETOOTH_SCAN)

        /** Secure SPP, insecure SPP (printers without authentication), then the hidden channel-1 socket. */
        private val SOCKET_FACTORIES: List<(BluetoothDevice) -> BluetoothSocket> = listOf(
            { d -> d.createRfcommSocketToServiceRecord(SPP) },
            { d -> d.createInsecureRfcommSocketToServiceRecord(SPP) },
            { d ->
                d.javaClass.getMethod("createRfcommSocket", Int::class.javaPrimitiveType)
                    .invoke(d, 1) as BluetoothSocket
            },
        )
    }
}
