// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by12.g.dart';

@JsonSerializable()
class WeighedBy12 {
  const WeighedBy12({required this.id, required this.name});

  factory WeighedBy12.fromJson(Map<String, Object?> json) =>
      _$WeighedBy12FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy12ToJson(this);
}
