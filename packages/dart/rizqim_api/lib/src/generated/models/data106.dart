// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data106.g.dart';

@JsonSerializable()
class Data106 {
  const Data106({required this.hallId});

  factory Data106.fromJson(Map<String, Object?> json) =>
      _$Data106FromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;

  Map<String, Object?> toJson() => _$Data106ToJson(this);
}
