// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'flags10.dart';

part 'offline11.g.dart';

@JsonSerializable()
class Offline11 {
  const Offline11({
    required this.localNumber,
    required this.createdAt,
    required this.syncedAt,
    required this.flags,
  });

  factory Offline11.fromJson(Map<String, Object?> json) =>
      _$Offline11FromJson(json);

  @JsonKey(name: 'local_number')
  final String? localNumber;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'synced_at')
  final String? syncedAt;
  final List<Flags10> flags;

  Map<String, Object?> toJson() => _$Offline11ToJson(this);
}
