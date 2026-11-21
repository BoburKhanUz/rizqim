// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device25.g.dart';

@JsonSerializable()
class Device25 {
  const Device25({required this.uuid, required this.name});

  factory Device25.fromJson(Map<String, Object?> json) =>
      _$Device25FromJson(json);

  final String uuid;
  final String? name;

  Map<String, Object?> toJson() => _$Device25ToJson(this);
}
