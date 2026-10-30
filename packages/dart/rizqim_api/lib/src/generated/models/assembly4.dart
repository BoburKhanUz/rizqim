// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations12.dart';

part 'assembly4.g.dart';

@JsonSerializable()
class Assembly4 {
  const Assembly4({
    required this.stationId,
    required this.requiredValue,
    required this.waiting,
    required this.assembledAt,
    required this.stations,
  });

  factory Assembly4.fromJson(Map<String, Object?> json) =>
      _$Assembly4FromJson(json);

  @JsonKey(name: 'station_id')
  final int stationId;

  /// The name has been replaced because it contains a keyword. Original name: `required`.
  @JsonKey(name: 'required')
  final bool requiredValue;
  final bool waiting;
  @JsonKey(name: 'assembled_at')
  final String? assembledAt;
  final List<Stations12> stations;

  Map<String, Object?> toJson() => _$Assembly4ToJson(this);
}
