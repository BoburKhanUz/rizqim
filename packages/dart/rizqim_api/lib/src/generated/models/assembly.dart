// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'stations9.dart';

part 'assembly.g.dart';

@JsonSerializable()
class Assembly {
  const Assembly({
    required this.stationId,
    required this.requiredValue,
    required this.waiting,
    required this.assembledAt,
    required this.stations,
  });

  factory Assembly.fromJson(Map<String, Object?> json) =>
      _$AssemblyFromJson(json);

  @JsonKey(name: 'station_id')
  final int stationId;

  /// The name has been replaced because it contains a keyword. Original name: `required`.
  @JsonKey(name: 'required')
  final bool requiredValue;
  final bool waiting;
  @JsonKey(name: 'assembled_at')
  final String? assembledAt;
  final List<Stations9> stations;

  Map<String, Object?> toJson() => _$AssemblyToJson(this);
}
