// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sandbox2.g.dart';

@JsonSerializable()
class Sandbox2 {
  const Sandbox2({
    required this.latencyMs,
    required this.failAttempts,
    required this.failureRate,
    required this.reject,
  });

  factory Sandbox2.fromJson(Map<String, Object?> json) =>
      _$Sandbox2FromJson(json);

  @JsonKey(name: 'latency_ms')
  final int latencyMs;
  @JsonKey(name: 'fail_attempts')
  final int failAttempts;
  @JsonKey(name: 'failure_rate')
  final int failureRate;
  final bool reject;

  Map<String, Object?> toJson() => _$Sandbox2ToJson(this);
}
