// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device13.g.dart';

@JsonSerializable()
class Device13 {
  const Device13({required this.uuid, required this.name});

  factory Device13.fromJson(Map<String, Object?> json) =>
      _$Device13FromJson(json);

  final String uuid;
  final String name;

  Map<String, Object?> toJson() => _$Device13ToJson(this);
}
