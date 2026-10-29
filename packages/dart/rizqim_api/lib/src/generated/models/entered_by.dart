// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'entered_by.g.dart';

@JsonSerializable()
class EnteredBy {
  const EnteredBy({required this.id, required this.name});

  factory EnteredBy.fromJson(Map<String, Object?> json) =>
      _$EnteredByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$EnteredByToJson(this);
}
