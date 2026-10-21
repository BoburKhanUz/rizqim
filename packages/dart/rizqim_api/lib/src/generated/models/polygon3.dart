// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'polygon3.g.dart';

@JsonSerializable()
class Polygon3 {
  const Polygon3({required this.type, required this.coordinates});

  factory Polygon3.fromJson(Map<String, Object?> json) =>
      _$Polygon3FromJson(json);

  final String type;
  final List<dynamic> coordinates;

  Map<String, Object?> toJson() => _$Polygon3ToJson(this);
}
