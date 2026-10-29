// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by8.g.dart';

@JsonSerializable()
class WeighedBy8 {
  const WeighedBy8({required this.id, required this.name});

  factory WeighedBy8.fromJson(Map<String, Object?> json) =>
      _$WeighedBy8FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy8ToJson(this);
}
