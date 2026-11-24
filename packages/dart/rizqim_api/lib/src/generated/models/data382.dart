// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data382.g.dart';

@JsonSerializable()
class Data382 {
  const Data382({required this.key, required this.kid});

  factory Data382.fromJson(Map<String, Object?> json) =>
      _$Data382FromJson(json);

  final String key;
  final int kid;

  Map<String, Object?> toJson() => _$Data382ToJson(this);
}
