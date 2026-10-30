// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'opening_hours2.g.dart';

@JsonSerializable()
class OpeningHours2 {
  const OpeningHours2({required this.open, required this.close});

  factory OpeningHours2.fromJson(Map<String, Object?> json) =>
      _$OpeningHours2FromJson(json);

  final String? open;
  final String? close;

  Map<String, Object?> toJson() => _$OpeningHours2ToJson(this);
}
