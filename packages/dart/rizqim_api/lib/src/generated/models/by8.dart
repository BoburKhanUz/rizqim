// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by8.g.dart';

@JsonSerializable()
class By8 {
  const By8({required this.id, required this.name});

  factory By8.fromJson(Map<String, Object?> json) => _$By8FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By8ToJson(this);
}
