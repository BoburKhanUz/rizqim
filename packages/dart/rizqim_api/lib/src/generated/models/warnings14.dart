// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'warnings14.g.dart';

@JsonSerializable()
class Warnings14 {
  const Warnings14({required this.code, required this.message});

  factory Warnings14.fromJson(Map<String, Object?> json) =>
      _$Warnings14FromJson(json);

  final String code;
  final String message;

  Map<String, Object?> toJson() => _$Warnings14ToJson(this);
}
