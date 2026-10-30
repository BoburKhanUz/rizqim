// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'weighed_by14.g.dart';

@JsonSerializable()
class WeighedBy14 {
  const WeighedBy14({required this.id, required this.name});

  factory WeighedBy14.fromJson(Map<String, Object?> json) =>
      _$WeighedBy14FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$WeighedBy14ToJson(this);
}
