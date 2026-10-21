// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device12.g.dart';

@JsonSerializable()
class Device12 {
  const Device12({required this.uuid, required this.name});

  factory Device12.fromJson(Map<String, Object?> json) =>
      _$Device12FromJson(json);

  final String uuid;
  final String name;

  Map<String, Object?> toJson() => _$Device12ToJson(this);
}
