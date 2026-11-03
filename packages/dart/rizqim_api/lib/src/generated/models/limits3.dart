// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'limits3.g.dart';

@JsonSerializable()
class Limits3 {
  const Limits3({required this.min, required this.max});

  factory Limits3.fromJson(Map<String, Object?> json) =>
      _$Limits3FromJson(json);

  final int min;
  final int max;

  Map<String, Object?> toJson() => _$Limits3ToJson(this);
}
