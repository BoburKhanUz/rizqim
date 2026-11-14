// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings18.g.dart';

@JsonSerializable()
class Warnings18 {
  const Warnings18({required this.code, required this.message});

  factory Warnings18.fromJson(Map<String, Object?> json) =>
      _$Warnings18FromJson(json);

  final String code;
  final String message;

  Map<String, Object?> toJson() => _$Warnings18ToJson(this);
}
