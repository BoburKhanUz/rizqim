// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rates5.g.dart';

@JsonSerializable()
class Rates5 {
  const Rates5({
    required this.morning,
    required this.day,
    required this.evening,
    required this.custom,
  });

  factory Rates5.fromJson(Map<String, Object?> json) => _$Rates5FromJson(json);

  final String? morning;
  final String? day;
  final String? evening;
  final String? custom;

  Map<String, Object?> toJson() => _$Rates5ToJson(this);
}
