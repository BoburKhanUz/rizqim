// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations10.dart';

part 'assembly2.g.dart';

@JsonSerializable()
class Assembly2 {
  const Assembly2({
    required this.stationId,
    required this.requiredValue,
    required this.waiting,
    required this.assembledAt,
    required this.stations,
  });

  factory Assembly2.fromJson(Map<String, Object?> json) =>
      _$Assembly2FromJson(json);

  @JsonKey(name: 'station_id')
  final int stationId;

  /// The name has been replaced because it contains a keyword. Original name: `required`.
  @JsonKey(name: 'required')
  final bool requiredValue;
  final bool waiting;
  @JsonKey(name: 'assembled_at')
  final String? assembledAt;
  final List<Stations10> stations;

  Map<String, Object?> toJson() => _$Assembly2ToJson(this);
}
