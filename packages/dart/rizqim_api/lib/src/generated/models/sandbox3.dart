// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sandbox3.g.dart';

@JsonSerializable()
class Sandbox3 {
  const Sandbox3({
    this.latencyMs,
    this.failAttempts,
    this.failureRate,
    this.reject,
  });

  factory Sandbox3.fromJson(Map<String, Object?> json) =>
      _$Sandbox3FromJson(json);

  @JsonKey(name: 'latency_ms')
  final int? latencyMs;
  @JsonKey(name: 'fail_attempts')
  final int? failAttempts;
  @JsonKey(name: 'failure_rate')
  final int? failureRate;
  final bool? reject;

  Map<String, Object?> toJson() => _$Sandbox3ToJson(this);
}
