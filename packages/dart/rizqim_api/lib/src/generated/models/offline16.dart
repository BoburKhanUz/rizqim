// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'flags15.dart';

part 'offline16.g.dart';

@JsonSerializable()
class Offline16 {
  const Offline16({
    required this.localNumber,
    required this.createdAt,
    required this.syncedAt,
    required this.flags,
  });

  factory Offline16.fromJson(Map<String, Object?> json) =>
      _$Offline16FromJson(json);

  @JsonKey(name: 'local_number')
  final String? localNumber;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'synced_at')
  final String? syncedAt;
  final List<Flags15> flags;

  Map<String, Object?> toJson() => _$Offline16ToJson(this);
}
