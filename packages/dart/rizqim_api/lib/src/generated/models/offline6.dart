// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'flags5.dart';

part 'offline6.g.dart';

@JsonSerializable()
class Offline6 {
  const Offline6({
    required this.localNumber,
    required this.createdAt,
    required this.syncedAt,
    required this.flags,
  });

  factory Offline6.fromJson(Map<String, Object?> json) =>
      _$Offline6FromJson(json);

  @JsonKey(name: 'local_number')
  final String? localNumber;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'synced_at')
  final String? syncedAt;
  final List<Flags5> flags;

  Map<String, Object?> toJson() => _$Offline6ToJson(this);
}
