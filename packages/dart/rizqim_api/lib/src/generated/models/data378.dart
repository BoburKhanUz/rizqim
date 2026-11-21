// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data378.g.dart';

@JsonSerializable()
class Data378 {
  const Data378({required this.key, required this.kid});

  factory Data378.fromJson(Map<String, Object?> json) =>
      _$Data378FromJson(json);

  final String key;
  final int kid;

  Map<String, Object?> toJson() => _$Data378ToJson(this);
}
