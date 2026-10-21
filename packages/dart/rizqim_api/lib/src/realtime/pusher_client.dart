import 'dart:async';
import 'dart:convert';

import 'backoff.dart';
import 'realtime_socket.dart';

/// Ulanish holati.
enum RealtimeStatus {
  /// [PusherClient.connect] hali chaqirilmagan yoki [PusherClient.disconnect] qilingan.
  idle,

  /// Birinchi ulanish.
  connecting,

  /// Ulangan (`pusher:connection_established` keldi).
  connected,

  /// Ulanish uzildi, [Backoff] bo'yicha qayta urinilmoqda.
  reconnecting,
}

/// Kanalga obuna holati ([PusherClient.channelStates]).
enum RealtimeSubscriptionState {
  /// Obuna so'ralmoqda (yoki ulanish yo'q: ulangach so'raladi).
  pending,

  /// Server `pusher_internal:subscription_succeeded` yubordi: kanal hodisalari keladi.
  subscribed,

  /// Imzo olinmadi (403, 429, tarmoq) yoki server `pusher:subscription_error` yubordi. Obuna [Backoff] bo'yicha
  /// qayta so'raladi; shu orada kanal hodisalari kelmaydi (ilova polling'ga tayanishi kerak).
  failed,
}

/// Bitta kanal obunasining o'zgargan holati.
class RealtimeChannelState {
  const RealtimeChannelState(this.channel, this.state, {this.error});

  final String channel;
  final RealtimeSubscriptionState state;

  /// [RealtimeSubscriptionState.failed] da sabab ([RealtimeSubscriptionError.cause]).
  final Object? error;

  @override
  String toString() => 'RealtimeChannelState($channel, ${state.name})';
}

/// Kanal hodisasi (`order.created`, `kitchen.item_status_changed` ...). [data] — hodisa tanasi.
class RealtimeEvent {
  const RealtimeEvent({
    required this.channel,
    required this.event,
    required this.data,
  });

  final String channel;
  final String event;
  final Map<String, Object?> data;

  @override
  String toString() => 'RealtimeEvent($channel, $event)';
}

/// Private kanal imzosi: `POST /api/broadcasting/auth` dan `auth` qiymati.
typedef ChannelAuthorizer = Future<String> Function(
  String socketId,
  String channelName,
);

/// Laravel Reverb (Pusher protokoli 7) klienti: bitta WebSocket, bir nechta kanal, uzilsa o'zi qayta ulanadi.
///
/// Nega o'zimizniki: bizga Pusher'ning kichik qismi kerak (ulanish, private kanalga imzo bilan obuna, ping/pong,
/// hodisalar), lekin imzo **terminal tokeni** bilan olinishi, qayta ulanish oralig'i, jim ulanishni aniqlash va
/// testlarda soxta ulanish (`RealtimeConnector`) bizning qo'limizda bo'lishi kerak. Ulanishning o'zi
/// `web_socket_channel` (Dart jamoasi) orqali.
///
/// * Ulanish uzilsa (yoki server `pusher:error` yuborsa) [Backoff] bo'yicha (1, 2, 4 ... 30 s) qayta ulanadi; muvaffaqiyatli
///   ulanishdan keyin hisob nolga tushadi. Hech qachon taslim bo'lmaydi: oshxona ekrani qarovsiz turadi.
/// * Server `activity_timeout` soniya jim tursa `pusher:ping` yuboriladi; [pongTimeout] ichida javob bo'lmasa ulanish
///   o'lgan deb hisoblanadi (Wi-Fi uzilganda TCP buni sezmasligi mumkin).
/// * Qayta ulanganda hamma kanallarga qayta obuna bo'ladi. [RealtimeStatus.connected] faqat ulanish (handshake) degani:
///   kanal hodisalari obuna tasdiqlangandan keyin keladi. Shuning uchun ilova "jonli" holatni va qayta ulangandagi
///   to'liq qayta yuklashni [channelStates] dagi [RealtimeSubscriptionState.subscribed] bo'yicha qiladi
///   ([isSubscribed]): uzilish paytidagi hodisalar yo'qolgan (docs/API.md, 8-bo'lim).
/// * Imzo olinmasa (tarmoq, 403, 429) yoki server obunani rad etsa kanal [RealtimeSubscriptionState.failed] bo'ladi va
///   obuna [Backoff] bo'yicha qayta so'raladi (ulanish uzilmaydi).
/// * Ulanish [connectTimeout] ichida ochilmasa urinish tashlanadi; kech ochilgan ulanish darhol yopiladi (osilib qolgan
///   TCP ulanishlar to'planmaydi). [disconnect] va [dispose] kutilayotgan urinishni ham bekor qiladi.
class PusherClient {
  PusherClient({
    required this.uri,
    required this.connector,
    required this.authorize,
    this.headers = const <String, String>{},
    this.backoff = const Backoff(),
    this.pongTimeout = const Duration(seconds: 30),
    this.connectTimeout = const Duration(seconds: 20),
    Duration defaultActivityTimeout = const Duration(seconds: 120),
  }) : _activityTimeout = defaultActivityTimeout;

  /// `wss://<domen>/app/<REVERB_APP_KEY>?protocol=7&client=...` ([realtimeUri]).
  final Uri uri;

  /// WebSocket so'roviga qo'shimcha sarlavhalar (masalan `Origin`: Reverb `allowed_origins` ni tekshiradi).
  final Map<String, String> headers;
  final Backoff backoff;
  final Duration pongTimeout;
  final Duration connectTimeout;

  /// Ulanish ochuvchi (ilovada [ioRealtimeConnector], testlarda soxta).
  final RealtimeConnector connector;

  /// Private kanal imzosini oluvchi (masalan, [ChannelAuth.terminal]).
  final ChannelAuthorizer authorize;

  final StreamController<RealtimeEvent> _events =
      StreamController<RealtimeEvent>.broadcast();
  final StreamController<RealtimeStatus> _statuses =
      StreamController<RealtimeStatus>.broadcast();
  final StreamController<Object> _errors = StreamController<Object>.broadcast();
  final StreamController<RealtimeChannelState> _channelStates =
      StreamController<RealtimeChannelState>.broadcast();

  final Set<String> _channels = <String>{};
  final Set<String> _subscribed = <String>{};
  final Map<String, Timer> _subscribeRetries = <String, Timer>{};
  final Map<String, int> _subscribeAttempts = <String, int>{};
  final Map<String, RealtimeSubscriptionState> _states =
      <String, RealtimeSubscriptionState>{};

  RealtimeStatus _status = RealtimeStatus.idle;
  RealtimeSocket? _socket;
  StreamSubscription<String>? _socketSubscription;
  String? _socketId;
  Duration _activityTimeout;
  Timer? _activityTimer;
  Timer? _pongTimer;
  Timer? _reconnectTimer;

  /// Kutilayotgan ulanish urinishining muddati va natijasi ([disconnect] bekor qiladi).
  Timer? _connectTimer;
  Completer<RealtimeSocket>? _connecting;
  int _attempt = 0;
  bool _running = false;

  /// Ulanishni almashtirganda eski ulanishning kech kelgan xabarlari e'tiborsiz qolishi uchun.
  int _generation = 0;

  /// Necha marta ulanishga urinildi (testlar va diagnostika).
  int connectAttempts = 0;

  RealtimeStatus get status => _status;
  Stream<RealtimeStatus> get statuses => _statuses.stream;
  Stream<RealtimeEvent> get events => _events.stream;

  /// Ulanish, imzo va server xatolari (log uchun; ulanish o'zi qayta urinadi).
  Stream<Object> get errors => _errors.stream;

  String? get socketId => _socketId;

  /// Obunasi tasdiqlangan kanallar.
  Set<String> get subscribedChannels => Set<String>.unmodifiable(_subscribed);

  /// Kanal obunasi holatining o'zgarishlari (ulanish uzilsa hamma kanal yana [RealtimeSubscriptionState.pending]).
  Stream<RealtimeChannelState> get channelStates => _channelStates.stream;

  /// [channel] ning joriy obuna holati ([subscribe] qilinmagan bo'lsa `null`).
  RealtimeSubscriptionState? channelState(String channel) =>
      _channels.contains(channel)
      ? (_states[channel] ?? RealtimeSubscriptionState.pending)
      : null;

  /// Ulangan va [channel] obunasi tasdiqlangan: kanal hodisalari keladi.
  bool isSubscribed(String channel) =>
      _status == RealtimeStatus.connected && _subscribed.contains(channel);

  /// Kanalga obuna (ulangan bo'lsa darhol, bo'lmasa ulangach). Qayta ulanishda o'zi takrorlanadi.
  void subscribe(String channel) {
    if (!_channels.add(channel)) return;
    if (_status == RealtimeStatus.connected) unawaited(_subscribe(channel));
  }

  void unsubscribe(String channel) {
    if (!_channels.remove(channel)) return;
    _subscribeRetries.remove(channel)?.cancel();
    _subscribeAttempts.remove(channel);
    _states.remove(channel);
    if (_subscribed.remove(channel)) {
      _send('pusher:unsubscribe', <String, Object?>{'channel': channel});
    }
  }

  void connect() {
    if (_running) return;
    _running = true;
    _attempt = 0;
    unawaited(_open());
  }

  Future<void> disconnect() async {
    _running = false;
    _reconnectTimer?.cancel();
    for (final Timer t in _subscribeRetries.values) {
      t.cancel();
    }
    _subscribeRetries.clear();
    _drop();
    _setStatus(RealtimeStatus.idle);
  }

  Future<void> dispose() async {
    await disconnect();
    await _events.close();
    await _statuses.close();
    await _errors.close();
    await _channelStates.close();
  }

  // --- ulanish ---------------------------------------------------------------------------------------------------

  Future<void> _open() async {
    if (!_running) return;
    final int generation = ++_generation;
    _setStatus(
      _status == RealtimeStatus.idle
          ? RealtimeStatus.connecting
          : RealtimeStatus.reconnecting,
    );
    connectAttempts++;
    final RealtimeSocket socket;
    try {
      socket = await _connectWithDeadline();
    } on Object catch (e) {
      if (generation != _generation) return;
      _error(e);
      _scheduleReconnect();
      return;
    }
    if (!_running || generation != _generation) {
      unawaited(socket.close());
      return;
    }
    _socket = socket;
    _socketSubscription = socket.messages.listen(
      (String m) => _onMessage(generation, m),
      onError: (Object e) {
        if (generation != _generation) return;
        _error(e);
        _lost();
      },
      onDone: () {
        if (generation != _generation) return;
        _lost();
      },
      cancelOnError: true,
    );
    // Server `connection_established` yubormasa ham jim ulanish aniqlanadi
    _armActivityTimer();
  }

  /// [connector] ni [connectTimeout] bilan chaqiradi. Muddat o'tgach (yoki [_drop] da) urinish tashlanadi, lekin
  /// connector'ning o'zi kechroq ulanib qolsa, o'sha ulanish darhol yopiladi: aks holda har osilgan urinish bitta TCP
  /// ulanishni ochiq qoldirardi (`Future.timeout` asl natijani tashlab yuboradi).
  Future<RealtimeSocket> _connectWithDeadline() {
    final Completer<RealtimeSocket> done = Completer<RealtimeSocket>();
    final Future<RealtimeSocket> attempt;
    try {
      attempt = connector(uri, headers);
    } on Object catch (e, s) {
      return Future<RealtimeSocket>.error(e, s);
    }
    _connecting = done;
    _connectTimer?.cancel();
    _connectTimer = Timer(connectTimeout, () {
      if (done.isCompleted) return;
      done.completeError(
        TimeoutException('Realtime: ulanish ochilmadi', connectTimeout),
      );
    });
    attempt.then(
      (RealtimeSocket socket) {
        if (done.isCompleted) {
          // Kech keldi: urinish allaqachon tashlangan
          unawaited(_closeQuietly(null, socket));
          return;
        }
        _connectTimer?.cancel();
        done.complete(socket);
      },
      onError: (Object e, StackTrace s) {
        if (done.isCompleted) return;
        _connectTimer?.cancel();
        done.completeError(e, s);
      },
    );
    return done.future.whenComplete(() {
      if (identical(_connecting, done)) _connecting = null;
    });
  }

  void _lost() {
    _drop();
    if (_running) _scheduleReconnect();
  }

  /// Joriy ulanishni tashlaydi: holat darhol tozalanadi, soket fonda yopiladi.
  void _drop() {
    _generation++;
    _connectTimer?.cancel();
    final Completer<RealtimeSocket>? connecting = _connecting;
    _connecting = null;
    if (connecting != null && !connecting.isCompleted) {
      // Urinish tashlandi; connector kechroq ulansa, ulanish [_connectWithDeadline] da yopiladi
      connecting.completeError(StateError('Realtime: ulanish bekor qilindi'));
    }
    _activityTimer?.cancel();
    _pongTimer?.cancel();
    _pongTimer = null;
    _socketId = null;
    _subscribed.clear();
    for (final String c in _channels) {
      _setChannelState(c, RealtimeSubscriptionState.pending);
    }
    final StreamSubscription<String>? sub = _socketSubscription;
    final RealtimeSocket? socket = _socket;
    _socketSubscription = null;
    _socket = null;
    unawaited(_closeQuietly(sub, socket));
  }

  static Future<void> _closeQuietly(
    StreamSubscription<String>? sub,
    RealtimeSocket? socket,
  ) async {
    try {
      // Avval obunani bekor qilamiz (kech xabarlar kelmasin), keyin soketni yopamiz; bekor qilishni kutmaymiz:
      // yopilmagan oqimda u tugamasligi mumkin
      unawaited(sub?.cancel());
      await socket?.close();
    } on Object {
      // Uzilgan ulanishni yopishdagi xato muhim emas
    }
  }

  void _scheduleReconnect() {
    _reconnectTimer?.cancel();
    if (!_running) return;
    _setStatus(RealtimeStatus.reconnecting);
    final Duration delay = backoff.delay(_attempt++);
    _reconnectTimer = Timer(delay, () => unawaited(_open()));
  }

  // --- xabarlar ---------------------------------------------------------------------------------------------------

  void _onMessage(int generation, String raw) {
    if (generation != _generation) return;
    _armActivityTimer();
    final Map<String, Object?> message;
    try {
      message = (jsonDecode(raw) as Map<Object?, Object?>)
          .cast<String, Object?>();
    } on Object catch (e) {
      _error(FormatException('Realtime: noto\'g\'ri xabar', raw, 0));
      _error(e);
      return;
    }
    final String event = '${message['event'] ?? ''}';
    final String? channel = message['channel'] as String?;
    final Map<String, Object?> data = _decodeData(message['data']);

    switch (event) {
      case 'pusher:connection_established':
        _socketId = data['socket_id'] as String?;
        final Object? timeout = data['activity_timeout'];
        if (timeout is num && timeout > 0) {
          _activityTimeout = Duration(seconds: timeout.toInt());
        }
        _attempt = 0;
        _armActivityTimer();
        _setStatus(RealtimeStatus.connected);
        for (final String c in _channels) {
          unawaited(_subscribe(c));
        }
      case 'pusher:ping':
        _send('pusher:pong', const <String, Object?>{});
      case 'pusher:pong':
        _pongTimer?.cancel();
        _pongTimer = null;
      case 'pusher:error':
        _error(RealtimeServerError(data['code'] as int?, '${data['message']}'));
        // 4000-4099: server qayta ulanmang deydi (masalan, kalit yoki origin noto'g'ri) — baribir uzoqroq kutib
        // urinamiz: sozlama tuzatilgach ekran o'zi tiklanadi.
        final Object? code = data['code'];
        if (code is int && code >= 4000 && code < 4100) {
          _attempt = _attempt < 4 ? 4 : _attempt;
        }
        _lost();
      case 'pusher_internal:subscription_succeeded':
        if (channel != null && _channels.contains(channel)) {
          _subscribed.add(channel);
          _subscribeAttempts.remove(channel);
          _subscribeRetries.remove(channel)?.cancel();
          _setChannelState(channel, RealtimeSubscriptionState.subscribed);
        }
      case 'pusher:subscription_error':
        if (channel != null && _channels.contains(channel)) {
          _error(RealtimeSubscriptionError(channel, data));
          _setChannelState(
            channel,
            RealtimeSubscriptionState.failed,
            error: data,
          );
          _retrySubscribe(channel);
        }
      default:
        if (channel != null &&
            _channels.contains(channel) &&
            !event.startsWith('pusher:') &&
            !event.startsWith('pusher_internal:')) {
          _events.add(
            RealtimeEvent(channel: channel, event: event, data: data),
          );
        }
    }
  }

  Future<void> _subscribe(String channel) async {
    final String? socketId = _socketId;
    final int generation = _generation;
    if (socketId == null) return;
    String? auth;
    if (channel.startsWith('private-') || channel.startsWith('presence-')) {
      try {
        auth = await authorize(socketId, channel);
      } on Object catch (e) {
        if (generation != _generation || !_channels.contains(channel)) return;
        _error(RealtimeSubscriptionError(channel, e));
        _setChannelState(channel, RealtimeSubscriptionState.failed, error: e);
        _retrySubscribe(channel);
        return;
      }
    }
    if (generation != _generation || !_channels.contains(channel)) return;
    _send('pusher:subscribe', <String, Object?>{
      'channel': channel,
      'auth': ?auth,
    });
  }

  void _retrySubscribe(String channel) {
    _subscribeRetries.remove(channel)?.cancel();
    final int attempt = _subscribeAttempts[channel] ?? 0;
    _subscribeAttempts[channel] = attempt + 1;
    _subscribeRetries[channel] = Timer(backoff.delay(attempt), () {
      _subscribeRetries.remove(channel);
      if (_status == RealtimeStatus.connected && _channels.contains(channel)) {
        unawaited(_subscribe(channel));
      }
    });
  }

  void _send(String event, Map<String, Object?> data) {
    final RealtimeSocket? socket = _socket;
    if (socket == null) return;
    try {
      socket.send(jsonEncode(<String, Object?>{'event': event, 'data': data}));
    } on Object catch (e) {
      _error(e);
    }
  }

  void _armActivityTimer() {
    _activityTimer?.cancel();
    _activityTimer = Timer(_activityTimeout, () {
      if (_socket == null) return;
      _send('pusher:ping', const <String, Object?>{});
      _pongTimer?.cancel();
      _pongTimer = Timer(pongTimeout, () {
        _error(const RealtimeServerError(null, 'pong kelmadi'));
        _lost();
      });
    });
  }

  /// Holat o'zgarsa xabar beradi. Qayta urinish paytida [RealtimeSubscriptionState.failed] saqlanadi (ekran
  /// "jonli" va "polling" orasida miltillamasin): faqat muvaffaqiyat yoki ulanish uzilishi uni almashtiradi.
  void _setChannelState(
    String channel,
    RealtimeSubscriptionState state, {
    Object? error,
  }) {
    final RealtimeSubscriptionState? before = _states[channel];
    _states[channel] = state;
    if (before == state && state != RealtimeSubscriptionState.failed) return;
    if (before == null && state == RealtimeSubscriptionState.pending) return;
    if (!_channelStates.isClosed) {
      _channelStates.add(RealtimeChannelState(channel, state, error: error));
    }
  }

  void _setStatus(RealtimeStatus status) {
    if (_status == status) return;
    _status = status;
    if (!_statuses.isClosed) _statuses.add(status);
  }

  void _error(Object error) {
    if (!_errors.isClosed) _errors.add(error);
  }

  /// Pusher `data` maydoni JSON satr (server hodisalarida) yoki obyekt bo'lishi mumkin.
  static Map<String, Object?> _decodeData(Object? data) {
    Object? value = data;
    if (value is String) {
      try {
        value = jsonDecode(value);
      } on FormatException {
        return <String, Object?>{'value': data};
      }
    }
    if (value is Map) return value.cast<String, Object?>();
    return <String, Object?>{};
  }
}

/// Server `pusher:error` yubordi yoki ping'ga javob bermadi.
class RealtimeServerError implements Exception {
  const RealtimeServerError(this.code, this.message);

  final int? code;
  final String message;

  @override
  String toString() => 'RealtimeServerError($code): $message';
}

/// Kanalga obuna bo'lib bo'lmadi (imzo olinmadi yoki server rad etdi).
class RealtimeSubscriptionError implements Exception {
  const RealtimeSubscriptionError(this.channel, this.cause);

  final String channel;
  final Object? cause;

  @override
  String toString() => 'RealtimeSubscriptionError($channel): $cause';
}

/// Backend manzilidan (`https://<domen>/api`) Reverb manzili: `wss://<domen>/app/<key>?protocol=7&...`.
/// [override] berilsa (`ws://127.0.0.1:8080`) xost va port undan olinadi.
Uri realtimeUri({
  required String apiBaseUrl,
  required String appKey,
  String? override,
  String clientName = 'rizqim-dart',
  String version = '1.0',
}) {
  final Uri base = Uri.parse(
    override != null && override.isNotEmpty ? override : apiBaseUrl,
  );
  final String scheme = switch (base.scheme) {
    'https' || 'wss' => 'wss',
    _ => 'ws',
  };
  return Uri(
    scheme: scheme,
    host: base.host,
    port: base.hasPort ? base.port : null,
    path: '/app/$appKey',
    queryParameters: <String, String>{
      'protocol': '7',
      'client': clientName,
      'version': version,
      'flash': 'false',
    },
  );
}

/// Brauzer bo'lmagan klient `Origin` yubormaydi, Reverb esa `allowed_origins` `*` bo'lmasa uni talab qiladi: ilova
/// backend manzilining origin'ini yuboradi (`https://api.example.uz`). Shu xost `REVERB_ALLOWED_ORIGINS` da bo'lishi
/// kerak.
String originOf(String apiBaseUrl) => Uri.parse(apiBaseUrl).origin;
