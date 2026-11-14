// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by12.g.dart';

@JsonSerializable()
class By12 {
  const By12({required this.id, required this.name});

  factory By12.fromJson(Map<String, Object?> json) => _$By12FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By12ToJson(this);
}
