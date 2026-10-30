// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'format.dart';

part 'data127.g.dart';

@JsonSerializable()
class Data127 {
  const Data127({
    required this.stationsCreated,
    required this.stationsSkipped,
    required this.categoriesCreated,
    required this.dishesCreated,
    required this.dishesSkipped,
    required this.format,
  });

  factory Data127.fromJson(Map<String, Object?> json) =>
      _$Data127FromJson(json);

  @JsonKey(name: 'stations_created')
  final List<String> stationsCreated;
  @JsonKey(name: 'stations_skipped')
  final List<String> stationsSkipped;
  @JsonKey(name: 'categories_created')
  final List<String> categoriesCreated;
  @JsonKey(name: 'dishes_created')
  final List<String> dishesCreated;
  @JsonKey(name: 'dishes_skipped')
  final List<String> dishesSkipped;
  final Format format;

  Map<String, Object?> toJson() => _$Data127ToJson(this);
}
