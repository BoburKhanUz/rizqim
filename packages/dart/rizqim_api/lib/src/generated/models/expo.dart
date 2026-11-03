// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'expo.g.dart';

@JsonSerializable()
class Expo {
  const Expo({
    required this.stationId,
    required this.name,
    required this.targetSeconds,
    required this.count,
    required this.medianSeconds,
    required this.p90Seconds,
    required this.withTarget,
    required this.overTarget,
    required this.overTargetShare,
  });

  factory Expo.fromJson(Map<String, Object?> json) => _$ExpoFromJson(json);

  @JsonKey(name: 'station_id')
  final int stationId;
  final String name;
  @JsonKey(name: 'target_seconds')
  final int? targetSeconds;
  final int count;
  @JsonKey(name: 'median_seconds')
  final int? medianSeconds;
  @JsonKey(name: 'p90_seconds')
  final int? p90Seconds;
  @JsonKey(name: 'with_target')
  final int withTarget;
  @JsonKey(name: 'over_target')
  final int overTarget;
  @JsonKey(name: 'over_target_share')
  final String? overTargetShare;

  Map<String, Object?> toJson() => _$ExpoToJson(this);
}
