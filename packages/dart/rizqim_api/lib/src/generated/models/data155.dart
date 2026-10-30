// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data155.g.dart';

@JsonSerializable()
class Data155 {
  const Data155({required this.hallId});

  factory Data155.fromJson(Map<String, Object?> json) =>
      _$Data155FromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;

  Map<String, Object?> toJson() => _$Data155ToJson(this);
}
