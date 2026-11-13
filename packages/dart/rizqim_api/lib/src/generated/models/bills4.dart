// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items29.dart';

part 'bills4.g.dart';

@JsonSerializable()
class Bills4 {
  const Bills4({required this.items, this.guests});

  factory Bills4.fromJson(Map<String, Object?> json) => _$Bills4FromJson(json);

  final List<Items29> items;
  final int? guests;

  Map<String, Object?> toJson() => _$Bills4ToJson(this);
}
