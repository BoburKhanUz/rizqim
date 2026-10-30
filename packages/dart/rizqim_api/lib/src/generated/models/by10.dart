// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by10.g.dart';

@JsonSerializable()
class By10 {
  const By10({required this.id, required this.name});

  factory By10.fromJson(Map<String, Object?> json) => _$By10FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$By10ToJson(this);
}
