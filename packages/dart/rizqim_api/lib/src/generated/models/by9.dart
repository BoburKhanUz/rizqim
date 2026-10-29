// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by9.g.dart';

@JsonSerializable()
class By9 {
  const By9({required this.id, required this.name});

  factory By9.fromJson(Map<String, Object?> json) => _$By9FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By9ToJson(this);
}
