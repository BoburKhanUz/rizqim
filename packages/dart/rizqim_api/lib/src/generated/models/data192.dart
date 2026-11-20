// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data192.g.dart';

@JsonSerializable()
class Data192 {
  const Data192({required this.hallId});

  factory Data192.fromJson(Map<String, Object?> json) =>
      _$Data192FromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;

  Map<String, Object?> toJson() => _$Data192ToJson(this);
}
