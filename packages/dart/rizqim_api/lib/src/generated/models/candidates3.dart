// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'candidates3.g.dart';

@JsonSerializable()
class Candidates3 {
  const Candidates3({
    required this.uuid,
    required this.name,
    required this.platform,
    required this.lastSeenAt,
  });

  factory Candidates3.fromJson(Map<String, Object?> json) =>
      _$Candidates3FromJson(json);

  final String uuid;
  final String? name;
  final String? platform;
  @JsonKey(name: 'last_seen_at')
  final String? lastSeenAt;

  Map<String, Object?> toJson() => _$Candidates3ToJson(this);
}
