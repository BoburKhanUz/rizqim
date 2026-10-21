// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'period3.g.dart';

@JsonSerializable()
class Period3 {
  const Period3({
    required this.from,
    required this.to,
    required this.days,
    required this.timezone,
    required this.basis,
  });

  factory Period3.fromJson(Map<String, Object?> json) =>
      _$Period3FromJson(json);

  final String from;
  final String to;
  final int days;
  final String timezone;
  final String basis;

  Map<String, Object?> toJson() => _$Period3ToJson(this);
}
