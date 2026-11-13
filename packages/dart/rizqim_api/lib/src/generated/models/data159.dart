// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data159.g.dart';

@JsonSerializable()
class Data159 {
  const Data159({required this.hallId});

  factory Data159.fromJson(Map<String, Object?> json) =>
      _$Data159FromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;

  Map<String, Object?> toJson() => _$Data159ToJson(this);
}
