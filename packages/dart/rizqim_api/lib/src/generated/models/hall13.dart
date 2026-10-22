// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'hall13.g.dart';

@JsonSerializable()
class Hall13 {
  const Hall13({required this.id, required this.name});

  factory Hall13.fromJson(Map<String, Object?> json) => _$Hall13FromJson(json);

  final int id;
  final String? name;

  Map<String, Object?> toJson() => _$Hall13ToJson(this);
}
