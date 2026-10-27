// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'device10.g.dart';

@JsonSerializable()
class Device10 {
  const Device10({required this.uuid, required this.name, required this.type});

  factory Device10.fromJson(Map<String, Object?> json) =>
      _$Device10FromJson(json);

  final String uuid;
  final String? name;
  final String type;

  Map<String, Object?> toJson() => _$Device10ToJson(this);
}
