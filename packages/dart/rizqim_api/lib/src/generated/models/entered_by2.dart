// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'entered_by2.g.dart';

@JsonSerializable()
class EnteredBy2 {
  const EnteredBy2({required this.id, required this.name});

  factory EnteredBy2.fromJson(Map<String, Object?> json) =>
      _$EnteredBy2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$EnteredBy2ToJson(this);
}
