// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by4.g.dart';

@JsonSerializable()
class By4 {
  const By4({required this.id, required this.name});

  factory By4.fromJson(Map<String, Object?> json) => _$By4FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By4ToJson(this);
}
