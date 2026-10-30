// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by10.g.dart';

@JsonSerializable()
class WeighedBy10 {
  const WeighedBy10({required this.id, required this.name});

  factory WeighedBy10.fromJson(Map<String, Object?> json) =>
      _$WeighedBy10FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy10ToJson(this);
}
