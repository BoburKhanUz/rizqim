// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'entered_by3.g.dart';

@JsonSerializable()
class EnteredBy3 {
  const EnteredBy3({required this.id, required this.name});

  factory EnteredBy3.fromJson(Map<String, Object?> json) =>
      _$EnteredBy3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$EnteredBy3ToJson(this);
}
