// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device_app.dart';

part 'candidates2.g.dart';

@JsonSerializable()
class Candidates2 {
  const Candidates2({
    required this.uuid,
    required this.name,
    required this.platform,
    required this.app,
    required this.lastSeenAt,
  });

  factory Candidates2.fromJson(Map<String, Object?> json) =>
      _$Candidates2FromJson(json);

  final String uuid;
  final String? name;
  final String? platform;
  final DeviceApp? app;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;

  Map<String, Object?> toJson() => _$Candidates2ToJson(this);
}
