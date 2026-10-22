import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:rizqim_api/rizqim_api.dart';

/// [SecureKeyValueStore] ning `flutter_secure_storage` bilan amalga oshirilishi: Android Keystore, Windows
/// Credential Locker/DPAPI, Linux libsecret. Terminal tokeni shu yerda saqlanadi.
class SecureStorageStore implements SecureKeyValueStore {
  SecureStorageStore([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  @override
  Future<String?> read(String key) => _storage.read(key: key);

  @override
  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<void> delete(String key) => _storage.delete(key: key);
}
