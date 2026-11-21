// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'results2.dart';
import 'unreadable.dart';
import 'device21.dart';

part 'data348.g.dart';

@JsonSerializable()
class Data348 {
  const Data348({
    required this.serverTime,
    required this.clockSkewSeconds,
    required this.clockWarning,
    required this.stoppedAt,
    required this.results,
    required this.unreadable,
    required this.device,
  });

  factory Data348.fromJson(Map<String, Object?> json) =>
      _$Data348FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'clock_skew_seconds')
  final int? clockSkewSeconds;
  @JsonKey(name: 'clock_warning')
  final bool clockWarning;
  @JsonKey(name: 'stopped_at')
  final int? stoppedAt;
  final List<Results2> results;
  final List<Unreadable> unreadable;
  final Device21 device;

  Map<String, Object?> toJson() => _$Data348ToJson(this);
}
