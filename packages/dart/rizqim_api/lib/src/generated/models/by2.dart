// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by2.g.dart';

@JsonSerializable()
class By2 {
  const By2({required this.id, required this.name});

  factory By2.fromJson(Map<String, Object?> json) => _$By2FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By2ToJson(this);
}
