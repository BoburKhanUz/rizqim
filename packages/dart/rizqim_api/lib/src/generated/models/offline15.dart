// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'flags14.dart';

part 'offline15.g.dart';

@JsonSerializable()
class Offline15 {
  const Offline15({
    required this.localNumber,
    required this.createdAt,
    required this.syncedAt,
    required this.flags,
  });

  factory Offline15.fromJson(Map<String, Object?> json) =>
      _$Offline15FromJson(json);

  @JsonKey(name: 'local_number')
  final String? localNumber;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'synced_at')
  final String? syncedAt;
  final List<Flags14> flags;

  Map<String, Object?> toJson() => _$Offline15ToJson(this);
}
