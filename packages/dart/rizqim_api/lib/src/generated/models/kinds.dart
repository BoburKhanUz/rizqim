// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'kinds.g.dart';

@JsonSerializable()
class Kinds {
  const Kinds({required this.value, required this.label});

  factory Kinds.fromJson(Map<String, Object?> json) => _$KindsFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$KindsToJson(this);
}
