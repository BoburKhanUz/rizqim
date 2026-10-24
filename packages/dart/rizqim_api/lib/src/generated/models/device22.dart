// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device22.g.dart';

@JsonSerializable()
class Device22 {
  const Device22({
    required this.uuid,
    required this.name,
    required this.prefix,
    required this.lastSeq,
    required this.pendingOps,
    required this.oldestOpAt,
    required this.reportedAt,
    required this.clockSkewSeconds,
    required this.clockWarning,
  });

  factory Device22.fromJson(Map<String, Object?> json) =>
      _$Device22FromJson(json);

  final String uuid;
  final String? name;
  final String? prefix;
  @JsonKey(name: 'last_seq')
  final int lastSeq;
  @JsonKey(name: 'pending_ops')
  final int pendingOps;
  @JsonKey(name: 'oldest_op_at')
  final String? oldestOpAt;
  @JsonKey(name: 'reported_at')
  final String? reportedAt;
  @JsonKey(name: 'clock_skew_seconds')
  final int? clockSkewSeconds;
  @JsonKey(name: 'clock_warning')
  final bool clockWarning;

  Map<String, Object?> toJson() => _$Device22ToJson(this);
}
