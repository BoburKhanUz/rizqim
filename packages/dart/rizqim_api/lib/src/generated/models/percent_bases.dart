// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'percent_bases.g.dart';

@JsonSerializable()
class PercentBases {
  const PercentBases({required this.value, required this.label});

  factory PercentBases.fromJson(Map<String, Object?> json) =>
      _$PercentBasesFromJson(json);

  final String value;
  final String label;

  Map<String, Object?> toJson() => _$PercentBasesToJson(this);
}
