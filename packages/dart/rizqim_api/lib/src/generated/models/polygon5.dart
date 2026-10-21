// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'polygon5.g.dart';

@JsonSerializable()
class Polygon5 {
  const Polygon5({required this.type, required this.coordinates});

  factory Polygon5.fromJson(Map<String, Object?> json) =>
      _$Polygon5FromJson(json);

  final String type;
  final List<dynamic> coordinates;

  Map<String, Object?> toJson() => _$Polygon5ToJson(this);
}
