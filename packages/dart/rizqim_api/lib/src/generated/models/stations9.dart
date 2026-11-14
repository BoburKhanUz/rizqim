// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'dishes5.dart';

part 'stations9.g.dart';

@JsonSerializable()
class Stations9 {
  const Stations9({
    required this.stationId,
    required this.name,
    required this.dishes,
  });

  factory Stations9.fromJson(Map<String, Object?> json) =>
      _$Stations9FromJson(json);

  @JsonKey(name: 'station_id')
  final int? stationId;
  final String name;
  final List<Dishes5> dishes;

  Map<String, Object?> toJson() => _$Stations9ToJson(this);
}
