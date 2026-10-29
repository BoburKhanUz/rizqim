// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by11.g.dart';

@JsonSerializable()
class WeighedBy11 {
  const WeighedBy11({required this.id, required this.name});

  factory WeighedBy11.fromJson(Map<String, Object?> json) =>
      _$WeighedBy11FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy11ToJson(this);
}
