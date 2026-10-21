// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'polygon4.g.dart';

@JsonSerializable()
class Polygon4 {
  const Polygon4({required this.type, required this.coordinates});

  factory Polygon4.fromJson(Map<String, Object?> json) =>
      _$Polygon4FromJson(json);

  final String type;
  final List<dynamic> coordinates;

  Map<String, Object?> toJson() => _$Polygon4ToJson(this);
}
