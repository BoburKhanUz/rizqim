// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'providers2.g.dart';

@JsonSerializable()
class Providers2 {
  const Providers2({required this.key, required this.label});

  factory Providers2.fromJson(Map<String, Object?> json) =>
      _$Providers2FromJson(json);

  final String key;
  final String label;

  Map<String, Object?> toJson() => _$Providers2ToJson(this);
}
