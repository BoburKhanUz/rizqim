// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings16.g.dart';

@JsonSerializable()
class Warnings16 {
  const Warnings16({required this.code, required this.message});

  factory Warnings16.fromJson(Map<String, Object?> json) =>
      _$Warnings16FromJson(json);

  final String code;
  final String message;

  Map<String, Object?> toJson() => _$Warnings16ToJson(this);
}
