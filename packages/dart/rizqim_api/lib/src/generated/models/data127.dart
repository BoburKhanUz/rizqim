// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data127.g.dart';

@JsonSerializable()
class Data127 {
  const Data127({required this.hallId});

  factory Data127.fromJson(Map<String, Object?> json) =>
      _$Data127FromJson(json);

  @JsonKey(name: 'hall_id')
  final int hallId;

  Map<String, Object?> toJson() => _$Data127ToJson(this);
}
