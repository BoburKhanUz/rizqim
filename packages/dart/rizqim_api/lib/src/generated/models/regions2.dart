// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'regions2.g.dart';

@JsonSerializable()
class Regions2 {
  const Regions2({required this.id, required this.name});

  factory Regions2.fromJson(Map<String, Object?> json) =>
      _$Regions2FromJson(json);

  final int id;
  final String name;

  Map<String, Object?> toJson() => _$Regions2ToJson(this);
}
