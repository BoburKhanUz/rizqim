// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by3.g.dart';

@JsonSerializable()
class By3 {
  const By3({required this.id, required this.name});

  factory By3.fromJson(Map<String, Object?> json) => _$By3FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By3ToJson(this);
}
