// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'modes2.g.dart';

@JsonSerializable()
class Modes2 {
  const Modes2({required this.key, required this.label});

  factory Modes2.fromJson(Map<String, Object?> json) => _$Modes2FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$Modes2ToJson(this);
}
