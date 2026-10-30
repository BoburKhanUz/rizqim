// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'stations10.g.dart';

@JsonSerializable()
class Stations10 {
  const Stations10({
    required this.stationId,
    required this.name,
    required this.kind,
    required this.itemsTotal,
    required this.itemsReady,
    required this.done,
  });

  factory Stations10.fromJson(Map<String, Object?> json) =>
      _$Stations10FromJson(json);

  @JsonKey(name: 'station_id')
  final int? stationId;
  final String name;
  final String? kind;
  @JsonKey(name: 'items_total')
  final int itemsTotal;
  @JsonKey(name: 'items_ready')
  final int itemsReady;
  final bool done;

  Map<String, Object?> toJson() => _$Stations10ToJson(this);
}
