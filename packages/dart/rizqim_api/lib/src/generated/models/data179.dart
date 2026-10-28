// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'results.dart';
import 'device19.dart';

part 'data179.g.dart';

@JsonSerializable()
class Data179 {
  const Data179({
    required this.serverTime,
    required this.clockSkewSeconds,
    required this.clockWarning,
    required this.stoppedAt,
    required this.results,
    required this.device,
  });

  factory Data179.fromJson(Map<String, Object?> json) =>
      _$Data179FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'clock_skew_seconds')
  final int? clockSkewSeconds;
  @JsonKey(name: 'clock_warning')
  final bool clockWarning;
  @JsonKey(name: 'stopped_at')
  final int? stoppedAt;
  final List<Results> results;
  final Device19 device;

  Map<String, Object?> toJson() => _$Data179ToJson(this);
}
