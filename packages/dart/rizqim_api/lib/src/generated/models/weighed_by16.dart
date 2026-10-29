// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by16.g.dart';

@JsonSerializable()
class WeighedBy16 {
  const WeighedBy16({required this.id, required this.name});

  factory WeighedBy16.fromJson(Map<String, Object?> json) =>
      _$WeighedBy16FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy16ToJson(this);
}
