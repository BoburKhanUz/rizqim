// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'device_app.dart';

part 'candidates.g.dart';

@JsonSerializable()
class Candidates {
  const Candidates({
    required this.uuid,
    required this.name,
    required this.platform,
    required this.app,
    required this.lastSeenAt,
  });

  factory Candidates.fromJson(Map<String, Object?> json) =>
      _$CandidatesFromJson(json);

  final String uuid;
  final String? name;
  final String? platform;
  final DeviceApp? app;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;

  Map<String, Object?> toJson() => _$CandidatesToJson(this);
}
