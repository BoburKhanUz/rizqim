// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by.g.dart';

@JsonSerializable()
class WeighedBy {
  const WeighedBy({required this.id, required this.name});

  factory WeighedBy.fromJson(Map<String, Object?> json) =>
      _$WeighedByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedByToJson(this);
}
