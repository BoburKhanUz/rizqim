// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items26.dart';

part 'bills3.g.dart';

@JsonSerializable()
class Bills3 {
  const Bills3({required this.items, this.guests});

  factory Bills3.fromJson(Map<String, Object?> json) => _$Bills3FromJson(json);

  final List<Items26> items;
  final int? guests;

  Map<String, Object?> toJson() => _$Bills3ToJson(this);
}
