// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings13.g.dart';

@JsonSerializable()
class Warnings13 {
  const Warnings13({required this.code, required this.message});

  factory Warnings13.fromJson(Map<String, Object?> json) =>
      _$Warnings13FromJson(json);

  final String code;
  final String message;

  Map<String, Object?> toJson() => _$Warnings13ToJson(this);
}
