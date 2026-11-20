// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sync_key.g.dart';

@JsonSerializable()
class SyncKey {
  const SyncKey({required this.key, required this.kid});

  factory SyncKey.fromJson(Map<String, Object?> json) =>
      _$SyncKeyFromJson(json);

  final String key;
  final int kid;

  Map<String, Object?> toJson() => _$SyncKeyToJson(this);
}
