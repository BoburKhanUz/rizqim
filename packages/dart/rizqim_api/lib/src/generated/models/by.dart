// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by.g.dart';

@JsonSerializable()
class By {
  const By({required this.id, required this.name});

  factory By.fromJson(Map<String, Object?> json) => _$ByFromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$ByToJson(this);
}
