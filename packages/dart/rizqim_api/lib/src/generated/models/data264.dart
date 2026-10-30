// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'results.dart';
import 'device20.dart';

part 'data264.g.dart';

@JsonSerializable()
class Data264 {
  const Data264({
    required this.serverTime,
    required this.clockSkewSeconds,
    required this.clockWarning,
    required this.stoppedAt,
    required this.results,
    required this.device,
  });

  factory Data264.fromJson(Map<String, Object?> json) =>
      _$Data264FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'clock_skew_seconds')
  final int? clockSkewSeconds;
  @JsonKey(name: 'clock_warning')
  final bool clockWarning;
  @JsonKey(name: 'stopped_at')
  final int? stoppedAt;
  final List<Results> results;
  final Device20 device;

  Map<String, Object?> toJson() => _$Data264ToJson(this);
}
