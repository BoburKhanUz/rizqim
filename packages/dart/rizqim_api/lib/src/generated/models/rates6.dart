// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rates6.g.dart';

@JsonSerializable()
class Rates6 {
  const Rates6({this.morning, this.day, this.evening, this.custom});

  factory Rates6.fromJson(Map<String, Object?> json) => _$Rates6FromJson(json);

  final num? morning;
  final num? day;
  final num? evening;
  final num? custom;

  Map<String, Object?> toJson() => _$Rates6ToJson(this);
}
