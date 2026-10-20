// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'polygon.g.dart';

@JsonSerializable()
class Polygon {
  const Polygon({required this.type, required this.coordinates});

  factory Polygon.fromJson(Map<String, Object?> json) =>
      _$PolygonFromJson(json);

  final String type;
  final List<dynamic> coordinates;

  Map<String, Object?> toJson() => _$PolygonToJson(this);
}
