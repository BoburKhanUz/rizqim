import 'dart:convert';

import 'models.dart';

/// Maxfiy kalit-qiymat ombori. Ilovalar buni `flutter_secure_storage` (Keychain, Keystore, libsecret, DPAPI) bilan
/// amalga oshiradi: bu paket Flutter'ga bog'liq emas. Tokenlar hech qachon `SharedPreferences` yoki oddiy faylda
/// saqlanmaydi.
abstract interface class SecureKeyValueStore {
  Future<String?> read(String key);

  Future<void> write(String key, String value);

  Future<void> delete(String key);
}

/// Testlar va vaqtinchalik sessiyalar uchun xotiradagi ombor.
class MemoryKeyValueStore implements SecureKeyValueStore {
  final Map<String, String> values = <String, String>{};

  @override
  Future<String?> read(String key) async => values[key];

  @override
  Future<void> write(String key, String value) async => values[key] = value;

  @override
  Future<void> delete(String key) async => values.remove(key);
}

/// Qurilmada saqlanadigan kirish ma'lumotlari: ulangan terminal va/yoki shaxsiy ilova sessiyasi.
///
/// Xodim (PIN) sessiyasi **saqlanmaydi**: ilova qayta ishga tushsa, xodim PIN'ni qayta teradi.
class StoredCredentials {
  const StoredCredentials({this.terminal, this.user});

  final PairedTerminal? terminal;
  final UserSession? user;

  bool get isEmpty => terminal == null && user == null;
}

/// [StoredCredentials] ni bitta kalit ostida JSON qilib yozadi/o'qiydi. Buzilgan yozuv "kirmagan" deb o'qiladi.
class CredentialStorage {
  CredentialStorage(this._store);

  static const String key = 'rizqim.auth.v1';

  final SecureKeyValueStore _store;

  // Yozuvlar navbat bilan bajariladi: tez ketma-ket ikkita o'zgarishda eskisi yangisini bosib ketmasin.
  Future<void> _queue = Future<void>.value();

  Future<StoredCredentials> read() async {
    try {
      final String? raw = await _store.read(key);
      if (raw == null) return const StoredCredentials();
      final Map<String, Object?> json =
          (jsonDecode(raw) as Map<Object?, Object?>).cast<String, Object?>();
      final Object? terminal = json['terminal'];
      final Object? user = json['user'];
      return StoredCredentials(
        terminal: terminal is Map
            ? PairedTerminal.fromJson(terminal.cast<String, Object?>())
            : null,
        user: user is Map
            ? UserSession.fromJson(user.cast<String, Object?>())
            : null,
      );
    } on Object {
      return const StoredCredentials();
    }
  }

  Future<void> write(StoredCredentials credentials) {
    final Future<void> next = _queue.then((_) async {
      if (credentials.isEmpty) {
        await _store.delete(key);
        return;
      }
      await _store.write(
        key,
        jsonEncode(<String, Object?>{
          'terminal': credentials.terminal?.toJson(),
          'user': credentials.user?.toJson(),
        }),
      );
    });
    // Bitta yozuvdagi xato keyingilarini to'xtatmasin; xato chaqiruvchiga qaytadi
    _queue = next.then((_) {}, onError: (Object _) {});
    return next;
  }
}
