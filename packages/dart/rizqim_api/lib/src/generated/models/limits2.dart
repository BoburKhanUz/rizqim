// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'limits2.g.dart';

@JsonSerializable()
class Limits2 {
  const Limits2({required this.min, required this.max});

  factory Limits2.fromJson(Map<String, Object?> json) =>
      _$Limits2FromJson(json);

  final int min;
  final int max;

  Map<String, Object?> toJson() => _$Limits2ToJson(this);
}
